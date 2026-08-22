<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Order;

class OrderPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, Order $order): bool
    {
        return true;
    }

    public function update(Admin $admin, Order $order): bool
    {
        return true;
    }

    public function forceCancel(Admin $admin, Order $order): bool
    {
        return true;
    }

    public function processRefund(Admin $admin, Order $order): bool
    {
        return true;
    }

    public function escalateDispute(Admin $admin, Order $order): bool
    {
        return true;
    }

    public function flagFraud(Admin $admin, Order $order): bool
    {
        return true;
    }
}
