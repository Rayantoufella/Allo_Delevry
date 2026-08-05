<?php

use App\Jobs\CreateDeliveryRequestNotificationJob;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\DriverProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function notificationDriver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'notification-driver-slug',
    ]);

    return $driver;
}

function notificationPayload(int $serviceId, int $deliveryZoneId): array
{
    return [
        'service_id' => $serviceId,
        'delivery_zone_id' => $deliveryZoneId,
        'recipient_name' => 'Jean Dupont',
        'recipient_phone' => '+33 6 12 34 56 78',
        'pickup_address' => '12 Rue de la Paix, 75002 Paris',
        'delivery_address' => '8 Avenue des Champs-Élysées, 75008 Paris',
        'package_description' => 'Colis fragile',
        'product_amount' => 150.00,
        'amount_to_collect' => 150.00,
    ];
}

it('dispatches the notification job when a client creates a request', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = notificationDriver();
    $service = Service::factory()->create(['user_id' => $driver->id]);
    $deliveryZone = DeliveryZone::factory()->create(['user_id' => $driver->id]);

    Sanctum::actingAs($client);
    $this->postJson('/api/drivers/notification-driver-slug/delivery-requests', notificationPayload($service->id, $deliveryZone->id))
        ->assertStatus(201);

    Queue::assertPushed(CreateDeliveryRequestNotificationJob::class);
});

it('does not dispatch the job on invalid payload', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    notificationDriver();

    Sanctum::actingAs($client);
    $this->postJson('/api/drivers/notification-driver-slug/delivery-requests', [
        'recipient_name' => '',
    ])->assertStatus(422);

    Queue::assertNotPushed(CreateDeliveryRequestNotificationJob::class);
});

it('creates the notification when the job runs', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = notificationDriver();
    $service = Service::factory()->create(['user_id' => $driver->id]);
    $deliveryZone = DeliveryZone::factory()->create(['user_id' => $driver->id]);

    Sanctum::actingAs($client);
    $response = $this->postJson('/api/drivers/notification-driver-slug/delivery-requests', notificationPayload($service->id, $deliveryZone->id))
        ->assertStatus(201);

    $deliveryRequest = DeliveryRequest::findOrFail($response->json('id'));

    (new CreateDeliveryRequestNotificationJob($deliveryRequest))->handle();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $driver->id,
        'delivery_request_id' => $deliveryRequest->id,
        'type' => 'delivery_request_created',
    ]);
});
