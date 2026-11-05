<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Auth\CredentialDeliveryResult;
use App\Services\Auth\CredentialDeliveryService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * The store owner's login (a VendorAdmin) and the emails that give them access.
 */
class VendorOwnerService
{
    public function __construct(private readonly CredentialDeliveryService $credentials) {}

    /**
     * Return the vendor's owner login, marking the registration account as the owner
     * (registration creates it without `is_owner`). A second account is created only when
     * the vendor has none at all, and never when the email already belongs to another seller.
     *
     * @throws OwnerEmailConflictException
     */
    public function ensureOwner(Vendor $vendor): VendorAdmin
    {
        $accounts = VendorAdmin::withTrashed()->where('vendor_id', $vendor->id)->orderBy('created_at')->get();

        $owner = $accounts->firstWhere('is_owner', true)
            ?? $accounts->first(fn (VendorAdmin $admin) => strcasecmp($admin->email, (string) $vendor->email) === 0)
            ?? $accounts->first(fn (VendorAdmin $admin) => in_array($admin->role, ['owner', 'vendor_owner'], true));

        if ($owner === null) {
            if (VendorAdmin::withTrashed()->where('email', $vendor->email)->exists()) {
                throw new OwnerEmailConflictException((string) $vendor->email);
            }

            $owner = VendorAdmin::create([
                'vendor_id' => $vendor->id,
                'name' => $vendor->business_name ?? $vendor->store_name ?? $vendor->name,
                'email' => $vendor->email,
                // Unusable: the owner chooses a password through the emailed link.
                'password' => Hash::make(Str::password(32)),
                'role' => 'vendor_owner',
                'is_owner' => true,
                'is_active' => true,
            ]);
        }

        if ($owner->trashed()) {
            $owner->restore();
        }

        if (! $owner->is_owner || $owner->role !== 'vendor_owner') {
            $owner->forceFill(['is_owner' => true, 'role' => 'vendor_owner'])->save();
        }

        if (Role::where('name', 'vendor_owner')->where('guard_name', 'vendor')->exists() && ! $owner->hasRole('vendor_owner')) {
            $owner->assignRole('vendor_owner');
        }

        return $owner;
    }

    /**
     * Approval email: tells the owner the store is live, where to log in, and gives a
     * time-limited link to set or reset the password. Never contains a password.
     */
    public function sendApprovalEmail(Vendor $vendor, VendorAdmin $owner): CredentialDeliveryResult
    {
        return $this->credentials->sendSetPasswordLink(
            $owner,
            'vendor_admins',
            'vendor_approved',
            'vendor_approved',
            ['store' => (string) ($vendor->store_name ?? $vendor->business_name ?? $vendor->name)],
        );
    }

    /**
     * Admin "Resend credentials" for the store owner.
     */
    public function resendCredentials(Vendor $vendor, VendorAdmin $owner): CredentialDeliveryResult
    {
        return $this->credentials->sendSetPasswordLink(
            $owner,
            'vendor_admins',
            'vendor_credentials_resent',
            'vendor_resend',
            ['store' => (string) ($vendor->store_name ?? $vendor->business_name ?? $vendor->name)],
        );
    }
}
