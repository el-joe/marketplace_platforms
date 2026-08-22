<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\PaidAdSlot;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdSlotPolicy
{
    use HandlesAuthorization;

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

    public function view(mixed $user, PaidAdSlot $adSlot): bool
    {
        return false;
    }

    public function create(mixed $user): bool
    {
        return false;
    }

    public function update(mixed $user, PaidAdSlot $adSlot): bool
    {
        return false;
    }

    public function delete(mixed $user, PaidAdSlot $adSlot): bool
    {
        return false;
    }
}
