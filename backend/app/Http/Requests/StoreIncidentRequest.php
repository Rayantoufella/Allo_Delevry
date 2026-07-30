<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['required', 'exists:delivery_requests,id'],
            'type' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'status' => ['sometimes', 'string', 'in:open,in_progress,resolved'],
        ];
    }
}
