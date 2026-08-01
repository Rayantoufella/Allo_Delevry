<?php

namespace App\Policies;

use App\Models\DriverProfile;
use App\Models\User;

class DriverProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DriverProfile $driverProfile): bool
    {
        return $driverProfile->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isDriver();
    }

    public function update(User $user, DriverProfile $driverProfile): bool
    {
        return $driverProfile->user_id === $user->id;
    }

    public function delete(User $user, DriverProfile $driverProfile): bool
    {
        return $driverProfile->user_id === $user->id;
    }
}
