<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['required', 'exists:delivery_requests,id'],
            'proof_type' => ['required', 'string', 'max:50'],
            'file_path' => ['required', 'string', 'max:255'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
