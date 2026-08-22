<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\GiftCard;

class GiftCardPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->hasPermissionTo('gift_cards.view');
    }

    public function view(Admin $admin, GiftCard $giftCard): bool
    {
        return $admin->hasPermissionTo('gift_cards.view');
    }

    public function create(Admin $admin): bool
    {
        return $admin->hasPermissionTo('gift_cards.create');
    }

    public function update(Admin $admin, GiftCard $giftCard): bool
    {
        return $admin->hasPermissionTo('gift_cards.edit');
    }
}
