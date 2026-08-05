<?php

use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function flowClient(): User
{
    return User::factory()->client()->create();
}

function flowDriver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'rib' => 'FR76 3000 6000 0112 3456 7890 189',
        'slug' => 'driver-slug-test',
    ]);

    return $driver;
}

it('performs the full RG06 flow with client-side delivery confirmation', function () {
    Queue::fake();

    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create(['status' => DeliveryRequest::STATUS_EN_ATTENTE]);

    Sanctum::actingAs($driver);
    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_PRIX_PROPOSE,
        'proposed_price' => 5000,
    ])->assertSuccessful()
        ->assertJsonPath('data.status', DeliveryRequest::STATUS_PRIX_PROPOSE)
        ->assertJsonPath('data.proposed_price', '5000.00');

    Sanctum::actingAs($client);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-price")
        ->assertSuccessful()
        ->assertJsonPath('data.status', DeliveryRequest::STATUS_CONFIRMEE);

    Sanctum::actingAs($driver);
    $codeResponse = $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/generate-code")
        ->assertSuccessful();
    $code = $codeResponse->json('code');
    expect($code)->toBeString()->toHaveLength(6);

    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_COLIS_RECUPERE,
    ])->assertSuccessful();

    expect($deliveryRequest->refresh()->picked_up_at)->not->toBeNull();

    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_EN_LIVRAISON,
    ])->assertSuccessful();

    Storage::fake('public');
    $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'signature',
        'file' => UploadedFile::fake()->image('signature.jpg'),
    ])->assertCreated();

    Sanctum::actingAs($client);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-delivery", [
        'code' => $code,
    ])->assertSuccessful()
        ->assertJsonPath('data.status', DeliveryRequest::STATUS_LIVREE);

    expect($deliveryRequest->refresh()->delivered_at)->not->toBeNull();
});

it('blocks the driver from confirming the delivery (RG06 is client-side)', function () {
    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($driver);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-delivery", [
        'code' => '123456',
    ])->assertForbidden();
});

it('blocks the client from generating the confirmation code', function () {
    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->confirmed()
        ->create();

    Sanctum::actingAs($client);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/generate-code")
        ->assertForbidden();
});

it('requires proposed_price when moving to prix_propose', function () {
    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create(['status' => DeliveryRequest::STATUS_EN_ATTENTE]);

    Sanctum::actingAs($driver);
    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_PRIX_PROPOSE,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('proposed_price');
});

it('rejects forbidden status transitions', function () {
    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create(['status' => DeliveryRequest::STATUS_EN_ATTENTE]);

    Sanctum::actingAs($driver);
    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_LIVREE,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('only allows the driver to attach a delivery proof', function () {
    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Storage::fake('public');

    Sanctum::actingAs($client);
    $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'signature',
        'file' => UploadedFile::fake()->image('signature.jpg'),
    ])->assertForbidden();

    Sanctum::actingAs($driver);
    $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'signature',
        'file' => UploadedFile::fake()->image('signature.jpg'),
    ])->assertCreated();

    expect(DeliveryProof::count())->toBe(1);
});

it('only allows the driver to record GPS locations', function () {
    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($client);
    $this->postJson('/api/gps-locations', [
        'delivery_request_id' => $deliveryRequest->id,
        'latitude' => 36.7525,
        'longitude' => 3.0420,
    ])->assertForbidden();

    Sanctum::actingAs($driver);
    $this->postJson('/api/gps-locations', [
        'delivery_request_id' => $deliveryRequest->id,
        'latitude' => 36.7525,
        'longitude' => 3.0420,
    ])->assertCreated();
});

it('rejects edits on a request that is already in delivery', function () {
    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($driver);
    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}", [
        'recipient_name' => 'Nouveau nom',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('only allows deleting requests in a terminal status', function () {
    $client = flowClient();
    $driver = flowDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create(['status' => DeliveryRequest::STATUS_EN_ATTENTE]);

    Sanctum::actingAs($client);
    $this->deleteJson("/api/delivery-requests/{$deliveryRequest->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    $deliveryRequest->update(['status' => DeliveryRequest::STATUS_ANNULEE]);
    $this->deleteJson("/api/delivery-requests/{$deliveryRequest->id}")
        ->assertSuccessful();
});

it('accepts reviews only on delivered requests, once, by the client', function () {
    $client = flowClient();
    $driver = flowDriver();

    $pending = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create(['status' => DeliveryRequest::STATUS_EN_ATTENTE]);

    Sanctum::actingAs($client);
    $this->postJson('/api/reviews', [
        'delivery_request_id' => $pending->id,
        'rating' => 5,
    ])->assertStatus(422);

    $delivered = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->delivered()
        ->create();

    $this->postJson('/api/reviews', [
        'delivery_request_id' => $delivered->id,
        'rating' => 5,
    ])->assertCreated();

    $this->postJson('/api/reviews', [
        'delivery_request_id' => $delivered->id,
        'rating' => 3,
    ])->assertStatus(422);

    Sanctum::actingAs($driver);
    $this->postJson('/api/reviews', [
        'delivery_request_id' => $delivered->id,
        'rating' => 4,
    ])->assertForbidden();
});

it('does not expose sensitive driver data on the public profile', function () {
    $driver = flowDriver();

    $this->getJson('/api/drivers/driver-slug-test')
        ->assertSuccessful()
        ->assertJsonMissingPath('rib')
        ->assertJsonMissingPath('user_id')
        ->assertJsonMissingPath('email')
        ->assertJsonMissingPath('phone');
});

it('keeps the status history read-only', function () {
    Sanctum::actingAs(flowClient());

    $this->postJson('/api/request-status-histories', [])->assertStatus(405);
});
