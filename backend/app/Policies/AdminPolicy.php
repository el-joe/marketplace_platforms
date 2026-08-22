<?php

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy
{
    /**
     * Super admins bypass all policy checks.
     */
    public function before(Admin $user, string $ability): bool|null
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(Admin $user): bool
    {
        return $user->hasPermissionTo('admins.view');
    }

    public function view(Admin $user, Admin $model): bool
    {
        return $user->hasPermissionTo('admins.view');
    }

    public function create(Admin $user): bool
    {
        return $user->hasPermissionTo('admins.create');
    }

    public function update(Admin $user, Admin $model): bool
    {
        if (!$user->hasPermissionTo('admins.edit')) {
            return false;
        }

        // Only super_admins can edit other super_admin accounts
        if ($model->hasRole('super_admin') && !$user->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function delete(Admin $user, Admin $model): bool
    {
        if (!$user->hasPermissionTo('admins.delete')) {
            return false;
        }

        // Cannot delete self
        if ($user->id === $model->id) {
            return false;
        }

        // Cannot delete the last super_admin
        if ($model->hasRole('super_admin')) {
            return Admin::role('super_admin', 'admin')->count() > 1;
        }

        return true;
    }

    public function impersonate(Admin $user, Admin $model): bool
    {
        if (!$user->hasPermissionTo('admins.impersonate')) {
            return false;
        }

        // Cannot impersonate self
        if ($user->id === $model->id) {
            return false;
        }

        // Cannot impersonate super_admin (before() already allowed super_admin→super_admin)
        if ($model->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function resetPassword(Admin $user, Admin $model): bool
    {
        return $user->hasPermissionTo('admins.edit');
    }
}
