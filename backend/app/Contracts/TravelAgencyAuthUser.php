<?php

namespace App\Contracts;

interface TravelAgencyAuthUser
{
    public function isOwner(): bool;

    public function getMemberType(): string;

    public function getAuthIdentifier();
}
