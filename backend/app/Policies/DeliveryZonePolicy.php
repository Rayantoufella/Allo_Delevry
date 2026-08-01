<?php

namespace App\Policies;

use App\Models\DeliveryZone;
use App\Models\User;

class DeliveryZonePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DeliveryZone $deliveryZone): bool
    {
        return $deliveryZone->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isDriver();
    }

    public function update(User $user, DeliveryZone $deliveryZone): bool
    {
        return $deliveryZone->user_id === $user->id;
    }

    public function delete(User $user, DeliveryZone $deliveryZone): bool
    {
        return $deliveryZone->user_id === $user->id;
    }
}
