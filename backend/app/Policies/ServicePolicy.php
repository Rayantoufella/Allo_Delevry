<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Service $service): bool
    {
        return $service->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isDriver();
    }

    public function update(User $user, Service $service): bool
    {
        return $service->user_id === $user->id;
    }

    public function delete(User $user, Service $service): bool
    {
        return $service->user_id === $user->id;
    }
}
