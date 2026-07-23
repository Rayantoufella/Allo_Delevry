<?php

namespace Database\Factories;

use App\Models\GpsLocation;
use App\Models\DeliveryRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class GpsLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_request_id' => DeliveryRequest::factory(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'recorded_at' => fake()->optional()->dateTime(),
        ];
    }
}
