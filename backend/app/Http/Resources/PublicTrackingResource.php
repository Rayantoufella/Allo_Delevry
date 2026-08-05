<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tracking_number' => $this->tracking_number,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'picked_up_at' => $this->picked_up_at,
            'delivered_at' => $this->delivered_at,
            'pickup_address' => $this->pickup_address,
            'delivery_address' => $this->delivery_address,
            'recipient_name' => $this->recipient_name,
            'timeline' => $this->statusHistories->map(fn ($history) => [
                'old_status' => $history->old_status,
                'new_status' => $history->new_status,
                'comment' => $history->comment,
                'created_at' => $history->created_at,
            ]),
        ];
    }
}
