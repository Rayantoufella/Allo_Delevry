<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
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
            'service_id' => null,
            'delivery_zone_id' => null,
            'ai_request_draft_id' => null,
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->phoneNumber(),
            'pickup_address' => fake()->address(),
            'delivery_address' => fake()->address(),
            'package_description' => fake()->optional()->paragraph(),
            'product_amount' => fake()->optional()->randomFloat(2, 20, 500),
            'amount_to_collect' => fake()->optional()->randomFloat(2, 10, 300),
            'proposed_price' => fake()->optional()->randomFloat(2, 15, 150),
            'scheduled_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            'picked_up_at' => fake()->optional()->dateTimeBetween('-2 days', 'now'),
            'delivered_at' => fake()->optional()->dateTimeBetween('-1 days', 'now'),
            'status' => DeliveryRequest::STATUS_EN_ATTENTE,
        ];
    }

    public function forClient(User $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }

    public function forDriver(User $driver): static
    {
        return $this->state(fn (array $attributes) => [
            'driver_id' => $driver->id,
        ]);
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
