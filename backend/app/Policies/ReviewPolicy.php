<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Review;

class ReviewPolicy
{
    /**
     * Admins bypass all policy checks.
     * All non-admin users are denied.
     */
    public function before(mixed $user, string $ability): ?bool
    {
        if ($user instanceof Admin) {
            return true;
        }

        return false;
    }

    public function viewAny(mixed $user): bool
    {
        return false;
    }

    public function view(mixed $user, Review $review): bool
    {
        return false;
    }

    public function update(mixed $user, Review $review): bool
    {
        return false;
    }

    public function delete(mixed $user, Review $review): bool
    {
        return false;
    }

    public function moderate(mixed $user, Review $review): bool
    {
        return false;
    }
}
