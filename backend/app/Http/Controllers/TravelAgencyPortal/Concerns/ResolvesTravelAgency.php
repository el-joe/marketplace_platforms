<?php

namespace App\Http\Controllers\TravelAgencyPortal\Concerns;

use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use Illuminate\Support\Facades\Auth;

trait ResolvesTravelAgency
{
    /**
     * Get the currently authenticated TravelAgencyMember.
     */
    protected function member(): TravelAgencyMember
    {
        $user = Auth::guard('travel_agency')->user();

        if ($user instanceof TravelAgency) {
            return $user->ownerMember();
        }

        return $user;
    }

    /**
     * Get the travel_agency_id for the authenticated member.
     * Use this to scope ALL data queries.
     */
    protected function agencyId(): string
    {
        return $this->member()->travel_agency_id;
    }

    /**
     * Get the TravelAgency model (for agency-level metadata like name, logo, status).
     */
    protected function agency(): TravelAgency
    {
        return $this->member()->travelAgency;
    }

    /**
     * Check if the current member is the agency owner.
     */
    protected function isOwner(): bool
    {
        return (bool) $this->member()->is_owner;
    }

    /**
     * Abort with 403 if current member is NOT the owner.
     * Used for owner-only actions like managing team, bank accounts, etc.
     */
    protected function requireOwner(): void
    {
        if (!$this->isOwner()) {
            abort(403, __('travel.roles.owner_only_action'));
        }
    }

    /**
     * Scope a query to this agency's data.
     * Usage: $this->agencyScope(TravelPackage::query())->get()
     */
    protected function agencyScope($query)
    {
        return $query->where('travel_agency_id', $this->agencyId());
    }
}
