<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAiRequestDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'exists:services,id'],
            'input_message' => ['required', 'string'],
            'generated_data' => ['nullable', 'json'],
            'status' => ['nullable', 'in:pending,done,failed'],
            'error_message' => ['nullable', 'string'],
            'validated_at' => ['nullable', 'date'],
        ];
    }
}
