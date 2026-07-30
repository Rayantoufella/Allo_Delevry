<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['sometimes', 'exists:delivery_requests,id'],
            'proof_type' => ['sometimes', 'string', 'max:50'],
            'file_path' => ['sometimes', 'string', 'max:255'],
            'receiver_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
