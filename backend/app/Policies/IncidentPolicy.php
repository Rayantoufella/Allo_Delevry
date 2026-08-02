<?php

namespace App\Policies;

use App\Models\DeliveryRequest;
use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        return $this->isParticipant($incident->deliveryRequest, $user);
    }

    public function create(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $this->isParticipant($deliveryRequest, $user);
    }

    public function update(User $user, Incident $incident): bool
    {
        return $this->isParticipant($incident->deliveryRequest, $user);
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $this->isParticipant($incident->deliveryRequest, $user);
    }

    private function isParticipant(DeliveryRequest $deliveryRequest, User $user): bool
    {
        return $deliveryRequest->client_id === $user->id
            || $deliveryRequest->driver_id === $user->id;
    }
}
