<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['required', 'exists:delivery_requests,id'],
            'message_type' => ['required', 'string', 'in:text,image,system'],
            'content' => ['required', 'string'],
            'is_read' => ['boolean'],
        ];
    }
}
