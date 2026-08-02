<?php

namespace Database\Seeders;

use App\Models\DeliveryRequest;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IncidentsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Incident::exists()) {
            return;
        }

        $deliveries = DeliveryRequest::all();

        if ($deliveries->isEmpty()) {
            $deliveries = DeliveryRequest::factory()->count(20)->create();
        }

        $users = User::all();

        if ($users->isEmpty()) {
            $users = User::factory()->count(5)->create();
        }

        Incident::factory()
            ->count(10)
            ->recycle([$deliveries, $users])
            ->create();
    }
}
