<?php

namespace App\Policies;

use App\Models\AiRequestDraft;
use App\Models\User;

class AiRequestDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiRequestDraft $aiRequestDraft): bool
    {
        return $aiRequestDraft->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiRequestDraft $aiRequestDraft): bool
    {
        return $aiRequestDraft->user_id === $user->id;
    }

    public function delete(User $user, AiRequestDraft $aiRequestDraft): bool
    {
        return $aiRequestDraft->user_id === $user->id;
    }
}
