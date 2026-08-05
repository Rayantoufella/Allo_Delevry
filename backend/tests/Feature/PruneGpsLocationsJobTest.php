<?php

use App\Jobs\PruneGpsLocationsJob;
use App\Models\DeliveryRequest;
use App\Models\GpsLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes GPS locations older than 7 days and keeps recent ones', function () {
    $deliveryRequest = DeliveryRequest::factory()->create();

    $old = GpsLocation::factory()->create([
        'delivery_request_id' => $deliveryRequest->id,
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'created_at' => now()->subDays(10),
    ]);

    $recent = GpsLocation::factory()->create([
        'delivery_request_id' => $deliveryRequest->id,
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'created_at' => now(),
    ]);

    (new PruneGpsLocationsJob())->handle();

    $this->assertDatabaseMissing('gps_locations', ['id' => $old->id]);
    $this->assertDatabaseHas('gps_locations', ['id' => $recent->id]);
});

it('keeps GPS locations recorded exactly at the retention boundary', function () {
    $deliveryRequest = DeliveryRequest::factory()->create();

    $boundary = GpsLocation::factory()->create([
        'delivery_request_id' => $deliveryRequest->id,
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'created_at' => now()->subDays(7),
    ]);

    (new PruneGpsLocationsJob())->handle();

    $this->assertDatabaseHas('gps_locations', ['id' => $boundary->id]);
});
