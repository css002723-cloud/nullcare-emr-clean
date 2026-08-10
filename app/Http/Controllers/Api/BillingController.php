<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DialysisSession;
use App\Models\Encounter;
use App\Models\ImagingOrder;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\AuditLogger;
use App\Services\ChargeCatalog;
use App\Services\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * GET /api/billing/patients/{patient}/pending-charges
     * Roles: billing, admin
     *
     * Pulls every unbilled, charge-worthy clinical event for this patient —
     * completed lab tests, completed imaging studies, dispensed medications,
     * and completed dialysis sessions — so reception doesn't have to
     * reconstruct the bill by hand from paper notes. An item only shows up
     * here once (it disappears the moment it's added to an invoice, via the
     * chargeable_type/chargeable_id link on invoice_line_items).
     */
    public function pendingCharges(Patient $patient)
    {
        $alreadyBilled = function (string $type) {
            return InvoiceLineItem::where('chargeable_type', $type)->pluck('chargeable_id');
        };

        $labCharges = LabOrder::where('patient_id', $patient->id)
            ->whereIn('status', ['resulted', 'verified'])
            ->whereNotIn('id', $alreadyBilled(LabOrder::class))
            ->get()
            ->map(fn (LabOrder $o) => [
                'chargeable_type' => LabOrder::class,
                'chargeable_id' => $o->id,
                'encounter_id' => $o->encounter_id,
                'service_category' => 'laboratory',
                'description' => 'Lab: '.($o->loinc_display ?: $o->test_code ?: 'Test'),
                'amount' => ChargeCatalog::labOrderPrice($o->test_code),
                'occurred_at' => ($o->received_at ?? $o->created_at)?->toIso8601String(),
            ]);

        $imagingCharges = ImagingOrder::where('patient_id', $patient->id)
            ->where('status', 'reported')
            ->whereNotIn('id', $alreadyBilled(ImagingOrder::class))
            ->get()
            ->map(fn (ImagingOrder $o) => [
                'chargeable_type' => ImagingOrder::class,
                'chargeable_id' => $o->id,
                'encounter_id' => $o->encounter_id,
                'service_category' => 'imaging',
                'description' => 'Imaging: '.($o->study_description ?: $o->modality ?: 'Study'),
                'amount' => ChargeCatalog::imagingOrderPrice($o->modality),
                'occurred_at' => $o->created_at?->toIso8601String(),
            ]);

        $prescriptionCharges = Prescription::where('patient_id', $patient->id)
            ->where('status', 'dispensed')
            ->whereNotIn('id', $alreadyBilled(Prescription::class))
            ->get()
            ->map(fn (Prescription $rx) => [
                'chargeable_type' => Prescription::class,
                'chargeable_id' => $rx->id,
                'encounter_id' => $rx->encounter_id,
                'service_category' => 'pharmacy',
                'description' => 'Medication: '.$rx->drug_name.($rx->formulation ? " ({$rx->formulation})" : ''),
                'amount' => ChargeCatalog::prescriptionPrice(),
                'occurred_at' => $rx->dispensed_at?->toIso8601String(),
            ]);

        $dialysisCharges = DialysisSession::where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->whereNotIn('id', $alreadyBilled(DialysisSession::class))
            ->get()
            ->map(fn (DialysisSession $s) => [
                'chargeable_type' => DialysisSession::class,
                'chargeable_id' => $s->id,
                'encounter_id' => $s->encounter_id,
                'service_category' => 'procedure',
                'description' => 'Dialysis session'.($s->session_date ? ' — '.$s->session_date->format('d M Y') : ''),
                'amount' => ChargeCatalog::dialysisSessionPrice(),
                'occurred_at' => $s->session_date?->toIso8601String(),
            ]);

        $charges = $labCharges->concat($imagingCharges)->concat($prescriptionCharges)->concat($dialysisCharges)
            ->sortByDesc('occurred_at')
            ->values();

        return response()->json([
            'patient_id' => $patient->id,
            'count' => $charges->count(),
            'total' => $charges->sum('amount'),
            'charges' => $charges,
        ]);
    }

    /**
     * GET /api/billing/invoices?status=&encounter_id=
     */
    public function index(Request $request)
    {
        $query = Invoice::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->query('encounter_id'));
        }

        $invoices = $query->latest()->get();

        $result = $invoices->map(function (Invoice $inv) {
            $d = $inv->toArray();
            $d['line_items'] = InvoiceLineItem::where('invoice_id', $inv->id)->get();

            return $d;
        });

        return response()->json($result);
    }

    /**
     * POST /api/billing/invoices
     * Roles: billing, admin
     * Body: { encounter_id, payer_type, payer_name, line_items: [{service_category, description, amount}] }
     */
    public function store(Request $request)
    {
        if (! $request->filled('encounter_id')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id is required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));
        $lineItems = $request->input('line_items', []);

        if (empty($lineItems)) {
            return response()->json([
                'error' => 'missing_line_items',
                'message' => 'Add at least one billable line item with an amount before creating the invoice.',
            ], 400);
        }

        $parsedItems = [];
        foreach ($lineItems as $item) {
            if (! is_numeric($item['amount'] ?? null)) {
                return response()->json(['error' => 'invalid_amount', 'message' => "One or more line item amounts aren't valid numbers."], 400);
            }
            $parsedItems[] = [
                'service_category' => $item['service_category'] ?? null,
                'description' => $item['description'] ?? null,
                'amount' => (float) $item['amount'],
                'chargeable_type' => $item['chargeable_type'] ?? null,
                'chargeable_id' => $item['chargeable_id'] ?? null,
            ];
        }

        foreach ($parsedItems as $item) {
            if ($item['amount'] <= 0) {
                return response()->json(['error' => 'invalid_amount', 'message' => 'Line item amounts must be greater than zero.'], 400);
            }
        }

        $total = array_sum(array_column($parsedItems, 'amount'));

        $invoice = DB::transaction(function () use ($request, $encounter, $parsedItems, $total) {
            $invoice = Invoice::create([
                'invoice_number' => IdGenerator::invoiceNumber(),
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'payer_type' => $request->input('payer_type', 'cash'),
                'payer_name' => $request->input('payer_name'),
                'total_amount' => $total,
                'created_by' => $request->user()->id,
                'client_uuid' => $request->input('client_uuid'),
            ]);

            foreach ($parsedItems as $item) {
                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'service_category' => $item['service_category'],
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'chargeable_type' => $item['chargeable_type'],
                    'chargeable_id' => $item['chargeable_id'],
                ]);
            }

            return $invoice;
        });

        AuditLogger::log($request->user(), 'create_invoice', 'encounter', $encounter->id, "total={$total}");

        $d = $invoice->toArray();
        $d['line_items'] = $parsedItems;

        return response()->json($d, 201);
    }

    /**
     * POST /api/billing/invoices/{invoice}/pay
     * Roles: billing, admin
     * Status: "partial" (not "partially_paid") once paid > 0 but < total.
     */
    public function pay(Request $request, Invoice $invoice)
    {
        $amount = (float) $request->input('amount', 0);

        $invoice->amount_paid += $amount;
        $invoice->payment_reference = $request->input('payment_reference', $invoice->payment_reference);

        if ($invoice->amount_paid >= $invoice->total_amount) {
            $invoice->status = 'paid';
        } elseif ($invoice->amount_paid > 0) {
            $invoice->status = 'partial';
        }

        $invoice->save();

        AuditLogger::log($request->user(), 'record_payment', 'invoice', $invoice->id, "amount={$amount}");

        return response()->json($invoice);
    }

    /**
     * POST /api/billing/invoices/{invoice}/waive
     * Roles: billing, admin
     */
    public function waive(Request $request, Invoice $invoice)
    {
        $invoice->update(['status' => 'waived']);

        AuditLogger::log($request->user(), 'waive_invoice', 'invoice', $invoice->id);

        return response()->json($invoice);
    }

    /**
     * GET /api/billing/unpaid-report
     * Roles: billing, admin
     */
    public function unpaidReport()
    {
        $invoices = Invoice::whereIn('status', ['unpaid', 'partial'])->get();
        $outstanding = $invoices->sum(fn ($i) => $i->total_amount - $i->amount_paid);

        return response()->json([
            'count' => $invoices->count(),
            'outstanding_total' => $outstanding,
            'invoices' => $invoices,
        ]);
    }
}
