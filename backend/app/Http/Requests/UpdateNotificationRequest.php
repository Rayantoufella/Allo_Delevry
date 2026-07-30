<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'delivery_request_id' => ['sometimes', 'nullable', 'exists:delivery_requests,id'],
            'read_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
