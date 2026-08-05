<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'brand_name' => $this->brand_name,
            'slug' => $this->slug,
            'logo_path' => $this->logo_path,
            'city' => $this->city,
            'is_available' => $this->is_available,
            'services' => $this->whenLoaded('user.services', fn () => $this->user->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'base_price' => $service->base_price,
                'is_active' => $service->is_active,
            ])),
            'delivery_zones' => $this->whenLoaded('user.deliveryZones', fn () => $this->user->deliveryZones->map(fn ($zone) => [
                'id' => $zone->id,
                'origin_zone' => $zone->origin_zone,
                'destination_zone' => $zone->destination_zone,
                'fixed_price' => $zone->fixed_price,
                'is_active' => $zone->is_active,
            ])),
        ];

        if ($request->user() && $request->user()->id === $this->user_id) {
            $data['user_id'] = $this->user_id;
            $data['rib'] = $this->rib;
        }

        return $data;
    }
}
