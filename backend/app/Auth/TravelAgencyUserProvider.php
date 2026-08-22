<?php

namespace App\Auth;

use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

/**
 * Authenticates the travel_agency session guard against either the agency
 * owner record (travel_agencies) or a staff record (travel_agency_members),
 * whichever matches the submitted email first.
 */
class TravelAgencyUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $agency = TravelAgency::find($identifier);
        if ($agency && $agency->isActive()) {
            return $agency->ownerMember();
        }

        return TravelAgencyMember::where('id', $identifier)
            ->where('is_active', true)
            ->first();
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials['email'])) {
            return null;
        }

        $agency = TravelAgency::where('email', $credentials['email'])->first();
        if ($agency && $agency->isActive()) {
            return $agency->ownerMember();
        }

        $member = TravelAgencyMember::where('email', $credentials['email'])
            ->where('is_active', true)
            ->first();

        return $member ?: null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return Hash::check($credentials['password'], $user->getAuthPassword());
    }
}
