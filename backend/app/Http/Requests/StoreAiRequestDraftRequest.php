<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiRequestDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'exists:services,id'],
            'input_message' => ['required', 'string'],
            'status' => ['sometimes', 'string', 'in:pending,done,failed'],
            'error_message' => ['nullable', 'string'],
            'validated_at' => ['nullable', 'date'],
        ];
    }
}
