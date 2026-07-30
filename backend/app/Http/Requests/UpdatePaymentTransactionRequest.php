<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['sometimes', 'exists:delivery_requests,id'],
            'provider' => ['sometimes', 'string', 'max:50'],
            'reference' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'status' => ['sometimes', 'string', 'max:50'],
            'environment' => ['sometimes', 'string', 'in:sandbox,production'],
        ];
    }
}
