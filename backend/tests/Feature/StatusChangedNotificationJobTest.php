<?php

use App\Jobs\CreateStatusChangedNotificationJob;
use App\Models\DeliveryRequest;
use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function statusDriver(): \App\Models\User
{
    $driver = \App\Models\User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'status-driver-slug',
    ]);

    return $driver;
}

it('dispatches a status change notification job when a driver updates the status', function () {
    Queue::fake();

    $client = \App\Models\User::factory()->client()->create();
    $driver = statusDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create([
            'status' => DeliveryRequest::STATUS_EN_ATTENTE,
        ]);

    Sanctum::actingAs($driver);
    $this->patchJson('/api/delivery-requests/'.$deliveryRequest->id.'/status', [
        'status' => 'prix_propose',
        'proposed_price' => 2500,
    ])->assertOk();

    Queue::assertPushed(CreateStatusChangedNotificationJob::class, fn ($job) => $job->deliveryRequest->is($deliveryRequest) && $job->newStatus === 'prix_propose' && $job->changedBy === $driver->id);
});

it('creates a notification for the client when the driver changes the status', function () {
    Queue::fake();

    $client = \App\Models\User::factory()->client()->create();
    $driver = statusDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create([
            'status' => DeliveryRequest::STATUS_EN_ATTENTE,
        ]);

    $job = new CreateStatusChangedNotificationJob($deliveryRequest, 'prix_propose', $driver->id);
    $job->handle();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $client->id,
        'type' => 'status_changed',
        'delivery_request_id' => $deliveryRequest->id,
    ]);
});

it('creates a notification for the driver when the client confirms the price', function () {
    Queue::fake();

    $client = \App\Models\User::factory()->client()->create();
    $driver = statusDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create([
            'status' => DeliveryRequest::STATUS_PRIX_PROPOSE,
            'proposed_price' => 2500,
        ]);

    $job = new CreateStatusChangedNotificationJob($deliveryRequest, 'confirmee', $client->id);
    $job->handle();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $driver->id,
        'type' => 'status_changed',
        'delivery_request_id' => $deliveryRequest->id,
    ]);
});

it('does nothing when changedBy is null', function () {
    Queue::fake();

    $client = \App\Models\User::factory()->client()->create();
    $driver = statusDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create();

    $job = new CreateStatusChangedNotificationJob($deliveryRequest, 'confirmee', null);
    $job->handle();

    $this->assertDatabaseCount('notifications', 0);
});
