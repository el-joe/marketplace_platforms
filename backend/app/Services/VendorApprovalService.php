<?php

namespace App\Services;

use App\Enums\VendorDocumentRequirementLevel;
use App\Enums\VendorDocumentStatus;
use App\Jobs\VendorApprovedJob;
use App\Models\Admin;
use App\Models\Vendor;
use App\Models\VendorDocumentType;
use App\Models\VendorStrike;
use App\Notifications\Vendor\AccountReactivated;
use App\Notifications\Vendor\AccountSuspended;
use App\Notifications\Vendor\DocumentsRequested;
use App\Notifications\Vendor\PayoutHoldPlaced;
use App\Notifications\Vendor\PayoutHoldReleased;
use App\Notifications\Vendor\StrikeIssued;
use App\Notifications\Vendor\VendorApproved;
use App\Services\Auth\CredentialDeliveryResult;
use App\Services\Vendor\OwnerEmailConflictException;
use App\Services\Vendor\VendorOwnerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class VendorApprovalService
{
    /**
     * Fallback approval documents, used only for a country that has no mandatory document
     * configured in Admin → Vendor document types. Configured countries use their own list.
     */
    public const DEFAULT_REQUIRED_DOC_TYPES = ['business_license', 'tax_certificate', 'owner_id'];

    public function __construct(private readonly VendorOwnerService $owners) {}

    // ── Approval eligibility ──────────────────────────────────────────────────

    /**
     * Returns a list of unmet approval requirements for the vendor.
     * Empty array means the vendor is eligible for approval.
     */
    public function getApprovalBlockers(Vendor $vendor): array
    {
        $blockers = [];

        if (empty($vendor->business_name) || empty($vendor->business_type)) {
            $blockers[] = 'Business info is incomplete.';
        }

        foreach (array_keys($this->requiredDocumentTypes($vendor)) as $type) {
            $verified = $vendor->documents()
                ->whereHas('documentType', fn ($q) => $q->where('code', $type))
                ->where('status', VendorDocumentStatus::Approved)
                ->exists();

            if (! $verified) {
                $blockers[] = "Required document not verified: {$type}.";
            }
        }

        return $blockers;
    }

    /**
     * The document types that must be verified before the vendor can be approved: the mandatory
     * documents configured for the vendor's country, or the platform default when none are configured.
     *
     * @return array<string, string> document type code => display label
     */
    public function requiredDocumentTypes(Vendor $vendor): array
    {
        $country = $vendor->country;

        $configured = $country
            ? $country->requiredDocumentTypesFor()
                ->filter(fn (array $item) => $item['requirement_level'] === VendorDocumentRequirementLevel::Mandatory)
                ->mapWithKeys(fn (array $item) => [$item['type']->code => $this->documentTypeLabel($item['type'])])
                ->all()
            : [];

        if ($configured !== []) {
            return $configured;
        }

        $defaults = VendorDocumentType::whereIn('code', self::DEFAULT_REQUIRED_DOC_TYPES)->get()->keyBy('code');

        return collect(self::DEFAULT_REQUIRED_DOC_TYPES)
            ->mapWithKeys(fn (string $code) => [
                $code => $defaults->has($code)
                    ? $this->documentTypeLabel($defaults->get($code))
                    : ucwords(str_replace('_', ' ', $code)),
            ])
            ->all();
    }

    private function documentTypeLabel(VendorDocumentType $type): string
    {
        $localized = app()->getLocale() === 'ar' ? $type->name_ar : $type->name_en;

        return $localized ?: ($type->name_en ?: ucwords(str_replace('_', ' ', $type->code)));
    }

    /**
     * Returns a list of non-blocking cautions for the vendor's approval.
     * These do not prevent approval but should be surfaced to the admin.
     */
    public function getApprovalWarnings(Vendor $vendor): array
    {
        $warnings = [];

        if (! $vendor->bankAccounts()->exists()) {
            $warnings[] = 'No bank account on file — payouts will not be possible until one is added.';
        }

        if (! $vendor->onboarding_completed_at) {
            $warnings[] = 'Vendor has not completed onboarding.';
        }

        return $warnings;
    }

    // ── Approval ───────────────────────────────────────────────────────────────

    /**
     * Approve the vendor, make sure its owner login exists and is flagged as owner, then
     * email the owner the login URL and a set-password link (synchronously, so a failure
     * is visible to the approving admin).
     *
     * @throws OwnerEmailConflictException when the store email already belongs to another seller account
     */
    public function approve(Vendor $vendor, Admin $admin): CredentialDeliveryResult
    {
        $owner = DB::transaction(function () use ($vendor, $admin) {
            $vendor->update([
                'global_status' => 'active',
                'approved_at' => now(),
                'approved_by_admin_id' => $admin->id,
                'onboarding_completed_at' => now(),
            ]);

            $this->logActivity($vendor, $admin, 'approved', 'Vendor account approved');

            $owner = $this->owners->ensureOwner($vendor);

            Notification::send($vendor->vendorAdmins, new VendorApproved);

            return $owner;
        });

        VendorApprovedJob::dispatch($vendor->id);

        return $this->owners->sendApprovalEmail($vendor, $owner);
    }

    // ── Rejection ─────────────────────────────────────────────────────────────

    public function reject(Vendor $vendor, string $reason, Admin $admin): void
    {
        DB::transaction(function () use ($vendor, $reason, $admin) {
            $vendor->update([
                'global_status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            $this->logActivity($vendor, $admin, 'rejected', 'Vendor application rejected', ['reason' => $reason]);
            // No notification class for rejection — vendor cannot log in after rejection
        });
    }

    // ── Request more info ────────────────────────────────────────────────────

    public function requestMoreInfo(Vendor $vendor, array $documentTypes, Admin $admin): void
    {
        DB::transaction(function () use ($vendor, $documentTypes, $admin) {
            $vendor->update(['global_status' => 'under_review']);

            $typeMap = VendorDocumentType::whereIn('code', $documentTypes)
                ->pluck('id', 'code');

            foreach ($documentTypes as $code) {
                if (! isset($typeMap[$code])) {
                    continue;
                }
                $vendor->documents()->firstOrCreate(
                    ['vendor_document_type_id' => $typeMap[$code]],
                    ['status' => 'pending']
                );
            }

            $this->logActivity($vendor, $admin, 'info_requested', 'Additional documents requested', ['types' => $documentTypes]);

            Notification::send($vendor->vendorAdmins, new DocumentsRequested($documentTypes));
        });
    }

    // ── Strike issuance ────────────────────────────────────────────────────────

    public function issueStrike(Vendor $vendor, array $data, Admin $admin): VendorStrike
    {
        return DB::transaction(function () use ($vendor, $data, $admin) {
            $strike = VendorStrike::create([
                'vendor_id' => $vendor->id,
                'reason' => $data['reason'],
                'severity' => $data['severity'],
                'description' => $data['description'] ?? null,
                'issued_by_admin_id' => $admin->id,
                'expires_at' => $data['expires_at'] ?? null,
                'is_active' => true,
            ]);

            $vendor->increment('strikes_count');
            $vendor->refresh();

            $activeCount = $vendor->activeStrikes()->count();
            $autoSuspended = false;

            if ($data['severity'] === 'critical' || $activeCount >= 3) {
                $this->suspend($vendor, 'Auto-suspended: '.($data['severity'] === 'critical' ? 'critical strike issued' : '3 active strikes'), $admin);
                $autoSuspended = true;
            }

            $this->logActivity($vendor, $admin, 'strike_issued', 'Strike issued', [
                'severity' => $data['severity'],
                'reason' => $data['reason'],
                'auto_suspend' => $autoSuspended,
            ]);

            Notification::send($vendor->vendorAdmins, new StrikeIssued($strike));

            $strike->auto_suspended = $autoSuspended;
            $strike->active_count = $activeCount;

            return $strike;
        });
    }

    // ── Suspension / reactivation ─────────────────────────────────────────────

    public function suspend(Vendor $vendor, string $reason, Admin $admin): void
    {
        $vendor->update([
            'global_status' => 'suspended',
        ]);

        $this->logActivity($vendor, $admin, 'suspended', 'Vendor suspended', ['reason' => $reason]);

        Notification::send($vendor->vendorAdmins, new AccountSuspended($reason));
    }

    public function reactivate(Vendor $vendor, Admin $admin): void
    {
        $vendor->update([
            'global_status' => 'active',
        ]);

        $this->logActivity($vendor, $admin, 'reactivated', 'Vendor reactivated');

        Notification::send($vendor->vendorAdmins, new AccountReactivated);
    }

    public function blacklist(Vendor $vendor, string $reason, Admin $admin): void
    {
        $vendor->update([
            'global_status' => 'blacklisted',
            'rejection_reason' => $reason,
        ]);

        $this->logActivity($vendor, $admin, 'blacklisted', 'Vendor blacklisted', ['reason' => $reason]);
    }

    // ── Payout hold ───────────────────────────────────────────────────────────

    public function placePayoutHold(Vendor $vendor, string $reason): void
    {
        $vendor->update([
            'payout_hold_active' => true,
            'payout_hold_reason' => $reason,
        ]);

        Notification::send($vendor->vendorAdmins, new PayoutHoldPlaced($reason));
    }

    public function releasePayoutHold(Vendor $vendor): void
    {
        $vendor->update([
            'payout_hold_active' => false,
            'payout_hold_reason' => null,
        ]);

        Notification::send($vendor->vendorAdmins, new PayoutHoldReleased);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function logActivity(Vendor $vendor, Admin $admin, string $event, string $description, array $properties = []): void
    {
        DB::table('activity_log')->insert([
            'id' => Str::uuid(),
            'log_name' => 'vendor',
            'description' => $description,
            'subject_type' => Vendor::class,
            'subject_id' => $vendor->id,
            'causer_type' => Admin::class,
            'causer_id' => $admin->id,
            'properties' => json_encode(array_merge(['event' => $event], $properties)),
            'created_at' => now(),
            // 'updated_at' => now(),
        ]);
    }
}
