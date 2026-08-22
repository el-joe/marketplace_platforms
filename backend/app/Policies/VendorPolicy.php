<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Vendor;

class VendorPolicy
{
    /**
     * Admins bypass all policy checks; everything else is denied.
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
    public function view(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function create(mixed $user): bool
    {
        return false;
    }
    public function update(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function delete(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function approve(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function suspend(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function blacklist(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function managePayout(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function manageDocuments(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function issueStrike(mixed $user, Vendor $v): bool
    {
        return false;
    }
    public function assignManager(mixed $user, Vendor $v): bool
    {
        return false;
    }
}
