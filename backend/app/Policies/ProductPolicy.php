<?php

namespace App\Policies;

use App\Models\Admin;

class ProductPolicy
{
    /**
     * Admins can perform all product actions.
     * Non-admin users (vendors, customers) have no access to the admin product panel.
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
    public function view(mixed $user): bool
    {
        return false;
    }
    public function create(mixed $user): bool
    {
        return false;
    }
    public function update(mixed $user): bool
    {
        return false;
    }
    public function delete(mixed $user): bool
    {
        return false;
    }
}
