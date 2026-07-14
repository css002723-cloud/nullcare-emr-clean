<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * POST /api/invoices/{invoice}/payments
     * Records a payment and rolls the invoice status forward based on
     * total collected so far vs total_amount owed.
     */
    public function store(StorePaymentRequest $request, Invoice $invoice)
    {
        $payment = DB::transaction(function () use ($request, $invoice) {
            $data = $request->validated();
            $data['invoice_id'] = $invoice->id;
            $data['received_by'] = $request->user()->id;
            $data['paid_at'] = now();

            $payment = $invoice->payments()->create($data);

            $totalPaid = $invoice->payments()->sum('amount_paid');

            $invoice->update([
                'status' => $totalPaid >= $invoice->total_amount ? 'paid' : 'partially_paid',
            ]);

            return $payment;
        });

        return response()->json($payment, 201);
    }
}
