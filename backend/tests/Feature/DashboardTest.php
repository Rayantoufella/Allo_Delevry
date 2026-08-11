<?php

use App\Models\ChatMessage;
use App\Models\DeliveryRequest;
use App\Models\DriverProfile;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Crée un livreur avec son profil professionnel.
 */
function dashboardDriver(): User
{
    $driver = User::factory()->driver()->create();

    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'dashboard-driver-slug',
    ]);

    return $driver;
}

/**
 * Crée une demande de livraison pour le livreur.
 */
function dashboardMission(User $driver, string $status, ?float $price = null): DeliveryRequest
{
    return DeliveryRequest::factory()->forDriver($driver)->create([
        'status' => $status,
        'proposed_price' => $price,
    ]);
}

it('returns the driver dashboard indicators', function () {
    $driver = dashboardDriver();

    $delivered = dashboardMission($driver, DeliveryRequest::STATUS_LIVREE, 100.0);
    dashboardMission($driver, DeliveryRequest::STATUS_LIVREE, 50.0);
    $confirmed = dashboardMission($driver, DeliveryRequest::STATUS_CONFIRMEE, 30.0);
    dashboardMission($driver, DeliveryRequest::STATUS_COLIS_RECUPERE, 20.0);
    dashboardMission($driver, DeliveryRequest::STATUS_EN_ATTENTE);
    dashboardMission($driver, DeliveryRequest::STATUS_REFUSEE);

    // 2 notifications non lues, 1 déjà lue.
    Notification::factory()->count(2)->create([
        'user_id' => $driver->id,
        'delivery_request_id' => $delivered->id,
        'read_at' => null,
    ]);
    Notification::factory()->create([
        'user_id' => $driver->id,
        'delivery_request_id' => $delivered->id,
        'read_at' => now(),
    ]);

    // 1 message sur une mission du livreur.
    ChatMessage::factory()->create([
        'delivery_request_id' => $confirmed->id,
        'sender_id' => $driver->id,
        'content' => 'Bonjour',
    ]);

    Sanctum::actingAs($driver);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.total_requests', 6)
        ->assertJsonPath('data.active_missions', 3)
        ->assertJsonPath('data.pending_requests', 1)
        ->assertJsonPath('data.delivered_missions', 2)
        ->assertJsonPath('data.estimated_revenue', '200.00')
        ->assertJsonPath('data.collected_revenue', '150.00')
        ->assertJsonPath('data.unread_notifications', 2)
        ->assertJsonCount(5, 'data.recent_requests')
        ->assertJsonPath('data.recent_messages.0.content', 'Bonjour');
});

it('forbids clients from accessing the driver dashboard', function () {
    $client = User::factory()->client()->create();

    Sanctum::actingAs($client);

    $this->getJson('/api/dashboard')->assertForbidden();
});

it('requires authentication to access the driver dashboard', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
});
