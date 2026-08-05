<?php

namespace App\Policies;

use App\Models\DeliveryRequest;
use App\Models\User;

class DeliveryRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $this->isParticipant($deliveryRequest, $user);
    }

    public function create(User $user): bool
    {
        return $user->isClient();
    }

    public function update(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $this->isParticipant($deliveryRequest, $user);
    }

    public function delete(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $this->isParticipant($deliveryRequest, $user);
    }

    public function updateStatus(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $deliveryRequest->driver_id === $user->id;
    }

    public function generateCode(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $deliveryRequest->driver_id === $user->id;
    }

    public function confirmDelivery(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $deliveryRequest->client_id === $user->id;
    }

    public function confirmPrice(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $deliveryRequest->client_id === $user->id;
    }

    public function cancel(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $deliveryRequest->client_id === $user->id;
    }

    private function isParticipant(DeliveryRequest $deliveryRequest, User $user): bool
    {
        return $deliveryRequest->client_id === $user->id
            || $deliveryRequest->driver_id === $user->id;
    }
}
