<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatMessagesSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (ChatMessage::exists()) {
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

        ChatMessage::factory()
            ->count(40)
            ->recycle([$deliveries, $users])
            ->create();
    }
}
