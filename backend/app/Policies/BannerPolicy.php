<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Banner;
use Illuminate\Auth\Access\HandlesAuthorization;

class BannerPolicy
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
    public function view(mixed $user, Banner $banner): bool
    {
        return false;
    }
    public function create(mixed $user): bool
    {
        return false;
    }
    public function update(mixed $user, Banner $banner): bool
    {
        return false;
    }
    public function delete(mixed $user, Banner $banner): bool
    {
        return false;
    }
    public function duplicate(mixed $user, Banner $banner): bool
    {
        return false;
    }
}
