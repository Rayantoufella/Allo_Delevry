<?php

use App\Models\AiRequestDraft;
use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\DriverProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function driverButtonsDriver(string $slug = 'driver-buttons-driver-slug'): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => $slug,
        'rib' => 'FR76 3000 6000 0112 3456 7890 189',
    ]);

    return $driver;
}

function driverButtonsPayload(int $serviceId, int $deliveryZoneId, int $draftId): array
{
    return [
        'service_id' => $serviceId,
        'delivery_zone_id' => $deliveryZoneId,
        'ai_request_draft_id' => $draftId,
        'recipient_name' => 'Alice Martin',
        'recipient_phone' => '+33 6 22 33 44 55',
        'pickup_address' => '3 Rue du Port, 13001 Marseille',
        'delivery_address' => '18 Rue Lafayette, 75009 Paris',
        'package_description' => 'Carton 5kg',
        'product_amount' => 299.99,
        'amount_to_collect' => 299.99,
    ];
}

/** Crée une demande via le client et la retourne. */
function driverButtonsCreateRequest(TestCase $test, User $driver, User $client): DeliveryRequest
{
    $service = Service::factory()->create(['user_id' => $driver->id]);
    $deliveryZone = DeliveryZone::factory()->create(['user_id' => $driver->id]);
    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'status' => AiRequestDraft::STATUS_DONE,
    ]);

    Sanctum::actingAs($client);

    $created = $test->postJson(
        '/api/drivers/driver-buttons-driver-slug/delivery-requests',
        driverButtonsPayload($service->id, $deliveryZone->id, $draft->id)
    )->assertCreated();

    return DeliveryRequest::findOrFail($created->json('id'));
}

/** Amène une demande jusqu'a "en_livraison" (parcours livreur standard, photos incluses). */
function driverButtonsDriveToDelivery(TestCase $test, DeliveryRequest $deliveryRequest): void
{
    Sanctum::actingAs($deliveryRequest->driver);

    $test->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_CONFIRMEE,
    ])->assertSuccessful();

    $test->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => DeliveryProof::TYPE_PICKUP_PHOTO,
        'file' => UploadedFile::fake()->image('pickup.jpg'),
    ])->assertCreated();

    $test->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_COLIS_RECUPERE,
    ])->assertSuccessful();

    $test->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_EN_LIVRAISON,
    ])->assertSuccessful();

    $test->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => 'photo',
        'file' => UploadedFile::fake()->image('proof.jpg'),
    ])->assertCreated();
}

it('runs the driver flow from en_livraison to livree: arrival and handover by the driver', function () {
    Queue::fake();
    Storage::fake('public');

    $driver = driverButtonsDriver();
    $client = User::factory()->clientOf($driver)->create();
    $deliveryRequest = driverButtonsCreateRequest($this, $driver, $client);

    driverButtonsDriveToDelivery($this, $deliveryRequest);

    // 1. Le livreur confirme son arrivee (tous les boutons sont chez le livreur).
    Sanctum::actingAs($driver);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-arrival")
        ->assertSuccessful()
        ->assertJsonPath('data.status', DeliveryRequest::STATUS_LIVREUR_ARRIVE);

    expect($deliveryRequest->refresh()->status)->toBe(DeliveryRequest::STATUS_LIVREUR_ARRIVE);
    expect($deliveryRequest->picked_up_at)->not->toBeNull();
    expect($deliveryRequest->delivered_at)->toBeNull();

    // 2. Le livreur confirme la remise (la commande est terminee).
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-handover")
        ->assertSuccessful()
        ->assertJsonPath('data.status', DeliveryRequest::STATUS_LIVREE);

    $deliveryRequest->refresh();
    expect($deliveryRequest->status)->toBe(DeliveryRequest::STATUS_LIVREE);
    expect($deliveryRequest->delivered_at)->not->toBeNull();

    $history = $deliveryRequest->statusHistories()
        ->orderBy('id')
        ->get()
        ->pluck('new_status')
        ->all();

    expect($history)->toBe([
        DeliveryRequest::STATUS_CONFIRMEE,
        DeliveryRequest::STATUS_COLIS_RECUPERE,
        DeliveryRequest::STATUS_EN_LIVRAISON,
        DeliveryRequest::STATUS_LIVREUR_ARRIVE,
        DeliveryRequest::STATUS_LIVREE,
    ]);
});

