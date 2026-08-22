<?php

namespace App\Policies;

use App\Enums\AdCampaignStatus;
use App\Models\Admin;
use App\Models\AdCampaign;
use App\Models\VendorAdmin;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdCampaignPolicy
{
    use HandlesAuthorization;

    public function before(mixed $user, string $ability): bool|null
    {
        if ($user instanceof Admin) {
            return true;
        }

        // VendorAdmin: fall through to per-method checks
        if ($user instanceof VendorAdmin) {
            return null;
        }

        return false;
    }

    public function viewAny(mixed $user): bool
    {
        return $user instanceof VendorAdmin;
    }

    public function view(mixed $user, AdCampaign $campaign): bool
    {
        return $user instanceof VendorAdmin && $campaign->vendor_id === $user->vendor_id;
    }

    public function create(mixed $user): bool
    {
        return $user instanceof VendorAdmin;
    }

    public function update(mixed $user, AdCampaign $campaign): bool
    {
        return $user instanceof VendorAdmin
            && $campaign->vendor_id === $user->vendor_id
            && in_array($campaign->status, [AdCampaignStatus::Draft, AdCampaignStatus::PendingReview, AdCampaignStatus::Paused, AdCampaignStatus::Rejected]);
    }

    public function delete(mixed $user, AdCampaign $campaign): bool
    {
        return $user instanceof VendorAdmin
            && $campaign->vendor_id === $user->vendor_id
            && in_array($campaign->status, [AdCampaignStatus::Draft, AdCampaignStatus::PendingReview, AdCampaignStatus::Rejected]);
    }

    public function pause(mixed $user, AdCampaign $campaign): bool
    {
        return $user instanceof VendorAdmin
            && $campaign->vendor_id === $user->vendor_id
            && $campaign->status === AdCampaignStatus::Active
            && ($campaign->ends_at === null || $campaign->ends_at->isFuture());
    }

    public function resume(mixed $user, AdCampaign $campaign): bool
    {
        return $user instanceof VendorAdmin
            && $campaign->vendor_id === $user->vendor_id
            && $campaign->status === AdCampaignStatus::Paused
            && ($campaign->ends_at === null || $campaign->ends_at->isFuture());
    }

    public function approve(mixed $user, AdCampaign $campaign): bool
    {
        return false; // Admin only via before()
    }

    public function reject(mixed $user, AdCampaign $campaign): bool
    {
        return false; // Admin only via before()
    }
}
