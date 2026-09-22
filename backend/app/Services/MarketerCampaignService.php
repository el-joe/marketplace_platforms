<?php

namespace App\Services;

use App\Jobs\ProcessCampaignAutoApproveJob;
use App\Jobs\ProcessInvitationTimeoutJob;
use App\Jobs\SendCampaignWhatsAppNotificationJob;
use App\Models\Admin;
use App\Models\AdminListing;
use App\Models\ClassifiedListing;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\MarketerCampaignTieredRule;
use App\Models\MarketerCategoryCommission;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketerListing;
use App\Models\TravelPackage;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Notifications\Admin\NewCampaignPendingNotification;
use App\Notifications\Marketer\CampaignInvitationAcceptedNotification;
use App\Notifications\Marketer\CampaignInvitationReceivedNotification;
use App\Notifications\Marketer\CampaignInvitationRejectedNotification;
use App\Notifications\Vendor\CampaignApprovedNotification;
use App\Notifications\Vendor\CampaignAutoApprovedNotification;
use App\Notifications\Vendor\CampaignDoneNotification;
use App\Notifications\Vendor\CampaignPendingAdminNotification;
use App\Notifications\Vendor\CampaignRejectedNotification;
use App\Notifications\Vendor\MarketerReplacedNotification as VendorMarketerReplacedNotification;
use App\Services\Inventory\InventoryService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketerCampaignService
{
    /**
     * Create a new campaign from vendor panel.
     * $data keys: vendor_listing_id|admin_listing_id, country_id, currency,
     *             commission_type, max_commission_budget, title, notes,
     *             marketer_ids (array of Marketer UUIDs),
     *             tiered_rules (array of {from_sale_number, commission_amount} — only for tiered type)
     */
    /**
     * Mark conversions as paid when included in a payout.
     * Called from payout processing flow.
     */
    public function markConversionsPaid(array $conversionIds, string $payoutId): void
    {
        DB::transaction(function () use ($conversionIds) {
            MarketerCampaignConversion::whereIn('id', $conversionIds)
                ->where('commissioned', false)
                ->update([
                    'commissioned' => true,
                    'paid_at' => now(),
                ]);

            $conversions = MarketerCampaignConversion::whereIn('id', $conversionIds)
                ->with('invitation.marketer')
                ->get();

            $earningsByMarketer = $conversions->groupBy('invitation.marketer_id');

            foreach ($earningsByMarketer as $marketerId => $marketerConversions) {
                $totalEarned = $marketerConversions->sum('commission_amount');

                $marketer = Marketer::find($marketerId);
                if (! $marketer) {
                    continue;
                }

                $profile = $marketer->marketerProfile()->firstOrCreate(
                    ['marketer_id' => $marketerId],
                    ['total_earnings' => 0, 'total_conversions' => 0]
                );

                $profile->increment('total_earnings', $totalEarned);
                $profile->increment('total_conversions', $marketerConversions->count());
            }
        });
    }

    /**
     * enhancement.md P-14 task 2: the single campaign-creation path, for
     * vendor listings, admin (platform) listings, travel packages and
     * classified listings alike — replaces the old
     * createCampaign(Vendor $vendor, array $data) which could only ever
     * create a vendor-owned, FBN-only, product campaign.
     *
     * Fulfilment model: previously hardcoded to 'fbn' for vendor listings.
     * Now controlled by the `marketer_campaign_allowed_fulfilment_models`
     * setting (default ['fbn','fbm']) so FBP vendor listings can run
     * campaigns too, per product-owner confirmation in enhancement.md P-14.
     *
     * Invitation timing (P-14 task 4): invitations are NO LONGER dispatched
     * here. They are dispatched only once the campaign is approved (or
     * auto-approved with a non-zero commission, per P-12) — see
     * approveCampaign()/autoApproveCampaign() below. This closes the
     * "accept then get rejected" window: a marketer could previously
     * accept an invitation and get a live referral link for a campaign
     * that was later rejected.
     */
    public function createCampaign(CampaignOwner $owner, CampaignSource $source, array $data): MarketerCampaign
    {
        $source->validate();

        return DB::transaction(function () use ($owner, $source, $data) {
            $category = null;
            $listing = null;

            if ($source->isVendorListing()) {
                if (! $owner->isVendor() && ! $owner->isMarketer()) {
                    throw new \RuntimeException('A vendor-listing campaign source must be owned by a vendor (or requested by a marketer).');
                }

                $listing = $owner->isVendor()
                    ? VendorListing::where('id', $source->vendorListingId)->where('vendor_id', $owner->id)->firstOrFail()
                    : VendorListing::where('id', $source->vendorListingId)->firstOrFail();

                $allowedModels = (array) setting('marketer_campaign_allowed_fulfilment_models', ['fbn', 'fbm']);
                if (! in_array($listing->fulfillment_model, $allowedModels, true)) {
                    throw new \RuntimeException("Campaigns are not allowed for {$listing->fulfillment_model} listings.");
                }

                $category = $listing->productVariant?->product?->category;

                $minStock = (int) ($category?->min_stock_for_campaign ?? 10);
                $availableStock = app(InventoryService::class)->availableStock($listing);
                if ($availableStock < $minStock) {
                    throw new \RuntimeException("Insufficient stock. Minimum {$minStock} units required to start a campaign.");
                }
            } elseif ($source->isAdminListing()) {
                if (! $owner->isPlatform() && ! $owner->isMarketer()) {
                    throw new \RuntimeException('An admin-listing campaign source must be platform-owned (or requested by a marketer).');
                }

                $listing = AdminListing::where('id', $source->adminListingId)->firstOrFail();

                $category = $listing->productVariant?->product?->category;

                $minStock = (int) ($category?->min_stock_for_campaign ?? 10);
                $availableStock = app(InventoryService::class)->availableStock($listing);
                if ($availableStock < $minStock) {
                    throw new \RuntimeException("Insufficient stock. Minimum {$minStock} units required to start a campaign.");
                }
            }
            // Travel/classified sources: no stock/fulfilment check yet — see
            // CampaignSource docblock. They are schema-ready but unreachable
            // through any controller in this prompt.

            $marketerVendors = Marketer::whereIn('id', $data['marketer_ids'] ?? [])
                ->where('global_status', 'active')
                ->get();

            // Determine sample snapshot from category (higher of the two if mixed marketer types)
            $perMarketerSampleQty = 0;
            $platformSampleQty = 0;
            if ($category) {
                $hasInfluencer = $marketerVendors->contains(fn ($m) => $m->isInfluencer());
                $hasAffiliate = $marketerVendors->contains(fn ($m) => $m->isAffiliate());
                if ($hasInfluencer) {
                    $perMarketerSampleQty = max($perMarketerSampleQty, $category->influencer_sample_qty);
                }
                if ($hasAffiliate) {
                    $perMarketerSampleQty = max($perMarketerSampleQty, $category->affiliate_sample_qty);
                }
                $platformSampleQty = $category->platform_sample_qty;
            }

            $autoApproveHours = (int) setting('marketer_campaign_auto_approve_hours', 36);

            $status = $owner->isMarketer() ? 'marketer_requested' : 'pending_admin';

            $campaign = MarketerCampaign::create([
                'vendor_id' => $owner->isVendor() ? $owner->id : null,
                'owner_type' => $owner->isMarketer() ? 'marketer' : $owner->type,
                'owner_id' => $owner->id,
                'requested_by_marketer_id' => $owner->isMarketer() ? $owner->id : null,
                'vendor_listing_id' => $source->vendorListingId,
                'admin_listing_id' => $source->adminListingId,
                'travel_package_id' => $source->travelPackageId,
                'classified_listing_id' => $source->classifiedListingId,
                'campaign_category' => $source->category,
                'country_id' => $data['country_id'],
                'currency' => $data['currency'],
                'commission_type' => $data['commission_type'],
                'max_commission_budget' => $data['max_commission_budget'],
                'platform_commission_amount' => $data['platform_commission_amount'] ?? 0,
                'marketer_commission_amount' => $data['marketer_commission_amount'] ?? 0,
                'status' => $status,
                'auto_approve_at' => $owner->isMarketer() ? null : now()->addHours($autoApproveHours),
                'auto_approved' => false,
                'platform_sample_qty_snapshot' => $platformSampleQty,
                'per_marketer_sample_qty_snapshot' => $perMarketerSampleQty,
                'requested_marketer_vendor_ids' => $owner->isMarketer()
                    ? [$owner->id]
                    : $marketerVendors->pluck('id')->values()->all(),
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($data['commission_type'] === 'tiered' && ! empty($data['tiered_rules'])) {
                foreach ($data['tiered_rules'] as $i => $rule) {
                    MarketerCampaignTieredRule::create([
                        'campaign_id' => $campaign->id,
                        'from_sale_number' => $rule['from_sale_number'],
                        'commission_amount' => $rule['commission_amount'],
                        'currency' => $data['currency'],
                        'sort_order' => $i,
                    ]);
                }
            }

            if ($listing) {
                $listing->update(['campaign_enabled' => true]);
            }

            // Create platform samples immediately — samples are for the
            // campaign's promotional lifecycle, independent of admin review.
            if ($platformSampleQty > 0) {
                MarketerCampaignSample::create([
                    'campaign_id' => $campaign->id,
                    'invitation_id' => null,
                    'sample_owner' => 'platform',
                    'quantity' => $platformSampleQty,
                    'status' => 'pending',
                ]);
            }

            if ($owner->isMarketer()) {
                // Marketer-originated request: notify the real owner
                // (vendor or admin) to review — no invitations exist yet,
                // there's nothing to dispatch until they approve.
                if ($listing instanceof VendorListing) {
                    $listing->vendor->vendorAdmins->each(fn ($va) => $va->notify(new CampaignPendingAdminNotification($campaign)));
                } else {
                    Admin::query()->get()->each(fn ($admin) => $admin->notify(new NewCampaignPendingNotification($campaign)));
                }

                return $campaign;
            }

            // Vendor/admin-originated campaigns still go through admin
            // review (or auto-approve) — invitations are dispatched only
            // once that review passes (see approveCampaign()/autoApproveCampaign()).
            Admin::query()->get()->each(fn ($admin) => $admin->notify(new NewCampaignPendingNotification($campaign)));
            if ($owner->isVendor()) {
                $campaign->vendor?->vendorAdmins?->each(fn ($va) => $va->notify(new CampaignPendingAdminNotification($campaign)));
            }

            ProcessCampaignAutoApproveJob::dispatch($campaign->id)
                ->delay(now()->addHours($autoApproveHours));

            return $campaign;
        });
    }

    /**
     * enhancement.md P-14 task 3: a marketer proposes to promote a listing
     * (vendor or admin). Thin wrapper over createCampaign() with the
     * marketer as owner — the real owner (vendor or admin) reviews it via
     * approveMarketerRequest()/rejectCampaign().
     */
    public function requestCampaign(Marketer $marketer, CampaignSource $source, array $data): MarketerCampaign
    {
        $data['marketer_ids'] = [$marketer->id];

        return $this->createCampaign(CampaignOwner::marketer($marketer), $source, $data);
    }

    /**
     * enhancement.md P-14 task 3: the listing owner (vendor or admin)
     * approves a marketer-originated request — sets commission, flips
     * ownership to the real owner, activates the campaign, and
     * auto-accepts the requesting marketer's invitation (they already
     * asked to promote it; there is no one left to "invite").
     */
    public function approveMarketerRequest(MarketerCampaign $campaign, array $commission): void
    {
        if ($campaign->status !== 'marketer_requested') {
            throw new \RuntimeException('Only a marketer_requested campaign can be approved this way.');
        }

        DB::transaction(function () use ($campaign, $commission) {
            $ownerType = $campaign->admin_listing_id ? 'platform' : 'vendor';
            $ownerId = $ownerType === 'vendor' ? $campaign->vendorListing?->vendor_id : null;

            $campaign->update([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'vendor_id' => $ownerId,
                'marketer_commission_amount' => $commission['marketer_commission_amount'] ?? $campaign->marketer_commission_amount,
                'platform_commission_amount' => $commission['platform_commission_amount'] ?? $campaign->platform_commission_amount,
                'status' => 'active',
                'reviewed_at' => now(),
            ]);

            if ($campaign->commission_type !== 'tiered' && (float) $campaign->marketer_commission_amount <= 0) {
                throw new \RuntimeException('Cannot approve a marketer request with a zero commission. Set it first.');
            }

            $invitation = $this->dispatchInvitation($campaign, $campaign->requested_by_marketer_id);
            $this->acceptInvitation($invitation->fresh());
        });
    }

    /**
     * Admin approves a campaign. Invitations were already dispatched at creation time —
     * this just activates the campaign so conversions can be tracked.
     *
     * enhancement.md P-12 task 4: a campaign cannot go 'active' with 0
     * commission on both sides (the bug that made every conversion earn
     * nothing) — the admin must set marketer_commission_amount (and,
     * for a fixed/last_click campaign, platform_commission_amount) to a
     * positive value before approving, except for 'tiered' campaigns whose
     * commission comes from their tiered_rules instead.
     */
    public function approveCampaign(MarketerCampaign $campaign, Admin $admin): void
    {
        if ($campaign->commission_type !== 'tiered' && (float) $campaign->marketer_commission_amount <= 0) {
            throw new \RuntimeException(
                'Cannot approve a campaign with a zero marketer commission amount. Set it first.'
            );
        }

        if ($campaign->commission_type === 'tiered' && $campaign->tieredRules()->count() === 0) {
            throw new \RuntimeException('Cannot approve a tiered campaign with no tiered rules.');
        }

        DB::transaction(function () use ($campaign, $admin) {
            $campaign->update([
                'status' => 'active',
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // enhancement.md P-14 task 4: invitations are dispatched only
            // now, after admin approval — not at creation time.
            foreach ((array) ($campaign->requested_marketer_vendor_ids ?? []) as $marketerId) {
                $this->dispatchInvitation($campaign, $marketerId);
            }

            $campaign->vendor?->vendorAdmins?->each(
                fn ($va) => $va->notify(new CampaignApprovedNotification($campaign, $campaign->invitations()->count()))
            );
        });
    }

    /**
     * Admin rejects a campaign.
     *
     * enhancement.md P-14 task 4: rejection cancels any pending invitations
     * and pauses/archives whatever marketer listings already exist for
     * this campaign (there should not be any yet, since invitations are
     * no longer dispatched before approval — but this guards against a
     * campaign rejected after having been active/auto_approved before).
     */
    public function rejectCampaign(MarketerCampaign $campaign, Admin $admin, string $reason): void
    {
        DB::transaction(function () use ($campaign, $admin, $reason) {
            $campaign->update([
                'status' => 'rejected',
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->cancelPendingInvitationsAndArchiveListings($campaign);

            $campaign->vendor?->vendorAdmins?->each(fn ($va) => $va->notify(new CampaignRejectedNotification($campaign)));
        });
    }

    /**
     * enhancement.md P-14 task 4: cancel a campaign (vendor/admin self-
     * service cancel, distinct from admin rejection) — same cleanup as
     * rejection.
     */
    public function cancelCampaign(MarketerCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => 'cancelled']);
            $this->cancelPendingInvitationsAndArchiveListings($campaign);
        });
    }

    /**
     * enhancement.md P-14 task 4: shared cleanup for reject/cancel/done —
     * pending invitations are cancelled (so they can no longer be accepted)
     * and any marketer listing already created from this campaign's
     * invitations is archived so it stops appearing as purchasable.
     */
    private function cancelPendingInvitationsAndArchiveListings(MarketerCampaign $campaign): void
    {
        $campaign->invitations()->where('status', 'pending')->get()->each(function ($invitation) {
            $invitation->update(['status' => 'cancelled', 'responded_at' => now()]);
        });

        MarketerListing::whereIn(
            'invitation_id',
            $campaign->invitations()->pluck('id')
        )->where('status', '!=', 'archived')->get()->each(
            fn ($listing) => $listing->update(['status' => 'archived'])
        );
    }

    /**
     * enhancement.md P-14 task 4: pauses a campaign (and its marketer
     * listings) without cancelling invitations — used when stock drops
     * below min_stock_for_campaign but is not yet zero. Resumes it once
     * stock recovers. See MonitorCampaignStockJob.
     */
    public function pauseCampaignForLowStock(MarketerCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => 'paused']);

            MarketerListing::whereIn('invitation_id', $campaign->invitations()->pluck('id'))
                ->where('status', 'active')
                ->update(['status' => 'paused']);
        });
    }

    /**
     * enhancement.md P-14 task 4: resumes a campaign paused by
     * pauseCampaignForLowStock() once stock recovers above
     * min_stock_for_campaign.
     */
    public function resumeCampaignAfterRestock(MarketerCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => $campaign->auto_approved ? 'auto_approved' : 'active']);

            MarketerListing::whereIn('invitation_id', $campaign->invitations()->pluck('id'))
                ->where('status', 'paused')
                ->update(['status' => 'active']);
        });
    }

    /**
     * Auto-approve a campaign (called by job after the configured timeout).
     *
     * enhancement.md P-12 task 4: auto-approval must never activate a
     * campaign with a 0 commission — the bug that made every conversion
     * earn nothing. Since nobody set marketer_commission_amount manually
     * (that's exactly why this is auto-approving instead of an admin
     * click), pull a default rate from marketer_category_commissions /
     * MarketerCommissionCountrySetting for the promoted item's category +
     * the campaign's country. If neither has a default, the campaign
     * stays 'pending_admin' (auto_approve_at is pushed back so the job
     * doesn't immediately retry it every run) and admins are notified to
     * set one manually.
     */
    public function autoApproveCampaign(string $campaignId): void
    {
        $campaign = MarketerCampaign::where('id', $campaignId)
            ->where('status', 'pending_admin')
            ->where('auto_approved', false)
            ->first();

        if (! $campaign) {
            return;
        }

        if ($campaign->commission_type !== 'tiered') {
            $defaults = $this->resolveDefaultCommission($campaign);

            if ($defaults === null) {
                $campaign->update(['auto_approve_at' => now()->addDays(3)]);

                Admin::query()->get()->each(
                    fn ($admin) => $admin->notify(new NewCampaignPendingNotification($campaign))
                );

                return;
            }

            $campaign->marketer_commission_amount = $defaults['marketer_commission_amount'];
            $campaign->platform_commission_amount = $defaults['platform_commission_amount'];
        } elseif ($campaign->tieredRules()->count() === 0) {
            // No tiers configured — nothing to auto-approve into.
            $campaign->update(['auto_approve_at' => now()->addDays(3)]);

            return;
        }

        DB::transaction(function () use ($campaign) {
            $campaign->status = 'auto_approved';
            $campaign->auto_approved = true;
            $campaign->reviewed_at = now();
            $campaign->save();

            // enhancement.md P-14 task 4: invitations are dispatched only
            // now, after auto-approval — not at creation time.
            foreach ((array) ($campaign->requested_marketer_vendor_ids ?? []) as $marketerId) {
                $this->dispatchInvitation($campaign, $marketerId);
            }

            $campaign->vendor?->vendorAdmins?->each(fn ($va) => $va->notify(new CampaignAutoApprovedNotification($campaign)));
        });
    }

    /**
     * @return array{marketer_commission_amount: int, platform_commission_amount: int}|null
     */
    private function resolveDefaultCommission(MarketerCampaign $campaign): ?array
    {
        $category = $campaign->vendorListing?->productVariant?->product?->category
            ?? $campaign->adminListing?->productVariant?->product?->category;

        if (! $category) {
            return null;
        }

        $categoryRate = MarketerCategoryCommission::where('category_id', $category->id)
            ->whereNull('marketer_id')
            ->first();

        $countrySetting = MarketerCommissionCountrySetting::where('country_id', $campaign->country_id)
            ->where('category_id', $category->id)
            ->first();

        $marketerCommission = 0;
        if ($countrySetting) {
            // Both marketer types default to the affiliate rate here — the
            // campaign is shared across whichever marketers accept the
            // invitation, and per-marketer-type differences are already
            // applied per-conversion in LastClickAttributionService.
            $marketerCommission = (int) $countrySetting->affiliate_commission_amount;
        }

        if ($marketerCommission <= 0) {
            return null;
        }

        $platformCommission = $categoryRate ? (int) round($marketerCommission * ((float) $categoryRate->commission_rate / 100)) : 0;

        return [
            'marketer_commission_amount' => $marketerCommission,
            'platform_commission_amount' => $platformCommission,
        ];
    }

    /**
     * Dispatch a campaign invitation to a marketer vendor.
     */
    public function dispatchInvitation(MarketerCampaign $campaign, string $marketerId): MarketerCampaignInvitation
    {
        $timeoutHours = max(168, (int) setting('marketer_invitation_timeout_hours', 168));
        $referralCode = strtoupper(Str::random(10));

        $invitation = MarketerCampaignInvitation::create([
            'campaign_id' => $campaign->id,
            'marketer_id' => $marketerId,
            'status' => 'pending',
            'acceptance_window_hours' => $timeoutHours,
            'expires_at' => now()->addHours($timeoutHours),
            'referral_code' => $referralCode,
            'referral_link' => rtrim(config('app.frontend_url'), '/')."/r/{$referralCode}",
        ]);

        // Generate QR code for this invitation's referral link
        $qrPath = $this->generateQrCode($invitation->referral_link, $invitation->id);
        if ($qrPath) {
            $invitation->update(['qr_code_path' => $qrPath]);
        }

        ProcessInvitationTimeoutJob::dispatch($invitation->id)
            ->delay(now()->addHours($timeoutHours));

        SendCampaignWhatsAppNotificationJob::dispatch($invitation->id);

        $invitation->marketer->marketerAdmins->each(
            fn ($ma) => $ma->notify(new CampaignInvitationReceivedNotification($invitation, $ma->id))
        );

        return $invitation;
    }

    /**
     * Invite one or more marketers to an EXISTING campaign.
     * Skips marketers who already have a pending or accepted invitation
     * on this campaign — dispatchInvitation() itself has no duplicate guard,
     * so this is the only safe entry point for bulk/repeated invites.
     *
     * @return array{invited: array<string>, skipped: array<string,string>}
     *                                                                      invited = marketer IDs successfully invited
     *                                                                      skipped = marketer ID => reason (e.g. 'already_pending', 'already_accepted', 'inactive')
     */
    public function inviteMarketers(MarketerCampaign $campaign, array $marketerIds): array
    {
        $invited = [];
        $skipped = [];

        if (! in_array($campaign->status, ['pending_admin', 'active'])) {
            foreach ($marketerIds as $id) {
                $skipped[$id] = 'campaign_not_open';
            }

            return ['invited' => $invited, 'skipped' => $skipped];
        }

        $existingInvitations = MarketerCampaignInvitation::where('campaign_id', $campaign->id)
            ->whereIn('marketer_id', $marketerIds)
            ->whereIn('status', ['pending', 'accepted'])
            ->pluck('status', 'marketer_id');

        $marketers = Marketer::whereIn('id', $marketerIds)->get()->keyBy('id');

        foreach ($marketerIds as $marketerId) {
            if ($existingInvitations->has($marketerId)) {
                $skipped[$marketerId] = 'already_'.$existingInvitations[$marketerId];

                continue;
            }

            $marketer = $marketers->get($marketerId);
            if (! $marketer || $marketer->global_status !== 'active') {
                $skipped[$marketerId] = 'inactive';

                continue;
            }

            $this->dispatchInvitation($campaign, $marketerId);
            $invited[] = $marketerId;
        }

        return ['invited' => $invited, 'skipped' => $skipped];
    }

    private function generateQrCode(string $url, string $invitationId): ?string
    {
        try {
            $result = (new Builder(
                writer: new PngWriter,
                data: $url,
                size: 400,
                margin: 15,
            ))->build();

            $path = 'qrcodes/invitations/'.$invitationId.'.png';
            Storage::disk('public')->put($path, $result->getString());

            return $path;
        } catch (\Throwable $e) {
            Log::warning(
                'QR generation failed for invitation '.$invitationId.': '.$e->getMessage()
            );

            return null;
        }
    }

    /**
     * Marketer accepts an invitation. Samples for the marketer are created now,
     * not at invitation time.
     */
    public function acceptInvitation(MarketerCampaignInvitation $invitation, ?string $marketerNote = null): void
    {
        if (! $invitation->isPending()) {
            throw new \RuntimeException('Invitation is no longer pending.');
        }

        // enhancement.md P-16: a marketer cannot access (accept into) a
        // campaign before BOTH admin approval (global_status = active)
        // AND accepting their current onboarding contract version.
        $invitingMarketer = $invitation->marketer;
        if ($invitingMarketer->global_status?->value !== 'active') {
            throw new \RuntimeException('Marketer account is not approved.');
        }
        if (! $invitingMarketer->hasAcceptedContract()) {
            throw new \RuntimeException('Marketer must accept the onboarding contract before accepting campaigns.');
        }

        DB::transaction(function () use ($invitation, $marketerNote) {
            $campaign = $invitation->campaign;
            $marketer = $invitation->marketer;

            // Platform fee applies per accepted invitation, influencer type only. Affiliate is always free.
            $feeAmount = 0;
            $feeStatus = 'not_applicable';

            if ($marketer->isInfluencer()) {
                $feeSetting = MarketerInfluencerFeeCountrySetting::where('country_id', $campaign->country_id)
                    ->first();

                $feeAmount = $feeSetting?->fee_per_influencer ?? 0;
                $feeStatus = $feeAmount > 0 ? 'pending' : 'waived';
            }

            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
                'marketer_note' => $marketerNote,
                'platform_fee_amount' => $feeAmount,
                'platform_fee_currency' => $campaign->currency,
                'platform_fee_status' => $feeStatus,
                'platform_fee_recorded_at' => now(),
            ]);

            // enhancement.md P-16 fix: the influencer platform fee must
            // actually be debited from the marketer's wallet at acceptance
            // time, not merely recorded as 'pending' on the invitation —
            // otherwise it is only ever settled if an admin later remembers
            // to call markInvitationFeePaid() manually.
            if ($feeStatus === 'pending' && $feeAmount > 0) {
                $walletService = app(WalletService::class);
                $wallet = $walletService->getOrCreateWallet('marketer', $marketer->id, $campaign->currency);
                $walletService->debit(
                    $wallet,
                    (int) $feeAmount,
                    'marketer_campaign_invitation_platform_fee',
                    $invitation->id,
                    'Influencer platform fee for accepted campaign invitation'
                );
                $invitation->update(['platform_fee_status' => 'paid']);
            }

            $category = $campaign->vendorListing?->productVariant?->product?->category
                ?? $campaign->adminListing?->productVariant?->product?->category;

            $sampleQty = 0;
            if ($category && $marketer) {
                $sampleQty = $marketer->isInfluencer()
                    ? ($category->influencer_sample_qty ?? 0)
                    : ($category->affiliate_sample_qty ?? 0);
            }

            if ($sampleQty > 0) {
                MarketerCampaignSample::create([
                    'campaign_id' => $campaign->id,
                    'invitation_id' => $invitation->id,
                    'sample_owner' => 'marketer',
                    'quantity' => $sampleQty,
                    'status' => 'pending',
                ]);
            }

            $campaign->vendor?->vendorAdmins?->each(
                fn ($va) => $va->notify(new CampaignInvitationAcceptedNotification($invitation))
            );

            $this->createMarketerListingFromInvitation($invitation);
        });
    }

    /**
     * Auto-create a MarketerListing for the campaign product once an invitation is accepted.
     */
    private function createMarketerListingFromInvitation(MarketerCampaignInvitation $invitation): void
    {
        $campaign = $invitation->campaign;
        $marketer = $invitation->marketer;

        match ($campaign->campaign_category ?? 'product') {
            'travel' => $this->createTravelListing($campaign, $marketer, $invitation),
            'classified' => $this->createClassifiedListing($campaign, $marketer, $invitation),
            default => $this->createProductListing($campaign, $marketer, $invitation),
        };
    }

    private function createProductListing(MarketerCampaign $campaign, Marketer $marketer, MarketerCampaignInvitation $invitation): void
    {
        $sourcePrice = null;
        $sourceCurrency = $campaign->currency;
        $sourceCondition = 'new';
        $variantId = null;
        $sourceType = null;
        $sourceListingId = null;

        if ($campaign->vendor_listing_id) {
            $source = VendorListing::find($campaign->vendor_listing_id);
            $sourcePrice = $source?->price;
            $sourceCondition = $source?->condition ?? 'new';
            $variantId = $source?->product_variant_id;
            $sourceType = 'vendor_listing';
            $sourceListingId = $source?->id;
        } elseif ($campaign->admin_listing_id) {
            $source = AdminListing::find($campaign->admin_listing_id);
            $sourcePrice = $source?->price;
            $variantId = $source?->product_variant_id;
            $sourceType = 'admin_listing';
            $sourceListingId = $source?->id;
        }

        if (! $variantId || ! $sourcePrice) {
            return; // Can't create listing without product/price
        }

        // enhancement.md P-15: campaign-linked marketer listings resolve
        // their source explicitly (vendor_listing_id / admin_listing_id),
        // same as independent listings — CartLineSource and the
        // availability observers no longer need to walk
        // invitation->campaign->listing at checkout/sync time.
        MarketerListing::firstOrCreate(
            ['invitation_id' => $invitation->id],
            [
                'marketer_id' => $marketer->id,
                'product_variant_id' => $variantId,
                'country_id' => $campaign->country_id,
                'listing_category' => 'product',
                'source_type' => $sourceType,
                'source_listing_id' => $sourceListingId,
                'price' => $sourcePrice,
                'currency' => $sourceCurrency,
                'condition' => $sourceCondition,
                'status' => 'active',
                'referral_code' => $invitation->referral_code,
                'referral_link' => $invitation->referral_link,
            ]
        );
    }

    private function createTravelListing(MarketerCampaign $campaign, Marketer $marketer, MarketerCampaignInvitation $invitation): void
    {
        if (! $campaign->travel_package_id) {
            return;
        }

        $package = TravelPackage::find($campaign->travel_package_id);
        if (! $package) {
            return;
        }

        MarketerListing::firstOrCreate(
            ['invitation_id' => $invitation->id],
            [
                'marketer_id' => $marketer->id,
                'travel_package_id' => $campaign->travel_package_id,
                'country_id' => $campaign->country_id,
                'listing_category' => 'travel',
                'price' => $package->price,
                'currency' => $package->currency,
                'condition' => 'new',
                'status' => 'active',
                'referral_code' => $invitation->referral_code,
                'referral_link' => $invitation->referral_link,
            ]
        );
    }

    private function createClassifiedListing(MarketerCampaign $campaign, Marketer $marketer, MarketerCampaignInvitation $invitation): void
    {
        if (! $campaign->classified_listing_id) {
            return;
        }

        $classified = ClassifiedListing::find($campaign->classified_listing_id);
        if (! $classified) {
            return;
        }

        MarketerListing::firstOrCreate(
            ['invitation_id' => $invitation->id],
            [
                'marketer_id' => $marketer->id,
                'classified_listing_id' => $campaign->classified_listing_id,
                'country_id' => $campaign->country_id,
                'listing_category' => 'classified',
                'price' => $classified->price,
                'currency' => $classified->currency,
                'condition' => 'new',
                'status' => 'active',
                'referral_code' => $invitation->referral_code,
                'referral_link' => $invitation->referral_link,
            ]
        );
    }

    /**
     * Marketer rejects an invitation — triggers replacement logic.
     */
    public function rejectInvitation(MarketerCampaignInvitation $invitation, ?string $reason = null): void
    {
        if (! $invitation->isPending()) {
            throw new \RuntimeException('Invitation is no longer pending.');
        }

        $invitation->update([
            'status' => 'rejected',
            'responded_at' => now(),
            'decline_reason' => $reason,
        ]);

        $this->replaceMarketer($invitation);
    }

    /**
     * Handle timeout — called by ProcessInvitationTimeoutJob.
     */
    public function handleInvitationTimeout(string $invitationId): void
    {
        $invitation = MarketerCampaignInvitation::find($invitationId);
        if (! $invitation || ! $invitation->isPending()) {
            return;
        }

        $invitation->update(['status' => 'timed_out', 'responded_at' => now()]);
        $this->replaceMarketer($invitation);
    }

    /**
     * Find a replacement marketer (same type, min accepted campaigns) and dispatch new invitation.
     */
    protected function replaceMarketer(MarketerCampaignInvitation $oldInvitation): void
    {
        $campaign = $oldInvitation->campaign;
        $oldMarketer = $oldInvitation->marketer;

        $alreadyInvited = $campaign->invitations()->pluck('marketer_id')->toArray();
        $minAccepted = (int) setting('marketer_replacement_min_accepted_campaigns', 0);

        $oldJobKeys = $oldMarketer->marketerJobs->pluck('key');

        $replacement = Marketer::whereHas('marketerJobs', fn ($q) => $q->whereIn('key', $oldJobKeys))
            ->whereNotIn('id', $alreadyInvited)
            ->where('global_status', 'active')
            ->withCount(['campaignInvitations as accepted_count' => fn ($q) => $q->where('status', 'accepted')])
            ->having('accepted_count', '>=', $minAccepted)
            ->orderBy('accepted_count', 'desc')
            ->first();

        $campaign->vendor?->vendorAdmins?->each(
            fn ($va) => $va->notify(new CampaignInvitationRejectedNotification($oldInvitation))
        );

        if (! $replacement) {
            return; // No replacement available
        }

        $newInvitation = $this->dispatchInvitation($campaign, $replacement->id);
        $newInvitation->update(['replaced_invitation_id' => $oldInvitation->id]);

        $campaign->vendor?->vendorAdmins?->each(
            fn ($va) => $va->notify(new VendorMarketerReplacedNotification($campaign, $oldMarketer, $replacement))
        );
    }

    /**
     * Mark a campaign as done when product stock hits zero.
     */
    public function markCampaignDone(MarketerCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => 'done']);
            // enhancement.md P-14 task 4: done cancels pending invitations
            // and archives marketer listings, same as reject/cancel.
            $this->cancelPendingInvitationsAndArchiveListings($campaign);
        });

        $totalConversions = (int) $campaign->conversions()->count();
        $totalCommissionEarned = (float) $campaign->conversions()->sum('commission_amount');

        $campaign->vendor?->vendorAdmins?->each(
            fn ($va) => $va->notify(new CampaignDoneNotification($campaign, $totalConversions, $totalCommissionEarned))
        );

        $campaign->invitations()->where('status', 'accepted')->with('marketer.marketerAdmins')->get()
            ->each(fn ($inv) => $inv->marketer->marketerAdmins->each(
                fn ($va) => $va->notify(new CampaignDoneNotification(
                    $campaign,
                    (int) $inv->total_conversions,
                    (float) $inv->total_commission_earned
                ))
            ));
    }

    /**
     * enhancement.md P-14 task 4: resolve a campaign's source stock and
     * react — 0 stock marks the campaign done (matches the pre-existing
     * MonitorCampaignStockJob behaviour), below min_stock_for_campaign
     * pauses it, at/above min_stock_for_campaign resumes a stock-paused
     * one. Used both by the ListingStockChanged listener (immediate) and
     * MonitorCampaignStockJob's scheduled sweep (safety net).
     */
    public function checkStockAndUpdateStatus(MarketerCampaign $campaign): void
    {
        $listing = $campaign->vendor_listing_id
            ? $campaign->vendorListing
            : ($campaign->admin_listing_id ? $campaign->adminListing : null);

        if (! $listing) {
            return; // travel/classified sources have no stock concept yet.
        }

        $category = $listing->productVariant?->product?->category;
        $minStock = (int) ($category?->min_stock_for_campaign ?? 10);
        $stock = app(InventoryService::class)->availableStock($listing);

        if ($stock <= 0) {
            if (in_array($campaign->status, ['active', 'auto_approved', 'paused'], true)) {
                $this->markCampaignDone($campaign);
            }

            return;
        }

        if ($stock < $minStock && in_array($campaign->status, ['active', 'auto_approved'], true)) {
            $this->pauseCampaignForLowStock($campaign);

            return;
        }

        if ($stock >= $minStock && $campaign->status === 'paused') {
            $this->resumeCampaignAfterRestock($campaign);
        }
    }

    /**
     * Search active marketers by name/email (used by vendor panel to search available marketers).
     */
    public function searchMarketers(string $query, ?string $type = null): Collection
    {
        return Marketer::query()
            ->where('global_status', 'active')
            ->when($type, fn ($q) => $q->whereHas('marketerJobs', fn ($j) => $j->where('key', $type)))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->with('marketerJobs')
            ->limit(20)
            ->get(['id', 'name', 'email']);
    }
}
