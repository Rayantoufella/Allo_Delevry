<?php

use App\Models\AiRequestDraft;
use App\Models\ChatMessage;
use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use App\Models\DriverProfile;
use App\Models\Incident;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Rattachement du compte client à un livreur précis.
 *
 * Un client s'inscrit toujours dans le contexte d'un livreur (lien public
 * `/drivers/{slug}`), ce qui autorise le même e-mail à créer un compte
 * distinct chez deux livreurs différents. Ces tests couvrent le contrat
 * figé : portes d'entrée `/drivers/{slug}/register|login`, forçage du rôle
 * et du `driver_id` côté serveur, isolation des ressources dérivées
 * (demandes, chat, avis, brouillons IA, preuves, incidents) et le piège des
 * NULL dans l'index unique `(email, driver_key)`.
 */
function scopingDriver(?string $slug = null): User
{
    $driver = User::factory()->driver()->create();

    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => $slug ?? 'driver-'.$driver->id,
    ]);

    return $driver;
}

// --- Inscription / connexion scopées au livreur ---------------------------

it('allows the same email to register with two different drivers', function () {
    $driverA = scopingDriver('driver-a');
    $driverB = scopingDriver('driver-b');

    $payload = fn () => [
        'name' => 'Client Partagé',
        'email' => 'client@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->postJson('/api/drivers/driver-a/register', $payload())->assertCreated();
    $this->postJson('/api/drivers/driver-b/register', $payload())->assertCreated();

    expect(User::where('email', 'client@example.com')->count())->toBe(2);

    $clientA = User::where('email', 'client@example.com')->where('driver_id', $driverA->id)->first();
    $clientB = User::where('email', 'client@example.com')->where('driver_id', $driverB->id)->first();

    expect($clientA)->not->toBeNull();
    expect($clientB)->not->toBeNull();
    expect($clientA->id)->not->toBe($clientB->id);
    expect($clientA->role)->toBe(User::ROLE_CLIENT);
    expect($clientB->role)->toBe(User::ROLE_CLIENT);
});

it('rejects a duplicate email for the same driver', function () {
    $driver = scopingDriver('driver-unique');

    $payload = [
        'name' => 'Client',
        'email' => 'duplicate@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->postJson('/api/drivers/driver-unique/register', $payload)->assertCreated();
    $this->postJson('/api/drivers/driver-unique/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('rejects a duplicate email for two drivers created without a client (driver_key = 0 fallback)', function () {
    // Deux comptes livreurs (driver_id NULL) ne peuvent pas partager le même
    // e-mail : la colonne générée driver_key = COALESCE(driver_id, 0) rend les
    // deux NULL équivalents à 0, donc l'unique (email, driver_key) s'applique.
    User::factory()->driver()->create(['email' => 'driver-dup@example.com']);

    $this->postJson('/api/register', [
        'name' => 'Autre Livreur',
        'email' => 'driver-dup@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('prevents a client of driver A from logging in through driver B\'s login route', function () {
    $driverA = scopingDriver('driver-a-login');
    $driverB = scopingDriver('driver-b-login');

    $client = User::factory()->clientOf($driverA)->create([
        'email' => 'client-a@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/drivers/driver-b-login/login', [
        'email' => 'client-a@example.com',
        'password' => 'password',
    ])->assertStatus(401);

    $this->postJson('/api/drivers/driver-a-login/login', [
        'email' => 'client-a@example.com',
        'password' => 'password',
    ])->assertSuccessful()->assertJsonPath('data.user.id', $client->id);
});

it('forces the client role on driver-scoped registration regardless of the payload', function () {
    $driver = scopingDriver('driver-role-force');

    $this->postJson('/api/drivers/driver-role-force/register', [
        'name' => 'Tentative',
        'email' => 'role-force@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => User::ROLE_DRIVER,
    ])->assertCreated();

    $created = User::where('email', 'role-force@example.com')->first();
    expect($created->role)->toBe(User::ROLE_CLIENT);
    expect($created->driver_id)->toBe($driver->id);
});

it('ignores an arbitrary driver_id supplied in the registration payload', function () {
    $driver = scopingDriver('driver-real');
    $otherDriver = scopingDriver('driver-other');

    $this->postJson('/api/drivers/driver-real/register', [
        'name' => 'Tentative Rattachement',
        'email' => 'no-hijack@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'driver_id' => $otherDriver->id,
    ])->assertCreated();

    $created = User::where('email', 'no-hijack@example.com')->first();
    expect($created->driver_id)->toBe($driver->id);
    expect($created->driver_id)->not->toBe($otherDriver->id);
});

it('ignores a client role sent to the driver-only registration endpoint', function () {
    // Le champ "role" n'est même pas dans les règles de validation de /register :
    // il est silencieusement ignoré, le rôle "driver" est forcé côté contrôleur.
    $this->postJson('/api/register', [
        'name' => 'Tentative Client',
        'email' => 'client-attempt@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => User::ROLE_CLIENT,
    ])->assertCreated();

    $created = User::where('email', 'client-attempt@example.com')->first();
    expect($created->role)->toBe(User::ROLE_DRIVER);
});

it('always creates a driver via the top-level registration route, even without a role', function () {
    $this->postJson('/api/register', [
        'name' => 'Nouveau Livreur',
        'email' => 'driver-attempt@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated();

    $created = User::where('email', 'driver-attempt@example.com')->first();
    expect($created->role)->toBe(User::ROLE_DRIVER);
    expect($created->driver_id)->toBeNull();
});

// --- /me expose le contexte livreur ---------------------------------------

it('exposes the driver context on /me for a client', function () {
    $driver = scopingDriver('driver-me');
    $client = User::factory()->clientOf($driver)->create();

    Sanctum::actingAs($client);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.driver.slug', 'driver-me')
        ->assertJsonPath('data.driver.brand_name', $driver->driverProfile->brand_name);
});

it('returns a null driver context on /me for a driver', function () {
    $driver = scopingDriver('driver-me-null');

    Sanctum::actingAs($driver);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.driver', null);
});

// --- Isolation des demandes de livraison -----------------------------------

it('only returns delivery requests scoped to the client\'s own driver', function () {
    $driverA = scopingDriver('driver-a-scope');
    $driverB = scopingDriver('driver-b-scope');

    $clientA = User::factory()->clientOf($driverA)->create();
    $clientB = User::factory()->clientOf($driverB)->create();

    $requestA = DeliveryRequest::factory()->forClient($clientA)->forDriver($driverA)->create();
    DeliveryRequest::factory()->forClient($clientB)->forDriver($driverB)->create();

    Sanctum::actingAs($clientA);

    $response = $this->getJson('/api/delivery-requests')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    $requestBId = DeliveryRequest::where('client_id', $clientB->id)->value('id');

    expect($ids)->toContain($requestA->id);
    expect($ids)->not->toContain($requestBId);
    expect($ids)->toHaveCount(1);
});

it('forbids a client from creating a delivery request through another driver\'s slug', function () {
    $driverA = scopingDriver('driver-a-forbid');
    $driverB = scopingDriver('driver-b-forbid');

    $clientA = User::factory()->clientOf($driverA)->create();

    Sanctum::actingAs($clientA);

    $this->postJson('/api/drivers/driver-b-forbid/delivery-requests', [
        'recipient_name' => 'Destinataire',
        'recipient_phone' => '0600000000',
        'pickup_address' => 'Adresse A',
        'delivery_address' => 'Adresse B',
    ])->assertForbidden();

    expect(DeliveryRequest::count())->toBe(0);
});

it('allows a client to create a delivery request through their own driver\'s slug', function () {
    $driver = scopingDriver('driver-own-slug');
    $client = User::factory()->clientOf($driver)->create();

    Sanctum::actingAs($client);

    $this->postJson('/api/drivers/driver-own-slug/delivery-requests', [
        'recipient_name' => 'Destinataire',
        'recipient_phone' => '0600000000',
        'pickup_address' => 'Adresse A',
        'delivery_address' => 'Adresse B',
    ])->assertCreated();

    $created = DeliveryRequest::first();
    expect($created->client_id)->toBe($client->id);
    expect($created->driver_id)->toBe($driver->id);
});

// --- Isolation des ressources dérivées --------------------------------------

it('prevents a client from viewing chat messages of another driver\'s delivery request', function () {
    $driverA = scopingDriver('driver-a-chat');
    $driverB = scopingDriver('driver-b-chat');

    $clientA = User::factory()->clientOf($driverA)->create();
    $clientB = User::factory()->clientOf($driverB)->create();

    $requestB = DeliveryRequest::factory()->forClient($clientB)->forDriver($driverB)->create();
    ChatMessage::factory()->create([
        'delivery_request_id' => $requestB->id,
        'sender_id' => $clientB->id,
    ]);

    Sanctum::actingAs($clientA);

    $this->getJson("/api/chat-messages?delivery_request_id={$requestB->id}")
        ->assertForbidden();
});

it('prevents a client from reviewing a delivery request of another driver', function () {
    $driverA = scopingDriver('driver-a-review');
    $driverB = scopingDriver('driver-b-review');

    $clientA = User::factory()->clientOf($driverA)->create();
    $clientB = User::factory()->clientOf($driverB)->create();

    $requestB = DeliveryRequest::factory()->forClient($clientB)->forDriver($driverB)->delivered()->create();

    Sanctum::actingAs($clientA);

    $this->postJson('/api/reviews', [
        'delivery_request_id' => $requestB->id,
        'rating' => 5,
    ])->assertForbidden();

    expect(Review::count())->toBe(0);
});

it('prevents a client from referencing an AI draft owned by a client of another driver', function () {
    $driverA = scopingDriver('driver-a-draft');
    $driverB = scopingDriver('driver-b-draft');

    $clientA = User::factory()->clientOf($driverA)->create();
    $clientB = User::factory()->clientOf($driverB)->create();

    $draftB = AiRequestDraft::factory()->create(['user_id' => $clientB->id]);

    Sanctum::actingAs($clientA);

    $this->postJson("/api/drivers/driver-a-draft/delivery-requests", [
        'recipient_name' => 'Destinataire',
        'recipient_phone' => '0600000000',
        'pickup_address' => 'Adresse A',
        'delivery_address' => 'Adresse B',
        'ai_request_draft_id' => $draftB->id,
    ])->assertStatus(422)->assertJsonValidationErrors('ai_request_draft_id');
});

it('prevents a client from viewing delivery proofs of another driver\'s delivery request', function () {
    $driverA = scopingDriver('driver-a-proof');
    $driverB = scopingDriver('driver-b-proof');

    $clientA = User::factory()->clientOf($driverA)->create();
    $clientB = User::factory()->clientOf($driverB)->create();

    $requestB = DeliveryRequest::factory()->forClient($clientB)->forDriver($driverB)->create();
    $proofB = DeliveryProof::factory()->create([
        'delivery_request_id' => $requestB->id,
        'uploaded_by' => $driverB->id,
    ]);

    Sanctum::actingAs($clientA);

    $this->getJson("/api/delivery-proofs/{$proofB->id}")->assertForbidden();
});

it('prevents a client from raising an incident on another driver\'s delivery request', function () {
    $driverA = scopingDriver('driver-a-incident');
    $driverB = scopingDriver('driver-b-incident');

    $clientA = User::factory()->clientOf($driverA)->create();
    $clientB = User::factory()->clientOf($driverB)->create();

    $requestB = DeliveryRequest::factory()->forClient($clientB)->forDriver($driverB)->create();

    Sanctum::actingAs($clientA);

    $this->postJson('/api/incidents', [
        'delivery_request_id' => $requestB->id,
        'type' => 'colis_endommage',
        'description' => 'Colis abîmé',
    ])->assertForbidden();

    expect(Incident::count())->toBe(0);
});
