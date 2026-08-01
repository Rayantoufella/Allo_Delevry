<?php

namespace App\Policies;

use App\Models\GpsLocation;
use App\Models\DeliveryRequest;
use App\Models\User;

class GpsLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GpsLocation $gpsLocation): bool
    {
        return $this->isParticipant($gpsLocation->deliveryRequest, $user);
    }

    public function create(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $this->isParticipant($deliveryRequest, $user);
    }

    public function update(User $user, GpsLocation $gpsLocation): bool
    {
        return $this->isParticipant($gpsLocation->deliveryRequest, $user);
    }

    public function delete(User $user, GpsLocation $gpsLocation): bool
    {
        return $this->isParticipant($gpsLocation->deliveryRequest, $user);
    }

    private function isParticipant(DeliveryRequest $deliveryRequest, User $user): bool
    {
        return $deliveryRequest->client_id === $user->id
            || $deliveryRequest->driver_id === $user->id;
    }
}
