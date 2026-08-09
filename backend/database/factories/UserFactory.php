<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_DRIVER,
            'driver_id' => null,
            'phone' => fake()->optional()->phoneNumber(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Client rattaché à un livreur parent créé automatiquement, pour que les
     * tests existants appelant ->client() continuent de fonctionner sans
     * modification.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_CLIENT,
            'driver_id' => fn () => User::factory()->driver()->create()->id,
        ]);
    }

    /**
     * Client rattaché à un livreur précis, utilisé par les tests
     * d'isolation entre livreurs.
     */
    public function clientOf(User $driver): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_CLIENT,
            'driver_id' => $driver->id,
        ]);
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_DRIVER,
            'driver_id' => null,
        ]);
    }
}
