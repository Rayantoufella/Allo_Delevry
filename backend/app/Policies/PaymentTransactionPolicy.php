<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\DeliveryRequest;
use App\Models\User;

class PaymentTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $this->isParticipant($paymentTransaction->deliveryRequest, $user);
    }

    public function create(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $this->isParticipant($deliveryRequest, $user);
    }

    public function update(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $this->isParticipant($paymentTransaction->deliveryRequest, $user);
    }

    public function delete(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $this->isParticipant($paymentTransaction->deliveryRequest, $user);
    }

    private function isParticipant(DeliveryRequest $deliveryRequest, User $user): bool
    {
        return $deliveryRequest->client_id === $user->id
            || $deliveryRequest->driver_id === $user->id;
    }
}
