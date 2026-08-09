<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'client_id' => $this->client_id,
            'driver_id' => $this->driver_id,
            'service_id' => $this->service_id,
            'delivery_zone_id' => $this->delivery_zone_id,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'delivery_zone' => new DeliveryZoneResource($this->whenLoaded('deliveryZone')),
            'ai_request_draft_id' => $this->ai_request_draft_id,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'pickup_address' => $this->pickup_address,
            'delivery_address' => $this->delivery_address,
            'package_description' => $this->package_description,
            'product_amount' => $this->product_amount,
            'amount_to_collect' => $this->amount_to_collect,
            'proposed_price' => $this->proposed_price,
            'scheduled_at' => $this->scheduled_at,
            'picked_up_at' => $this->picked_up_at,
            'delivered_at' => $this->delivered_at,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
