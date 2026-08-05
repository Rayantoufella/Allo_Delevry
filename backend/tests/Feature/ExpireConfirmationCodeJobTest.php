<?php

use App\Jobs\ExpireConfirmationCodeJob;
use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('expires a still-valid confirmation code', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create([
            'confirmation_code_hash' => bcrypt('123456'),
            'confirmation_code_expires_at' => now()->addMinutes(30),
        ]);

    (new ExpireConfirmationCodeJob($deliveryRequest))->handle();

    $this->assertTrue(
        $deliveryRequest->refresh()->confirmation_code_expires_at->lte(now())
    );
});

it('does not change the code when it is already expired', function () {
    Queue::fake();

    $deliveryRequest = DeliveryRequest::factory()
        ->inDelivery()
        ->create([
            'confirmation_code_hash' => bcrypt('123456'),
            'confirmation_code_expires_at' => now()->subMinutes(5),
        ]);

    $before = $deliveryRequest->refresh()->confirmation_code_expires_at;

    (new ExpireConfirmationCodeJob($deliveryRequest))->handle();

    $after = $deliveryRequest->refresh()->confirmation_code_expires_at;

    $this->assertTrue($before->equalTo($after));
});

it('does not change the code when none has been generated', function () {
    Queue::fake();

    $deliveryRequest = DeliveryRequest::factory()
        ->inDelivery()
        ->create([
            'confirmation_code_hash' => null,
            'confirmation_code_expires_at' => null,
        ]);

    (new ExpireConfirmationCodeJob($deliveryRequest))->handle();

    $this->assertDatabaseHas('delivery_requests', [
        'id' => $deliveryRequest->id,
        'confirmation_code_hash' => null,
        'confirmation_code_expires_at' => null,
    ]);
});

it('dispatches the expiration job with a delay when a code is generated', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->inDelivery()
        ->create();

    Sanctum::actingAs($driver);
    $this->postJson('/api/delivery-requests/'.$deliveryRequest->id.'/generate-code')
        ->assertStatus(200)
        ->assertJsonStructure(['code']);

    Queue::assertPushed(ExpireConfirmationCodeJob::class, fn ($job) => $job->delay !== null);
});
