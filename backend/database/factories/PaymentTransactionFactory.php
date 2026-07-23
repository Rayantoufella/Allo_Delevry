<?php

namespace Database\Factories;

use App\Models\PaymentTransaction;
use App\Models\DeliveryRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_request_id' => DeliveryRequest::factory(),
            'provider' => fake()->randomElement(['stripe', 'paypal']),
            'reference' => fake()->optional()->uuid(),
            'amount' => fake()->optional()->randomFloat(2, 10, 500),
            'currency' => 'MAD',
            'status' => fake()->randomElement(['pending', 'completed', 'failed']),
            'environment' => 'sandbox',
        ];
    }
}
