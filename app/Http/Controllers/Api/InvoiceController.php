<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * GET /api/patients/{patient}/invoices
     */
    public function index(Patient $patient)
    {
        return response()->json($patient->invoices()->with(['items', 'payments'])->latest()->paginate(20));
    }

    /**
     * POST /api/patients/{patient}/invoices
     * Billing officer (or auto-capture, later) builds an invoice from a
     * list of billable services. total_amount is computed server-side —
     * never trust a client-supplied total.
     */
    public function store(StoreInvoiceRequest $request, Patient $patient)
    {
        $data = $request->validated();

        $invoice = DB::transaction(function () use ($data, $request, $patient) {
            $total = collect($data['items'])->sum('amount');

            $invoice = Invoice::create([
                'patient_id' => $patient->id,
                'encounter_id' => $data['encounter_id'] ?? null,
                'total_amount' => $total,
                'status' => 'unpaid',
                'created_by' => $request->user()->id,
            ]);

            $invoice->items()->createMany($data['items']);

            return $invoice;
        });

        return response()->json($invoice->load('items'), 201);
    }

    /**
     * GET /api/invoices/{invoice}
     */
    public function show(Invoice $invoice)
    {
        return response()->json($invoice->load(['items', 'payments', 'patient:id,patient_number,first_name,last_name']));
    }
}
