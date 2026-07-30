<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiRequestDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['sometimes', 'exists:services,id'],
            'input_message' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:pending,done,failed'],
            'error_message' => ['sometimes', 'nullable', 'string'],
            'validated_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
