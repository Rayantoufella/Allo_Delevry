<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_request_id' => $this->delivery_request_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender?->name,
            'message_type' => $this->message_type,
            'content' => $this->content,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
