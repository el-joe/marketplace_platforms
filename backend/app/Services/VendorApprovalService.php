<?php

namespace App\Services;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class VendorApprovalService
{
    // ── Approval ───────────────────────────────────────────────────────────────

    public function approve(Vendor $vendor, Admin $admin): void
    {
        DB::transaction(function () use ($vendor, $admin) {
            $vendor->update([
                'global_status' => 'active',
                'approved_at' => now(),
                'approved_by_admin_id' => $admin->id,
                'onboarding_completed_at' => now(),
            ]);

            $this->logActivity($vendor, $admin, 'approved', 'Vendor account approved');

            Notification::send($vendor->vendorAdmins, new VendorApproved());

            VendorApprovedJob::dispatch($vendor->id);
        });
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
                $this->suspend($vendor, 'Auto-suspended: ' . ($data['severity'] === 'critical' ? 'critical strike issued' : '3 active strikes'), $admin);
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

        Notification::send($vendor->vendorAdmins, new AccountReactivated());
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

        Notification::send($vendor->vendorAdmins, new PayoutHoldReleased());
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function logActivity(Vendor $vendor, Admin $admin, string $event, string $description, array $properties = []): void
    {
        DB::table('activity_log')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
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
