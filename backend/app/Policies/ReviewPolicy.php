<?php

namespace App\Policies;

use App\Models\DeliveryRequest;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Review $review): bool
    {
        return $review->user_id === $user->id
            || $this->isParticipant($review->deliveryRequest, $user);
    }

    public function create(User $user, DeliveryRequest $deliveryRequest): bool
    {
        return $deliveryRequest->client_id === $user->id;
    }

    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    private function isParticipant(DeliveryRequest $deliveryRequest, User $user): bool
    {
        return $deliveryRequest->client_id === $user->id
            || $deliveryRequest->driver_id === $user->id;
    }
}
