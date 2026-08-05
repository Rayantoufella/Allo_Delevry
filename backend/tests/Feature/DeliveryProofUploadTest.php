<?php

use App\Models\DeliveryRequest;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function uploadClient(): User
{
    return User::factory()->client()->create();
}

function uploadDriver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'rib' => 'FR76 3000 6000 0112 3456 7890 189',
        'slug' => 'driver-upload-test',
    ]);

    return $driver;
}

it('uploads a delivery proof file', function () {
    Storage::fake('public');

    $client = uploadClient();
    $driver = uploadDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($driver);
    $response = $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'signature',
        'file' => UploadedFile::fake()->image('proof.jpg', 100, 100)->size(100),
        'receiver_name' => 'John Doe',
    ])->assertCreated();

    $this->assertStringStartsWith('proofs/', $response->json('file_path'));
    $this->assertStringContainsString('storage/', $response->json('file_url'));
    Storage::disk('public')->assertExists($response->json('file_path'));
});

it('rejects a non-image file', function () {
    Storage::fake('public');

    $client = uploadClient();
    $driver = uploadDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($driver);
    $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'signature',
        'file' => UploadedFile::fake()->create('doc.pdf', 100),
    ])->assertStatus(422);
});

it('rejects a file larger than 2MB', function () {
    Storage::fake('public');

    $client = uploadClient();
    $driver = uploadDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($driver);
    $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'signature',
        'file' => UploadedFile::fake()->image('big.jpg')->size(3000),
    ])->assertStatus(422);
});

it('does not allow a client to upload a proof', function () {
    Storage::fake('public');

    $client = uploadClient();
    $driver = uploadDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($client);
    $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'signature',
        'file' => UploadedFile::fake()->image('proof.jpg'),
    ])->assertForbidden();
});

it('replaces the old file on update', function () {
    Storage::fake('public');

    $client = uploadClient();
    $driver = uploadDriver();

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($driver);

    $createResponse = $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'signature',
        'file' => UploadedFile::fake()->image('proof.jpg', 100, 100)->size(100),
    ])->assertCreated();

    $oldFilePath = $createResponse->json('file_path');
    $proofId = $createResponse->json('id');
    Storage::disk('public')->assertExists($oldFilePath);

    $this->putJson("/api/delivery-proofs/{$proofId}", [
        'file' => UploadedFile::fake()->image('new_proof.jpg', 100, 100)->size(100),
    ])->assertSuccessful();

    Storage::disk('public')->assertMissing($oldFilePath);
});
