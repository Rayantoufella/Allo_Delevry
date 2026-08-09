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
            'description' => $this->description,
            'is_available' => $this->is_available,
        ];

        // Relations pointées : `whenLoaded('user.services')` ne résout pas les
        // clés en pointillé dans `relationLoaded()` — on teste `user`, puis la
        // relation elle-même sur le user chargé.
        if ($this->relationLoaded('user')) {
            if ($this->user->relationLoaded('services')) {
                $data['services'] = $this->user->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'base_price' => $service->base_price,
                    'is_active' => $service->is_active,
                ]);
            }

            if ($this->user->relationLoaded('deliveryZones')) {
                $data['delivery_zones'] = $this->user->deliveryZones->map(fn ($zone) => [
                    'id' => $zone->id,
                    'origin_zone' => $zone->origin_zone,
                    'destination_zone' => $zone->destination_zone,
                    'fixed_price' => $zone->fixed_price,
                    'is_active' => $zone->is_active,
                    'deliveries_count' => $zone->deliveries_count ?? 0,
                ]);
            }
        }

        if ($request->user() && $request->user()->id === $this->user_id) {
            $data['user_id'] = $this->user_id;
            $data['rib'] = $this->rib;
            $data['phone'] = $this->user->phone;
        }

        return $data;
    }
}
