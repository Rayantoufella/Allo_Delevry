<?php

namespace Database\Seeders;

use App\Models\DeliveryRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Notification::exists()) {
            return;
        }

        $users = User::all();

        if ($users->isEmpty()) {
            $users = User::factory()->count(5)->create();
        }

        $deliveries = DeliveryRequest::all();

        if ($deliveries->isEmpty()) {
            $deliveries = DeliveryRequest::factory()->count(20)->create();
        }

        Notification::factory()
            ->count(40)
            ->recycle([$users, $deliveries])
            ->create();
    }
}
