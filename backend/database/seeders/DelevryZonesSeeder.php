<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DelevryZonesSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DeliveryZone::exists()) {
            return;
        }

        $drivers = User::where('role', User::ROLE_DRIVER)->get();

        if ($drivers->isEmpty()) {
            $drivers = User::factory()->count(10)->driver()->create();
        }

        foreach ($drivers as $driver) {
            DeliveryZone::factory()->count(3)->for($driver, 'user')->create();
        }
    }
}
