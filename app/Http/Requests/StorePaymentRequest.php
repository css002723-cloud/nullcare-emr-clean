<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,mobile_money,insurance,bank'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
