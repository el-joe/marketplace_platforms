<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CartCardOffer;

class CartCardOfferPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->hasPermissionTo('cart_card_offers.view');
    }

    public function view(Admin $admin, CartCardOffer $cartCardOffer): bool
    {
        return $admin->hasPermissionTo('cart_card_offers.view');
    }

    public function create(Admin $admin): bool
    {
        return $admin->hasPermissionTo('cart_card_offers.create');
    }

    public function update(Admin $admin, CartCardOffer $cartCardOffer): bool
    {
        return $admin->hasPermissionTo('cart_card_offers.edit');
    }

    public function delete(Admin $admin, CartCardOffer $cartCardOffer): bool
    {
        return $admin->hasPermissionTo('cart_card_offers.delete');
    }
}
