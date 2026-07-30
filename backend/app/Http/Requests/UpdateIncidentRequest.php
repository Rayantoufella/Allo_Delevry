<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['sometimes', 'exists:delivery_requests,id'],
            'type' => ['sometimes', 'string', 'max:50'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:open,in_progress,resolved'],
        ];
    }
}
