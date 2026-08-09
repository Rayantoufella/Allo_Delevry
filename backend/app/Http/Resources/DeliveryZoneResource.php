<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'origin_zone' => $this->origin_zone,
            'destination_zone' => $this->destination_zone,
            'fixed_price' => $this->fixed_price,
            'is_active' => $this->is_active,
            'deliveries_count' => $this->deliveries_count ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
