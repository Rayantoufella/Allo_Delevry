<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestStatusHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['sometimes', 'exists:delivery_requests,id'],
            'old_status' => ['sometimes', 'string', 'max:50'],
            'new_status' => ['sometimes', 'string', 'max:50'],
            'comment' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
