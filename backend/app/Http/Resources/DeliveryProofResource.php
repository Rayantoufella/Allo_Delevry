<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_request_id' => $this->delivery_request_id,
            'uploaded_by' => $this->uploaded_by,
            'proof_type' => $this->proof_type,
            'file_path' => $this->file_path,
            'file_url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'receiver_name' => $this->receiver_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
