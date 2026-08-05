<?php

namespace App\Policies;

use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use App\Models\User;

class DeliveryProofPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DeliveryProof $deliveryProof): bool
    {
        return $this->isParticipant($deliveryProof->deliveryRequest, $user);
    }

    public function create(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $deliveryRequest->driver_id === $user->id;
    }

    public function update(User $user, DeliveryProof $deliveryProof): bool
    {
        return $this->isParticipant($deliveryProof->deliveryRequest, $user);
    }

    public function delete(User $user, DeliveryProof $deliveryProof): bool
    {
        return $this->isParticipant($deliveryProof->deliveryRequest, $user);
    }

    private function isParticipant(DeliveryRequest $deliveryRequest, User $user): bool
    {
        return $deliveryRequest->client_id === $user->id
            || $deliveryRequest->driver_id === $user->id;
    }
}
