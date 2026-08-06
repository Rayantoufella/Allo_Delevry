<?php

use App\Models\DeliveryRequest;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Crée un livreur avec son profil professionnel (marque affichée sur le ticket).
 */
function ticketDriver(): User
{
    $driver = User::factory()->driver()->create();

    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'ticket-driver-slug',
        'brand_name' => 'Allo Express',
    ]);

    return $driver;
}

it('streams the ticket PDF to the driver', function () {
    $driver = ticketDriver();

    $deliveryRequest = DeliveryRequest::factory()->forDriver($driver)->create();

    Sanctum::actingAs($driver);

    $this->getJson('/api/delivery-requests/'.$deliveryRequest->id.'/ticket')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('streams the ticket PDF to the client participant', function () {
    $driver = ticketDriver();
    $client = User::factory()->client()->create();

    $deliveryRequest = DeliveryRequest::factory()
        ->forDriver($driver)
        ->forClient($client)
        ->create();

    Sanctum::actingAs($client);

    $this->getJson('/api/delivery-requests/'.$deliveryRequest->id.'/ticket')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('forbids a non-participant from downloading the ticket', function () {
    $driver = ticketDriver();
    $otherUser = User::factory()->driver()->create();

    $deliveryRequest = DeliveryRequest::factory()->forDriver($driver)->create();

    Sanctum::actingAs($otherUser);

    $this->getJson('/api/delivery-requests/'.$deliveryRequest->id.'/ticket')->assertForbidden();
});

it('requires authentication to download the ticket', function () {
    $this->getJson('/api/delivery-requests/1/ticket')->assertUnauthorized();
});

it('returns 404 for an unknown delivery request', function () {
    $driver = ticketDriver();

    Sanctum::actingAs($driver);

    $this->getJson('/api/delivery-requests/999999/ticket')->assertNotFound();
});