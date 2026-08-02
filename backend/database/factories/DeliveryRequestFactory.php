<?php

namespace Database\Factories;

use App\Models\AiRequestDraft;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tracking_number' => fake()->unique()->regexify('[A-Z]{3}[0-9]{8}'),
            'private_token' => fake()->unique()->sha256(),
            'client_id' => User::factory()->client(),
            'driver_id' => User::factory()->driver(),
            'service_id' => Service::factory(),
            'delivery_zone_id' => DeliveryZone::factory(),
            'ai_request_draft_id' => AiRequestDraft::factory(),
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->phoneNumber(),
            'pickup_address' => fake()->address(),
            'delivery_address' => fake()->address(),
            'package_description' => fake()->optional()->paragraph(),
            'product_amount' => fake()->optional()->randomFloat(2, 20, 500),
            'amount_to_collect' => fake()->optional()->randomFloat(2, 10, 300),
            'proposed_price' => fake()->optional()->randomFloat(2, 15, 150),
            'confirmation_code_hash' => fake()->optional()->sha256(),
            'scheduled_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            'picked_up_at' => fake()->optional()->dateTimeBetween('-2 days', 'now'),
            'delivered_at' => fake()->optional()->dateTimeBetween('-1 days', 'now'),
            'status' => DeliveryRequest::STATUS_EN_ATTENTE,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliveryRequest::STATUS_CONFIRMEE,
        ]);
    }

    public function inDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliveryRequest::STATUS_EN_LIVRAISON,
            'picked_up_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliveryRequest::STATUS_LIVREE,
            'picked_up_at' => now()->subHours(3),
            'delivered_at' => now(),
        ]);
    }
}
