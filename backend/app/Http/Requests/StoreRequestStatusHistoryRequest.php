<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequestStatusHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['required', 'exists:delivery_requests,id'],
            'old_status' => ['required', 'string', 'max:50'],
            'new_status' => ['required', 'string', 'max:50'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