it('rejects confirm-arrival when the request is not en_livraison', function () {
    Queue::fake();

    $driver = driverButtonsDriver();
    $client = User::factory()->clientOf($driver)->create();
    $deliveryRequest = driverButtonsCreateRequest($this, $driver, $client);

    Sanctum::actingAs($driver);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-arrival")
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect($deliveryRequest->refresh()->status)->toBe(DeliveryRequest::STATUS_EN_ATTENTE);
});

it('rejects confirm-handover when the request is not livreur_arrive', function () {
    Queue::fake();
    Storage::fake('public');

    $driver = driverButtonsDriver();
    $client = User::factory()->clientOf($driver)->create();
    $deliveryRequest = driverButtonsCreateRequest($this, $driver, $client);

    driverButtonsDriveToDelivery($this, $deliveryRequest);

    Sanctum::actingAs($driver);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-handover")
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect($deliveryRequest->refresh()->status)->toBe(DeliveryRequest::STATUS_EN_LIVRAISON);
});

it('allows confirm-handover without delivery proof', function () {
    Queue::fake();
    Storage::fake('public');

    $driver = driverButtonsDriver();
    $client = User::factory()->clientOf($driver)->create();
    $deliveryRequest = driverButtonsCreateRequest($this, $driver, $client);

    // Livreur : confirmee -> colis_recupere -> en_livraison, SANS preuve de remise.
    Sanctum::actingAs($driver);
    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_CONFIRMEE,
    ])->assertSuccessful();
    $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $deliveryRequest->id,
        'proof_type' => DeliveryProof::TYPE_PICKUP_PHOTO,
        'file' => UploadedFile::fake()->image('pickup.jpg'),
    ])->assertCreated();
    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_COLIS_RECUPERE,
    ])->assertSuccessful();
    $this->patchJson("/api/delivery-requests/{$deliveryRequest->id}/status", [
        'status' => DeliveryRequest::STATUS_EN_LIVRAISON,
    ])->assertSuccessful();

    // Le livreur confirme son arrivee.
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-arrival")
        ->assertSuccessful();

    // La remise fonctionne sans preuve de livraison.
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-handover")
        ->assertSuccessful();

    expect($deliveryRequest->refresh()->status)->toBe(DeliveryRequest::STATUS_LIVREE);
});

it('forbids the client from confirming the arrival (all buttons are driver-side)', function () {
    Queue::fake();
    Storage::fake('public');

    $driver = driverButtonsDriver();
    $client = User::factory()->clientOf($driver)->create();
    $deliveryRequest = driverButtonsCreateRequest($this, $driver, $client);

    driverButtonsDriveToDelivery($this, $deliveryRequest);

    // Le client proprietaire ne dispose d'aucun bouton de statut.
    Sanctum::actingAs($client);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-arrival")
        ->assertForbidden();

    expect($deliveryRequest->refresh()->status)->toBe(DeliveryRequest::STATUS_EN_LIVRAISON);
});

it('forbids another driver from confirming the arrival', function () {
    Queue::fake();
    Storage::fake('public');

    $driver = driverButtonsDriver();
    $client = User::factory()->clientOf($driver)->create();
    $otherDriver = driverButtonsDriver('driver-buttons-driver-slug-2');
    $deliveryRequest = driverButtonsCreateRequest($this, $driver, $client);

    driverButtonsDriveToDelivery($this, $deliveryRequest);

    Sanctum::actingAs($otherDriver);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-arrival")
        ->assertForbidden();

    expect($deliveryRequest->refresh()->status)->toBe(DeliveryRequest::STATUS_EN_LIVRAISON);
});

it('forbids clients and other drivers from confirming the handover', function () {
    Queue::fake();
    Storage::fake('public');

    $driver = driverButtonsDriver();
    $client = User::factory()->clientOf($driver)->create();
    $otherDriver = driverButtonsDriver('driver-buttons-driver-slug-3');
    $deliveryRequest = driverButtonsCreateRequest($this, $driver, $client);

    driverButtonsDriveToDelivery($this, $deliveryRequest);

    // Le livreur attribue confirme son arrivee.
    Sanctum::actingAs($driver);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-arrival")
        ->assertSuccessful();

    // Le client proprietaire ne peut pas confirmer la remise (reserve au livreur).
    Sanctum::actingAs($client);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-handover")
        ->assertForbidden();

    // Un autre livreur non attribue ne peut pas non plus.
    Sanctum::actingAs($otherDriver);
    $this->postJson("/api/delivery-requests/{$deliveryRequest->id}/confirm-handover")
        ->assertForbidden();

    expect($deliveryRequest->refresh()->status)->toBe(DeliveryRequest::STATUS_LIVREUR_ARRIVE);
});
