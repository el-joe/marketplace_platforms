<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    /**
     * Admins bypass all policy checks.
     */
    public function before(mixed $user, string $ability): bool|null
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

    public function view(mixed $user, Customer $customer): bool
    {
        return false;
    }

    public function update(mixed $user, Customer $customer): bool
    {
        return false;
    }

    public function suspend(mixed $user, Customer $customer): bool
    {
        return false;
    }

    public function ban(mixed $user, Customer $customer): bool
    {
        return false;
    }

    public function reactivate(mixed $user, Customer $customer): bool
    {
        return false;
    }

    public function adjustLoyaltyPoints(mixed $user, Customer $customer): bool
    {
        return false;
    }

    public function exportData(mixed $user, Customer $customer): bool
    {
        return false;
    }
}
