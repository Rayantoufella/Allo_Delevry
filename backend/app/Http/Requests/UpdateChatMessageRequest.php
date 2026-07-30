<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_request_id' => ['sometimes', 'exists:delivery_requests,id'],
            'message_type' => ['sometimes', 'string', 'in:text,image,system'],
            'content' => ['sometimes', 'string'],
            'is_read' => ['sometimes', 'boolean'],
        ];
    }
}
