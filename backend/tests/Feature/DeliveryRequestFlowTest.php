<?php

use App\Jobs\CreateDeliveryRequestNotificationJob;
use App\Models\AiRequestDraft;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\DriverProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function ar41Driver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'ar41-driver-slug',
        'rib' => 'FR76 3000 6000 0112 3456 7890 189',
    ]);

    return $driver;
}

function ar41CreatePayload(int $serviceId, int $deliveryZoneId, ?int $draftId): array
{
    $payload = [
        'service_id' => $serviceId,
        'delivery_zone_id' => $deliveryZoneId,
        'recipient_name' => 'Alice Martin',
        'recipient_phone' => '+33 6 22 33 44 55',
        'pickup_address' => '3 Rue du Port, 13001 Marseille',
        'delivery_address' => '18 Rue Lafayette, 75009 Paris',
        'package_description' => 'Carton 5kg',
        'product_amount' => 299.99,
        'amount_to_collect' => 299.99,
    ];

    if ($draftId !== null) {
        $payload['ai_request_draft_id'] = $draftId;
    }

    return $payload;
}

it('runs the full delivery lifecycle end to end from creation by the client', function () {
    Queue::fake();
    Storage::fake('public');

    $client = User::factory()->client()->create();
    $driver = ar41Driver();
    $service = Service::factory()->create(['user_id' => $driver->id]);
    $deliveryZone = DeliveryZone::factory()->create(['user_id' => $driver->id]);
    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'status' => AiRequestDraft::STATUS_DONE,
    ]);

    // 1. Client creates the request with a draft it owns.
    Sanctum::actingAs($client);
    $created = $this->postJson(
        '/api/drivers/ar41-driver-slug/delivery-requests',
        ar41CreatePayload($service->id, $deliveryZone->id, $draft->id)
    )->assertCreated();

    $created->assertJsonPath('status', DeliveryRequest::STATUS_EN_ATTENTE)
        ->assertJsonPath('client_id', $client->id)
        ->assertJsonPath('ai_request_draft_id', $draft->id);
    $requestId = $created->json('id');

    $deliveryRequest = DeliveryRequest::findOrFail($requestId);
    expect($draft->refresh()->deliveryRequests()->count())->toBe(1);

    // 2. Driver proposes a price.
    Sanctum::actingAs($driver);
    $this->patchJson("/api/delivery-requests/{$requestId}/status", [
        'status' => DeliveryRequest::STATUS_PRIX_PROPOSE,
        'proposed_price' => 45.00,
    ])->assertSuccessful()
        ->assertJsonPath('data.status', DeliveryRequest::STATUS_PRIX_PROPOSE);

    // 3. Client accepts the price.
    Sanctum::actingAs($client);
    $this->postJson("/api/delivery-requests/{$requestId}/confirm-price")
        ->assertSuccessful()
        ->assertJsonPath('data.status', DeliveryRequest::STATUS_CONFIRMEE);

    // 4. Driver generates the confirmation code.
    Sanctum::actingAs($driver);
    $codeResponse = $this->postJson("/api/delivery-requests/{$requestId}/generate-code")
        ->assertSuccessful();
    $code = $codeResponse->json('code');
    expect($code)->toBeString()->toHaveLength(6);
    expect(Hash::check($code, $deliveryRequest->refresh()->confirmation_code_hash))->toBeTrue();

    // 5. Driver picks up the parcel.
    $this->patchJson("/api/delivery-requests/{$requestId}/status", [
        'status' => DeliveryRequest::STATUS_COLIS_RECUPERE,
    ])->assertSuccessful();

    // 6. Driver is on the way.
    $this->patchJson("/api/delivery-requests/{$requestId}/status", [
        'status' => DeliveryRequest::STATUS_EN_LIVRAISON,
    ])->assertSuccessful();

    // 7. Driver uploads a proof of delivery.
    $this->postJson('/api/delivery-proofs', [
        'delivery_request_id' => $requestId,
        'proof_type' => 'photo',
        'file' => UploadedFile::fake()->image('proof.jpg'),
    ])->assertCreated();

    // 8. Client confirms the delivery with the code.
    Sanctum::actingAs($client);
    $this->postJson("/api/delivery-requests/{$requestId}/confirm-delivery", [
        'code' => $code,
    ])->assertSuccessful()
        ->assertJsonPath('data.status', DeliveryRequest::STATUS_LIVREE);

    // 9. Timestamps and history are recorded.
    $deliveryRequest->refresh();
    expect($deliveryRequest->status)->toBe(DeliveryRequest::STATUS_LIVREE);
    expect($deliveryRequest->picked_up_at)->not->toBeNull();
    expect($deliveryRequest->delivered_at)->not->toBeNull();

    $history = $deliveryRequest->statusHistories()
        ->orderBy('id')
        ->get()
        ->pluck('new_status')
        ->all();

    expect($history)->toBe([
        DeliveryRequest::STATUS_PRIX_PROPOSE,
        DeliveryRequest::STATUS_CONFIRMEE,
        DeliveryRequest::STATUS_COLIS_RECUPERE,
        DeliveryRequest::STATUS_EN_LIVRAISON,
        DeliveryRequest::STATUS_LIVREE,
    ]);

    Queue::assertPushed(CreateDeliveryRequestNotificationJob::class);
});

it('rejects a request linked to a draft owned by another client', function () {
    $client = User::factory()->client()->create();
    $otherClient = User::factory()->client()->create();
    $driver = ar41Driver();
    $service = Service::factory()->create(['user_id' => $driver->id]);
    $deliveryZone = DeliveryZone::factory()->create(['user_id' => $driver->id]);
    $foreignDraft = AiRequestDraft::factory()->create([
        'user_id' => $otherClient->id,
        'status' => AiRequestDraft::STATUS_DONE,
    ]);

    Sanctum::actingAs($client);
    $this->postJson(
        '/api/drivers/ar41-driver-slug/delivery-requests',
        ar41CreatePayload($service->id, $deliveryZone->id, $foreignDraft->id)
    )->assertStatus(422)
        ->assertJsonValidationErrors('ai_request_draft_id');

    expect(DeliveryRequest::count())->toBe(0);
});