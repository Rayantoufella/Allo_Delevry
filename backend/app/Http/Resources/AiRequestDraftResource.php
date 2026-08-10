<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiRequestDraftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $chatHistory = $this->chat_history ?? [];

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'input_message' => $this->input_message,
            'chat_history' => array_map(function (array $message) {
                return [
                    'role' => $message['role'],
                    'content' => $message['content'],
                    'created_at' => $message['created_at'] ?? null,
                ];
            }, $chatHistory),
            'generated_data' => $this->generated_data,
            'service_id' => $this->service_id,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'validated_at' => $this->validated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
