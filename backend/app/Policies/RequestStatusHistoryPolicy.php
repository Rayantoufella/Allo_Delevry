<?php

namespace App\Policies;

use App\Models\DeliveryRequest;
use App\Models\RequestStatusHistory;
use App\Models\User;

class RequestStatusHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RequestStatusHistory $requestStatusHistory): bool
    {
        return $this->isParticipant($requestStatusHistory->deliveryRequest, $user);
    }

    public function create(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $this->isParticipant($deliveryRequest, $user);
    }

    public function update(User $user, RequestStatusHistory $requestStatusHistory): bool
    {
        return $this->isParticipant($requestStatusHistory->deliveryRequest, $user);
    }

    public function delete(User $user, RequestStatusHistory $requestStatusHistory): bool
    {
        return $this->isParticipant($requestStatusHistory->deliveryRequest, $user);
    }

    private function isParticipant(DeliveryRequest $deliveryRequest, User $user): bool
    {
        return $deliveryRequest->client_id === $user->id
            || $deliveryRequest->driver_id === $user->id;
    }
}
