<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Client Demo',
                'role' => User::ROLE_CLIENT,
                'password' => 'password',
            ],
        );

        User::firstOrCreate(
            ['email' => 'driver@example.com'],
            [
                'name' => 'Livreur Demo',
                'role' => User::ROLE_DRIVER,
                'password' => 'password',
            ],
        );

        if (User::count() > 2) {
            return;
        }

        User::factory()
            ->count(10)
            ->client()
            ->create();

        User::factory()
            ->count(10)
            ->driver()
            ->create();
    }
}
