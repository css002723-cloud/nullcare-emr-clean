<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
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
