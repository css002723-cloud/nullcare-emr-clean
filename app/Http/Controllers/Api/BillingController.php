<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Encounter;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * GET /api/billing/invoices
     */
    public function index()
    {
        $invoices = Invoice::with(['items', 'payments'])->latest()->get();

        return InvoiceResource::collection($invoices);
    }

    /**
     * POST /api/billing/invoices
     * Body: { encounter_id, payer_type, line_items: [{ service_category, description, amount }] }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'payer_type' => ['required', 'in:cash,insurance,institutional,waiver'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.service_category' => ['required', 'string', 'max:30'],
            'line_items.*.description' => ['nullable', 'string', 'max:150'],
            'line_items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $encounter = Encounter::findOrFail($validated['encounter_id']);

        $invoice = DB::transaction(function () use ($validated, $encounter, $request) {
            $total = collect($validated['line_items'])->sum('amount');

            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'payer_type' => $validated['payer_type'],
                'total_amount' => $total,
                'status' => 'unpaid',
                'created_by' => $request->user()->id,
            ]);

            $invoice->items()->createMany(array_map(fn ($item) => [
                'service_category' => $item['service_category'],
                'service_name' => $item['description'] ?? $item['service_category'],
                'amount' => $item['amount'],
            ], $validated['line_items']));

            return $invoice;
        });

        return response()->json(new InvoiceResource($invoice->load('items', 'payments')), 201);
    }

    /**
     * POST /api/billing/invoices/{invoice}/pay
     * Body: { amount }
     */
    public function pay(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($validated, $request, $invoice) {
            $invoice->payments()->create([
                'amount_paid' => $validated['amount'],
                'payment_method' => 'cash',
                'received_by' => $request->user()->id,
                'paid_at' => now(),
            ]);

            $totalPaid = $invoice->payments()->sum('amount_paid');

            $invoice->update([
                'status' => $totalPaid >= $invoice->total_amount ? 'paid' : 'partially_paid',
            ]);
        });

        return new InvoiceResource($invoice->fresh(['items', 'payments']));
    }

    /**
     * POST /api/billing/invoices/{invoice}/waive
     */
    public function waive(Invoice $invoice)
    {
        $invoice->update(['status' => 'waived']);

        return new InvoiceResource($invoice->fresh(['items', 'payments']));
    }

    /**
     * GET /api/billing/unpaid-report
     */
    public function unpaidReport()
    {
        $invoices = Invoice::whereIn('status', ['unpaid', 'partially_paid'])->withSum('payments', 'amount_paid')->get();

        return response()->json([
            'count' => $invoices->count(),
            'outstanding_total' => (float) $invoices->sum(fn ($i) => $i->total_amount - ($i->payments_sum_amount_paid ?? 0)),
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');

        do {
            $sequence = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $candidate = "INV-{$year}-{$sequence}";
        } while (Invoice::where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
