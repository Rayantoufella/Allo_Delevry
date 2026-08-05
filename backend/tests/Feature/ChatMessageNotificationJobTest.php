<?php

use App\Jobs\CreateChatMessageNotificationJob;
use App\Models\ChatMessage;
use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('dispatches the job when the client sends a message', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create();

    Sanctum::actingAs($client);
    $response = $this->postJson('/api/chat-messages', [
        'delivery_request_id' => $deliveryRequest->id,
        'content' => 'Bonjour, où est mon colis ?',
    ])->assertStatus(201);

    $message = ChatMessage::findOrFail($response->json('id'));

    Queue::assertPushed(CreateChatMessageNotificationJob::class, fn ($job) => $job->chatMessage->is($message));
});

it('notifies the driver when the client sends a message', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create();

    $message = ChatMessage::factory()->create([
        'delivery_request_id' => $deliveryRequest->id,
        'sender_id' => $client->id,
        'content' => 'Bonjour, où est mon colis ?',
    ]);

    (new CreateChatMessageNotificationJob($message))->handle();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $driver->id,
        'delivery_request_id' => $deliveryRequest->id,
        'type' => 'chat_message',
        'title' => 'Nouveau message',
    ]);
});

it('notifies the client when the driver sends a message', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create();

    $message = ChatMessage::factory()->create([
        'delivery_request_id' => $deliveryRequest->id,
        'sender_id' => $driver->id,
        'content' => 'Votre colis arrive dans 10 minutes.',
    ]);

    (new CreateChatMessageNotificationJob($message))->handle();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $client->id,
        'delivery_request_id' => $deliveryRequest->id,
        'type' => 'chat_message',
    ]);
});

it('does not notify anyone when the sender is not a participant', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create();

    $stranger = User::factory()->client()->create();
    $message = ChatMessage::factory()->create([
        'delivery_request_id' => $deliveryRequest->id,
        'sender_id' => $stranger->id,
        'content' => 'Message inconnu',
    ]);

    (new CreateChatMessageNotificationJob($message))->handle();

    $this->assertDatabaseCount('notifications', 0);
});
