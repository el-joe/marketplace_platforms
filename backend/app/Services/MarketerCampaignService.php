<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PeerPromotionException;
use App\Jobs\ProcessCampaignAutoApproveJob;
use App\Jobs\ProcessInvitationTimeoutJob;
use App\Jobs\SendCampaignWhatsAppNotificationJob;
use App\Jobs\SendVendorInvitationResponseWhatsAppJob;
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
use App\Models\MarketerCommissionRule;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketerListing;
use App\Models\OpenMarketCategoryCommission;
use App\Models\TravelPackage;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Notifications\Admin\CampaignMarketerReassignedNotification;
use App\Notifications\Admin\NewCampaignPendingNotification;
use App\Notifications\Marketer\CampaignExpired;
use App\Notifications\Marketer\CampaignInvitationReceivedNotification;
use App\Notifications\Marketer\CampaignInvitationRejectedNotification;
use App\Notifications\Marketer\PeerPromotionStartedNotification;
use App\Notifications\Vendor\CampaignApprovedNotification;
use App\Notifications\Vendor\CampaignAutoApprovedNotification;
use App\Notifications\Vendor\CampaignDoneNotification;
use App\Notifications\Vendor\CampaignInvitationResponseNotification as VendorCampaignInvitationResponseNotification;
use App\Notifications\Vendor\CampaignPendingAdminNotification;
use App\Notifications\Vendor\CampaignRejectedNotification;
use App\Notifications\Vendor\MarketerReplacedNotification as VendorMarketerReplacedNotification;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Inventory\InventoryService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Carbon\CarbonInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketerCampaignService
{
    /** Default for the `marketer_campaign_allowed_fulfilment_models` setting (cross_dock = billed as FBM). */
    public const DEFAULT_ALLOWED_FULFILMENT_MODELS = ['fbn', 'fbm', 'cross_dock'];

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
        DB::transaction(function () use ($conversionIds, $payoutId) {
            MarketerCampaignConversion::whereIn('id', $conversionIds)
                ->where('commissioned', false)
                ->update([
                    'commissioned' => true,
                    'paid_at' => now(),
                    'payout_id' => $payoutId,
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
     * setting (default ['fbn','fbm','cross_dock']) so FBP vendor listings can run
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

                $allowedModels = (array) setting('marketer_campaign_allowed_fulfilment_models', MarketerCampaignService::DEFAULT_ALLOWED_FULFILMENT_MODELS);
                if (! in_array($listing->fulfillment_model?->value, $allowedModels, true)) {
                    throw new \RuntimeException("Campaigns are not allowed for {$listing->fulfillment_model?->value} listings.");
                }

                $category = $listing->productVariant?->product?->category;
            } elseif ($source->isAdminListing()) {
                if (! $owner->isPlatform() && ! $owner->isMarketer()) {
                    throw new \RuntimeException('An admin-listing campaign source must be platform-owned (or requested by a marketer).');
                }

                $listing = AdminListing::where('id', $source->adminListingId)->firstOrFail();

                $category = $listing->productVariant?->product?->category;
            } elseif ($source->isMarketerListing()) {
                // P13 (R11): influencer promotes ANOTHER influencer's own FBN listing.
                if (! $owner->isMarketer()) {
                    throw new \RuntimeException('A marketer-listing campaign can only be requested by a marketer (the promoter).');
                }

                $peerListing = $this->assertPeerPromotionAllowed($owner->id, $source->marketerListingId);
                $category = $peerListing->productVariant?->product?->category;
            }
            // Travel/classified sources: no stock/fulfilment check yet — see
            // CampaignSource docblock. They are schema-ready but unreachable
            // through any controller in this prompt.

            $marketerVendors = Marketer::whereIn('id', $data['marketer_ids'] ?? [])
                ->where('global_status', 'active')
                ->get();

            // Samples: one fixed admin sample + one per influencer (+ one affiliate
            // row when affiliates are targeted). The stock gate counts them all.
            $samples = app(CampaignSampleService::class);
            $hasInfluencer = false;
            $influencerCount = 0;
            $hasAffiliate = false;
            foreach ($marketerVendors as $m) {
                if ($m->isInfluencer()) {
                    $influencerCount++;
                    $hasInfluencer = true;
                } else {
                    $hasAffiliate = true;
                }
            }
            $plan = $samples->plan($category, $influencerCount, $hasAffiliate);
            $platformSampleQty = $listing ? $plan['admin'] : 0;
            $perMarketerSampleQty = $listing && $hasInfluencer ? $plan['influencer_each'] : 0;

            if ($listing) {
                $minStock = (int) ($category?->min_stock_for_campaign ?? 10);
                $remaining = $samples->stockAfterSamples($listing, $plan['total']);
                if ($remaining < $minStock) {
                    throw new \RuntimeException("Insufficient stock. Minimum {$minStock} units must remain after {$plan['total']} sample unit(s) are set aside (available after samples: {$remaining}).");
                }
            }

            $autoApproveHours = (int) setting('marketer_campaign_auto_approve_hours', 36);

            $isPeer = $source->isMarketerListing();
            $status = $owner->isMarketer() ? ($isPeer ? 'active' : 'marketer_requested') : 'pending_admin';

            if ($isPeer) {
                // Country/currency are the promoted listing's, never client supplied.
                $data['country_id'] = $peerListing->country_id;
                $data['currency'] = $peerListing->currency;
                $data['commission_type'] = 'fixed';
                $data['max_commission_budget'] = $data['max_commission_budget'] ?? 0;
            }

            // Vendor-listing campaigns snapshot the server-side quote (never client numbers) so later setting
            // changes do not rewrite what the vendor was shown; platform/marketer-owned ones carry no vendor charge.
            $feeSnapshot = $listing instanceof VendorListing && $owner->isVendor()
                ? $this->feeSnapshot($listing, $marketerVendors->pluck('id')->all())
                : [];

            $campaign = MarketerCampaign::create($feeSnapshot + [
                'vendor_id' => $owner->isVendor() ? $owner->id : null,
                'owner_type' => $owner->isMarketer() ? 'marketer' : $owner->type,
                // Peer campaigns: the real owner is the listing's influencer, the promoter is requested_by.
                'owner_id' => $isPeer ? $peerListing->marketer_id : $owner->id,
                'requested_by_marketer_id' => $owner->isMarketer() ? $owner->id : null,
                'vendor_listing_id' => $source->vendorListingId,
                'admin_listing_id' => $source->adminListingId,
                'travel_package_id' => $source->travelPackageId,
                'classified_listing_id' => $source->classifiedListingId,
                'marketer_listing_id' => $source->marketerListingId,
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
                'selected_ad_types' => $data['selected_ad_types'] ?? null,
                'vendor_ad_notes' => $data['vendor_ad_notes'] ?? null,
            ]);

            // The vendor never types a commission: show the admin-configured default straight away so a campaign
            // awaiting review does not display 0 (approval re-resolves it only when it is still unset).
            if ($data['commission_type'] !== 'tiered' && (float) $campaign->marketer_commission_amount <= 0) {
                $defaults = $this->resolveDefaultCommission($campaign);
                if ($defaults !== null) {
                    $campaign->update([
                        'marketer_commission_amount' => $defaults['marketer_commission_amount'],
                        'platform_commission_amount' => $defaults['platform_commission_amount'],
                    ]);
                }
            }

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

            // Fixed admin sample exists from creation, independent of admin review.
            // Influencer samples follow their invitations (dispatchInvitation()).
            $samples->ensureAdminSample($campaign, $category);
            if ($hasAffiliate) {
                $samples->ensureAffiliateSample($campaign, $category);
            }

            if ($isPeer) {
                // No seller/admin review: the owner is an influencer and the rules were enforced
                // above, so the promotion goes live immediately for the promoter.
                return $this->activatePeerCampaign($campaign, $peerListing);
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

    /** True when the campaign targets at least one marketer and every one of them is an influencer. */
    public function isInfluencerOnly(MarketerCampaign $campaign): bool
    {
        $ids = array_values(array_filter((array) ($campaign->requested_marketer_vendor_ids ?? [])));
        if ($ids === []) {
            return false;
        }

        $marketers = Marketer::whereIn('id', $ids)->with('marketerJobs')->get();

        return $marketers->isNotEmpty() && $marketers->every(fn (Marketer $m) => $m->isInfluencer());
    }

    /**
     * Campaign fee snapshot columns from a fresh server-side quote.
     *
     * @param  array<int, string>  $marketerIds
     * @return array<string, mixed>
     */
    private function feeSnapshot(VendorListing $listing, array $marketerIds): array
    {
        $quote = app(MarketerCampaignQuoteService::class)->quote($listing, $marketerIds);

        return [
            'fulfillment_type' => $quote['fulfillment_type'],
            'campaign_subtotal' => $quote['campaign_subtotal'],
            'marketing_fee_fixed' => $quote['marketing']['fixed_fee'],
            'marketing_fee_variable' => $quote['marketing']['variable_fee'],
            'marketing_fee_total' => $quote['marketing']['total'],
            'campaign_grand_total' => $quote['campaign_grand_total'],
        ];
    }

    /**
     * P13 (R11): the rules of an influencer-to-influencer promotion. Returns the (locked-free)
     * owner listing when the promoter may promote it right now, otherwise throws
     * PeerPromotionException with a stable reason key.
     *
     *  - the listing must be another influencer's OWN FBN listing (source_listing_id IS NULL);
     *  - promoter and owner must both be influencers; promoter != owner (self-promotion block);
     *  - the listing must be `active`;
     *  - it must have stock in its own warehouse_inventories;
     *  - ONE promotion per (promoter, owner) per calendar month (rejected/cancelled ones free the slot).
     *    The promoter row is locked so two simultaneous requests cannot both pass.
     */
    public function assertPeerPromotionAllowed(string $promoterId, string $marketerListingId, ?CarbonInterface $at = null): MarketerListing
    {
        $listing = MarketerListing::query()->with('productVariant.product.category')->find($marketerListingId);

        if (! $listing || $listing->source_listing_id !== null || ($listing->listing_category ?? 'product') !== 'product') {
            throw new PeerPromotionException(PeerPromotionException::NOT_PEER_LISTING, 'Only an influencer\'s own FBN product listing can be promoted by another influencer.');
        }

        if ($listing->marketer_id === $promoterId) {
            throw new PeerPromotionException(PeerPromotionException::SELF_PROMOTION, 'An influencer cannot promote his own listing.');
        }

        if (! $listing->isActive()) {
            throw new PeerPromotionException(PeerPromotionException::LISTING_NOT_ACTIVE, 'The listing must be active to be promoted.');
        }

        $promoter = Marketer::query()->whereKey($promoterId)->lockForUpdate()->firstOrFail();
        if (! $promoter->isInfluencer() || ! $listing->marketer?->isInfluencer()) {
            throw new PeerPromotionException(PeerPromotionException::NOT_INFLUENCER, 'Only influencers can promote other influencers\' products.');
        }

        $minStock = max(1, (int) setting('marketer_peer_campaign_min_stock', 1));
        $stock = app(InventoryService::class)->availableStock($listing);
        if ($stock < $minStock) {
            throw new PeerPromotionException(PeerPromotionException::INSUFFICIENT_STOCK, "Insufficient stock to promote: {$stock} available, {$minStock} required.", ['available' => $stock, 'required' => $minStock]);
        }

        $existing = $this->peerPromotionInMonth($promoterId, $listing->marketer_id, $at ?? now());
        if ($existing) {
            throw new PeerPromotionException(PeerPromotionException::MONTHLY_LIMIT, 'This influencer was already promoted by you this month.', ['campaign_id' => $existing->id]);
        }

        return $listing;
    }

    /** The promoter's peer campaign for this owner influencer in the calendar month of $at, if any. */
    public function peerPromotionInMonth(string $promoterId, string $ownerMarketerId, CarbonInterface $at): ?MarketerCampaign
    {
        return MarketerCampaign::query()
            ->whereNotNull('marketer_listing_id')
            ->where('requested_by_marketer_id', $promoterId)
            ->where('owner_type', 'marketer')
            ->where('owner_id', $ownerMarketerId)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereBetween('created_at', [$at->copy()->startOfMonth(), $at->copy()->endOfMonth()])
            ->first();
    }

    /**
     * Per-unit commission of a peer promotion = the promoter's profile rule for the listing's category
     * (D12), with the platform rule applied on top. Zero is allowed: the campaign then shows a
     * "no commission configured" warning and conversions resolve the profile rule again at sale time.
     *
     * @return array{marketer_commission_amount: float, platform_commission_amount: float}
     */
    private function resolvePeerCommission(MarketerCampaign $campaign, MarketerListing $listing, Marketer $promoter): array
    {
        $category = $listing->productVariant?->product?->category;
        if (! $category) {
            return ['marketer_commission_amount' => 0.0, 'platform_commission_amount' => 0.0];
        }

        // D11/D12: an influencer has no per-sale default on the category tab; the only per-unit commission of a peer
        // promotion is the promoter's own profile rule ("Commission Rates by Category"), else 0 (no commission).
        $perUnit = (float) (app(MarketerCommissionRateService::class)
            ->resolveRule($promoter, $category->id)
            ?->resolveAmount((float) ($listing->price ?? 0), 1, true) ?? 0);

        $platform = 0.0;
        if ($perUnit > 0) {
            $rule = app(CommissionRuleResolver::class)->resolve(null, 'products', $category);
            $platform = $rule
                ? $this->computeCommissionAmount($rule->commission_mode, $rule->commission_flat_amount, (float) $rule->commission_rate, $perUnit)
                : 0.0;
        }

        return ['marketer_commission_amount' => $perUnit, 'platform_commission_amount' => $platform];
    }

    /**
     * Peer campaign goes live at once: commission from the category rules, the promoter's
     * invitation is created and accepted (no seller fee: there is no seller to charge and the
     * promotion is part of the promoter's monthly obligation), his referral listing is built.
     */
    private function activatePeerCampaign(MarketerCampaign $campaign, MarketerListing $listing): MarketerCampaign
    {
        $promoter = Marketer::findOrFail($campaign->requested_by_marketer_id);

        $campaign->update($this->resolvePeerCommission($campaign, $listing, $promoter) + [
            'status' => 'active',
            'reviewed_at' => now(),
        ]);

        $invitation = $this->dispatchInvitation($campaign, $promoter->id);
        $this->acceptInvitation($invitation->fresh());

        $listing->marketer?->marketerAdmins?->each(
            fn ($ma) => $ma->notify(new PeerPromotionStartedNotification($campaign->fresh(), $ma->id))
        );

        return $campaign->fresh();
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

        // A request form only names the listing: country/currency come from the listing itself.
        $requested = match (true) {
            $source->isVendorListing() => VendorListing::find($source->vendorListingId),
            $source->isAdminListing() => AdminListing::find($source->adminListingId),
            $source->isMarketerListing() => MarketerListing::find($source->marketerListingId),
            default => null,
        };
        $data['country_id'] ??= $requested?->country_id;
        $data['currency'] ??= $requested?->currency;
        $data['commission_type'] ??= 'fixed';
        $data['max_commission_budget'] ??= 0;

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
        // Seller-created campaigns carry no commission (it is never typed by the vendor): resolve the admin's
        // country/category default now, exactly like the auto-approval does.
        if ($campaign->commission_type !== 'tiered' && (float) $campaign->marketer_commission_amount <= 0) {
            $defaults = $this->resolveDefaultCommission($campaign);
            if ($defaults !== null) {
                $campaign->marketer_commission_amount = $defaults['marketer_commission_amount'];
                $campaign->platform_commission_amount = $defaults['platform_commission_amount'];
                $campaign->save();
            }
        }

        // Influencer campaigns are paid by the fee share, not a per-sale amount (D2/D11), so only a campaign that
        // targets affiliates (or has no marketer list to prove otherwise) needs a resolvable per-unit commission.
        if ($campaign->commission_type !== 'tiered' && (float) $campaign->marketer_commission_amount <= 0 && ! $this->isInfluencerOnly($campaign)) {
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
            $this->syncListingCampaignEnabled($campaign);

            $campaign->vendor?->vendorAdmins?->each(fn ($va) => $va->notify(new CampaignRejectedNotification($campaign)));
        });

        // P7: charged influencer invitations are refunded / voided, the marketer fee share is reversed.
        app(MarketerInvitationRefundService::class)->refundCampaign($campaign, 'campaign_rejected');
        app(AffiliateFeeSettlementService::class)->settleIfClosed($campaign, 'rejected');
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
            $this->syncListingCampaignEnabled($campaign);
        });

        // P7: charged influencer invitations are refunded / voided, the marketer fee share is reversed.
        app(MarketerInvitationRefundService::class)->refundCampaign($campaign, 'campaign_cancelled');

        // P6: a cancelled campaign settles the affiliate marketing fees accrued so far.
        app(AffiliateFeeSettlementService::class)->settleIfClosed($campaign, 'cancelled');
    }

    /**
     * Statuses after which a campaign no longer occupies its listing.
     * ('completed' is a legacy spelling of 'done'.)
     */
    public const TERMINAL_STATUSES = ['cancelled', 'rejected', 'done', 'completed'];

    /**
     * Keep listing->campaign_enabled true while ANY non-terminal campaign
     * exists for the promoted listing, and false only when none remain.
     */
    public function syncListingCampaignEnabled(MarketerCampaign $campaign): void
    {
        $listing = $campaign->vendorListing ?? $campaign->adminListing;
        if (! $listing) {
            return;
        }

        $column = $campaign->vendor_listing_id ? 'vendor_listing_id' : 'admin_listing_id';
        $listingId = $campaign->vendor_listing_id ?: $campaign->admin_listing_id;

        $hasOpen = MarketerCampaign::where($column, $listingId)
            ->whereNotIn('status', self::TERMINAL_STATUSES)
            ->exists();

        if ((bool) $listing->campaign_enabled !== $hasOpen) {
            $listing->update(['campaign_enabled' => $hasOpen]);
        }
    }

    /**
     * Edit a still-pending campaign. Marketer list changes are NOT handled
     * here (use inviteMarketers()/cancel).
     */
    public function updateCampaign(MarketerCampaign $campaign, array $data): MarketerCampaign
    {
        if (! in_array($campaign->status, ['pending_admin', 'marketer_requested'], true)) {
            throw new \RuntimeException('Only a pending campaign can be edited.');
        }

        return DB::transaction(function () use ($campaign, $data) {
            $campaign->update([
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'commission_type' => $data['commission_type'],
                'max_commission_budget' => $data['max_commission_budget'] ?? $campaign->max_commission_budget,
                'selected_ad_types' => $data['selected_ad_types'] ?? null,
                'vendor_ad_notes' => $data['vendor_ad_notes'] ?? null,
            ]);

            $campaign->tieredRules()->delete();
            if ($campaign->commission_type === 'tiered') {
                foreach (array_values($data['tiered_rules'] ?? []) as $i => $rule) {
                    MarketerCampaignTieredRule::create([
                        'campaign_id' => $campaign->id,
                        'from_sale_number' => $rule['from_sale_number'],
                        'commission_amount' => $rule['commission_amount'],
                        'currency' => $campaign->currency,
                        'sort_order' => $i,
                    ]);
                }
            }

            return $campaign->refresh();
        });
    }

    /**
     * Soft-delete a campaign. pending_admin campaigns are cancelled first so
     * invitations are cleaned up and listing->campaign_enabled is re-synced.
     */
    public function deleteCampaign(MarketerCampaign $campaign): void
    {
        if (! in_array($campaign->status, ['cancelled', 'rejected', 'done', 'completed', 'pending_admin'], true)) {
            throw new \RuntimeException('This campaign cannot be deleted in its current status.');
        }

        if ($campaign->status === 'pending_admin') {
            $this->cancelCampaign($campaign);
        }

        $campaign->delete();
        $this->syncListingCampaignEnabled($campaign);
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

        // Nothing more will be sent: undispatched samples (admin, affiliate and
        // per-influencer) are cancelled so they stop reserving stock.
        app(CampaignSampleService::class)->cancelUndispatchedForCampaign($campaign);

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

            if ($defaults === null && ! $this->isInfluencerOnly($campaign)) {
                $campaign->update(['auto_approve_at' => now()->addDays(3)]);

                Admin::query()->get()->each(
                    fn ($admin) => $admin->notify(new NewCampaignPendingNotification($campaign))
                );

                return;
            }

            if ($defaults !== null) {
                $campaign->marketer_commission_amount = $defaults['marketer_commission_amount'];
                $campaign->platform_commission_amount = $defaults['platform_commission_amount'];
            }
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
     * @return array{marketer_commission_amount: float, platform_commission_amount: float}|null
     */
    private function resolveDefaultCommission(MarketerCampaign $campaign): ?array
    {
        if (($campaign->campaign_category ?? 'product') === 'classified') {
            return $this->resolveDefaultOpenMarketCommission($campaign);
        }

        $category = $campaign->vendorListing?->productVariant?->product?->category
            ?? $campaign->adminListing?->productVariant?->product?->category;

        if (! $category) {
            return null;
        }

        $categoryRate = app(CommissionRuleResolver::class)
            ->resolve(null, 'products', $category);

        // Per-unit estimate of the category-tab AFFILIATE default (the campaign is shared across whichever marketers
        // accept; influencers have no default per-sale commission, D11) at the listing price. Percentage parts are
        // re-resolved on the real line at sale time (LastClickAttributionService).
        $listingPrice = (float) ($campaign->vendorListing?->price ?? $campaign->adminListing?->price ?? 0);
        $marketerCommission = app(AffiliateCommissionResolver::class)
            ->categoryDefault($campaign->country_id, $category->id, $listingPrice, 1);

        if ($marketerCommission <= 0) {
            return null;
        }

        $platformCommission = $categoryRate
            ? $this->computeCommissionAmount(
                $categoryRate->commission_mode,
                $categoryRate->commission_flat_amount,
                (float) $categoryRate->commission_rate,
                $marketerCommission
            )
            : 0.0;

        return [
            'marketer_commission_amount' => $marketerCommission,
            'platform_commission_amount' => $platformCommission,
        ];
    }

    /**
     * Resolve default commission for an open-market (classified listing)
     * campaign, using the classified listing's own category tree and the
     * marketer-agnostic OpenMarketCategoryCommission rules.
     *
     * @return array{marketer_commission_amount: float, platform_commission_amount: float}|null
     */
    private function resolveDefaultOpenMarketCommission(MarketerCampaign $campaign): ?array
    {
        $category = $campaign->classifiedListing?->classifiedCategory;

        if (! $category) {
            return null;
        }

        $categoryRate = app(CommissionRuleResolver::class)
            ->resolve(null, 'open_market', $category);

        $marketerCommission = app(AffiliateCommissionResolver::class)
            ->categoryDefault($campaign->country_id, $category->id, (float) ($campaign->classifiedListing?->price ?? 0), 1);

        if ($marketerCommission <= 0) {
            return null;
        }

        $platformCommission = $categoryRate
            ? $this->computeCommissionAmount(
                $categoryRate->commission_mode,
                $categoryRate->commission_flat_amount,
                (float) $categoryRate->commission_rate,
                $marketerCommission
            )
            : 0.0;

        return [
            'marketer_commission_amount' => $marketerCommission,
            'platform_commission_amount' => $platformCommission,
        ];
    }

    /**
     * Shared commission computation used by both the product-category and
     * open-market-category commission resolution paths above, mirroring
     * MarketerCategoryCommission::resolveAmount() /
     * OpenMarketCategoryCommission::resolveAmount() so all three stay
     * consistent.
     *
     * @param  string  $mode  fixed | percentage | both
     */
    private function computeCommissionAmount(string $mode, int|float|string|null $flat, float $rate, int|float|string $base): float
    {
        return (new MarketerCommissionRule([
            'commission_mode' => $mode,
            'commission_rate' => $rate,
            'commission_flat_amount' => $flat,
        ]))->resolveAmount($base, 1, true);
    }

    /**
     * Dispatch a campaign invitation to a marketer vendor.
     */
    public function dispatchInvitation(MarketerCampaign $campaign, string $marketerId, ?MarketerCampaignSample $inheritSample = null): MarketerCampaignInvitation
    {
        $timeoutHours = max(1, (int) setting('marketer_invitation_timeout_hours', 12));
        // P14: every invitation (including a replacement) gets its own fresh code, link and QR;
        // an old code is never reused so a stale scan can never reach the new marketer.
        do {
            $referralCode = strtoupper(Str::random(10));
        } while (MarketerCampaignInvitation::where('referral_code', $referralCode)->exists());

        $invitation = MarketerCampaignInvitation::create([
            'campaign_id' => $campaign->id,
            'marketer_id' => $marketerId,
            'status' => 'pending',
            'acceptance_window_hours' => $timeoutHours,
            'expires_at' => now()->addHours($timeoutHours),
            'referral_code' => $referralCode,
            'referral_link' => rtrim(config('app.frontend_url'), '/')."/r/{$referralCode}",
        ]);

        // Samples follow the invitation: an influencer gets his own sample row the
        // moment he is invited (a predecessor's undispatched sample is re-pointed,
        // never duplicated); affiliates only add the campaign-wide affiliate row.
        $this->syncInvitationSamples($campaign, $invitation, $inheritSample);

        // Generate QR code for this invitation's referral link
        $qrPath = $this->generateQrCode($invitation->referral_link, $invitation->id);
        if ($qrPath) {
            $invitation->update(['qr_code_path' => $qrPath]);
        }

        // A peer promotion (P13) is requested by the promoter himself and accepted in the same
        // transaction: no acceptance window, WhatsApp invitation or "you were invited" notice.
        if ($campaign->marketer_listing_id) {
            return $invitation;
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
     * Create the sample(s) a freshly dispatched invitation entitles to.
     * Idempotent. A sample handed over for re-pointing that is not reused
     * (affiliate replacement, no category) is cancelled.
     */
    private function syncInvitationSamples(MarketerCampaign $campaign, MarketerCampaignInvitation $invitation, ?MarketerCampaignSample $inherit = null): void
    {
        $samples = app(CampaignSampleService::class);
        $category = $samples->categoryFor($campaign);
        $marketer = $invitation->marketer;

        if ($marketer && $marketer->isInfluencer()) {
            $samples->ensureInfluencerSample($invitation, $category, $inherit);

            return;
        }

        if ($inherit) {
            $samples->cancel($inherit);
        }
        $samples->ensureAffiliateSample($campaign, $category);
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
     * Debit the per-influencer platform fee of an accepted invitation from the configured payer's wallet.
     *
     * @return bool true when the fee is now paid
     */
    public function collectInfluencerFee(MarketerCampaignInvitation $invitation, bool $throwWhenShort = false): bool
    {
        if ($invitation->platform_fee_status !== 'pending' || (float) $invitation->platform_fee_amount <= 0) {
            return false;
        }

        $payerIsVendor = $invitation->platform_fee_payer === 'vendor';
        $ownerType = $payerIsVendor ? 'vendor' : 'marketer';
        $ownerId = $payerIsVendor ? $invitation->campaign->vendor_id : $invitation->marketer_id;

        $walletService = app(WalletService::class);
        $wallet = $walletService->getOrCreateWallet($ownerType, $ownerId, $invitation->platform_fee_currency ?? $invitation->campaign->currency);

        try {
            // The invitation row is locked and its status re-read, so a concurrent
            // statement run (GenerateVendorPayoutsJob) or a second collector can never
            // settle the same fee twice: the loser sees `paid` and does nothing.
            $paid = DB::transaction(function () use ($invitation, $walletService, $wallet) {
                $locked = MarketerCampaignInvitation::whereKey($invitation->id)->lockForUpdate()->first();
                if (! $locked || $locked->platform_fee_status !== 'pending' || $locked->payoutItems()->exists()) {
                    return false;
                }

                // Fee + VAT + marketing fees leave the wallet in a single debit.
                $walletService->debit(
                    $wallet,
                    $locked->platformFeeTotal(),
                    'marketer_campaign_invitation_platform_fee',
                    $locked->id,
                    'Influencer platform fee + VAT + marketing fees for accepted campaign invitation'
                );

                $locked->update([
                    'platform_fee_status' => 'paid',
                    'platform_fee_paid_via' => 'wallet',
                    'platform_fee_paid_at' => now(),
                ]);
                $invitation->setRawAttributes($locked->getAttributes(), true);

                return true;
            });

            // After the vendor charge is committed: a failed/frozen marketer credit never undoes it (retry job).
            if ($paid) {
                app(MarketerFeePayoutService::class)->creditForInvitation($invitation);
            }

            return $paid;
        } catch (InsufficientBalanceException $e) {
            if ($throwWhenShort) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Marketer accepts an invitation. The marketer's sample already exists since
     * dispatch (legacy invitations without one get it here).
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
            // D7: the vendor always pays on vendor campaigns; only campaigns without a vendor
            // (admin-owned listings) keep the marketer-pays branch.
            $feeAmount = 0.0;
            $feeVatRate = 0.0;
            $feeVat = 0.0;
            $feeStatus = 'not_applicable';
            $feePayer = null;
            $marketingShare = 0.0;
            $sharePct = 0.0;
            $marketerPayout = 0.0;

            // Peer promotions (P13) carry no promotion fee: there is no seller to bill.
            if ($marketer->isInfluencer() && ! $campaign->marketer_listing_id) {
                $feeSetting = MarketerInfluencerFeeCountrySetting::where('country_id', $campaign->country_id)
                    ->first();

                $feeAmount = (float) ($feeSetting?->fee_per_influencer ?? 0);
                $feeStatus = $feeAmount > 0 ? 'pending' : 'waived';

                // VAT of the campaign country (countries.vat_rate), snapshotted on the invitation.
                $quoteService = app(MarketerCampaignQuoteService::class);
                $feeVatRate = $feeAmount > 0 ? $quoteService->vatRate($campaign->country_id) : 0.0;
                $feeVat = $quoteService->vatOn($feeAmount, $feeVatRate);

                $feePayer = $campaign->vendor_id ? 'vendor' : 'marketer';

                if ($feeAmount > 0 && $feePayer === 'vendor') {
                    $marketingShare = $this->allocateMarketingShare($campaign, $invitation);

                    // Fee share (D1): paid to the marketer wallet once the vendor charge is paid.
                    $split = app(MarketerFeeShareResolver::class)->split($feeAmount, $campaign->country_id, $this->campaignCategoryId($campaign));
                    $sharePct = $split['share_pct'];
                    $marketerPayout = $split['marketer_payout'];
                }
            }

            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
                'marketer_note' => $marketerNote,
                'platform_fee_amount' => $feeAmount,
                'platform_fee_currency' => $campaign->currency,
                'platform_fee_vat_rate' => $feeVatRate,
                'platform_fee_vat_amount' => $feeVat,
                'platform_fee_marketing_amount' => $marketingShare,
                'marketer_fee_share_pct' => $sharePct,
                'marketer_payout_amount' => $marketerPayout,
                'platform_fee_status' => $feeStatus,
                'platform_fee_payer' => $feeAmount > 0 ? $feePayer : null,
                'platform_fee_recorded_at' => now(),
            ]);

            // enhancement.md P-16 fix: the fee must actually be debited at acceptance time, not merely
            // recorded as 'pending'. Marketer-paid: a short balance blocks the acceptance (unchanged).
            // Vendor-paid: a short balance never blocks the promotion; the daily
            // CollectPendingInfluencerFeesJob retries until the seller tops up.
            if ($feeStatus === 'pending' && $feeAmount > 0) {
                $this->collectInfluencerFee($invitation->fresh(), throwWhenShort: $feePayer === 'marketer');
            }

            // The sample was created when the invitation was dispatched; legacy
            // invitations (created before that) get theirs now. Idempotent.
            $this->syncInvitationSamples($campaign, $invitation);

            $campaign->vendor?->vendorAdmins?->each(
                fn ($va) => $va->notify(new VendorCampaignInvitationResponseNotification($invitation, 'accepted'))
            );
            SendVendorInvitationResponseWhatsAppJob::dispatch($invitation->id, 'accepted')->afterCommit();

            $this->createMarketerListingFromInvitation($invitation);
        });
    }

    /**
     * Marketing-fee share of one accepted influencer invitation (plan 4.3), from the campaign snapshot taken at
     * creation: snapshot marketing total / N, N = influencer invitations requested (replacements do not count
     * twice). The last accepted invitation takes the rounding remainder, so accepting all of them sums to the
     * snapshot exactly. The campaign row is locked so concurrent acceptances cannot over-allocate.
     */
    private function allocateMarketingShare(MarketerCampaign $campaign, MarketerCampaignInvitation $invitation): float
    {
        $locked = MarketerCampaign::whereKey($campaign->id)->lockForUpdate()->first() ?? $campaign;
        $total = (float) $locked->marketing_fee_total;
        if ($total <= 0) {
            return 0.0;
        }

        $replacedIds = MarketerCampaignInvitation::where('campaign_id', $campaign->id)
            ->whereNotNull('replaced_invitation_id')
            ->pluck('replaced_invitation_id');
        $requested = max(1, MarketerCampaignInvitation::where('campaign_id', $campaign->id)
            ->whereNotIn('id', $replacedIds)
            ->whereHas('marketer.marketerJobs', fn ($q) => $q->where('key', 'influencer'))
            ->count());

        $others = MarketerCampaignInvitation::where('campaign_id', $campaign->id)
            ->where('status', 'accepted')
            ->where('platform_fee_amount', '>', 0)
            ->where('id', '!=', $invitation->id);
        $acceptedBefore = (clone $others)->count();
        $remaining = max(0.0, round($total - (float) (clone $others)->sum('platform_fee_marketing_amount'), 2));

        return $acceptedBefore + 1 >= $requested
            ? $remaining
            : min($remaining, round($total / $requested, 2));
    }

    private function campaignCategoryId(MarketerCampaign $campaign): ?string
    {
        return $campaign->vendorListing?->productVariant?->product?->category_id
            ?? $campaign->adminListing?->productVariant?->product?->category_id;
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
        } elseif ($campaign->marketer_listing_id) {
            // P13: the proxy sells the owner influencer's stock at the owner's price.
            $source = MarketerListing::find($campaign->marketer_listing_id);
            $sourcePrice = $source?->price;
            $sourceCondition = $source?->condition ?? 'new';
            $variantId = $source?->product_variant_id;
            $sourceType = 'marketer_listing';
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
        $windowStart = now()->subDays(30);

        // Replacement = same job type, active, never invited to this campaign, and the one
        // who received the fewest invitations in the last 30 days (spreads work evenly).
        $replacement = Marketer::whereHas('marketerJobs', fn ($q) => $q->whereIn('key', $oldJobKeys))
            ->whereNotIn('id', $alreadyInvited)
            ->where('global_status', 'active')
            ->withCount([
                'campaignInvitations as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
                'campaignInvitations as recent_requests_count' => fn ($q) => $q->where('created_at', '>=', $windowStart),
            ])
            ->having('accepted_count', '>=', $minAccepted)
            ->orderBy('recent_requests_count', 'asc')
            ->orderBy('accepted_count', 'asc')
            ->first();

        $oldInvitation->marketer->marketerAdmins->each(
            fn ($ma) => $ma->notify(new CampaignInvitationRejectedNotification($oldInvitation))
        );

        $reason = $oldInvitation->status === 'timed_out' ? 'timed_out' : 'rejected';
        // Vendor: tell them the marketer rejected / timed out and a replacement is being searched.
        $campaign->vendor?->vendorAdmins?->each(
            fn ($va) => $va->notify(new VendorCampaignInvitationResponseNotification($oldInvitation, $reason))
        );
        SendVendorInvitationResponseWhatsAppJob::dispatch($oldInvitation->id, $reason);

        // The predecessor's sample has not been sent (admin dispatch requires an
        // accepted invitation): hand it to the replacement or cancel it.
        $sampleService = app(CampaignSampleService::class);
        $freedSample = $sampleService->undispatchedFor($oldInvitation);

        $notifyAdmins = fn (?Marketer $new) => Admin::query()->get()->each(
            fn ($admin) => $admin->notify(new CampaignMarketerReassignedNotification($campaign, $oldMarketer, $new, $reason))
        );

        if (! $replacement) {
            if ($freedSample) {
                $sampleService->cancel($freedSample);
            }
            $notifyAdmins(null);

            return; // No replacement available
        }

        $newInvitation = $this->dispatchInvitation($campaign, $replacement->id, $freedSample);
        $newInvitation->update(['replaced_invitation_id' => $oldInvitation->id]);
        // P14 follow-up: the old invitation keeps its real outcome (rejected / timed_out: the marketer views,
        // vendor notices and decline stats key on it) and is only stamped here. Grace attribution stays anchored
        // on responded_at (the moment it stopped being the live holder), so this stamp never changes who is credited.
        $oldInvitation->update(['replaced_at' => now()]);

        $campaign->vendor?->vendorAdmins?->each(
            fn ($va) => $va->notify(new VendorMarketerReplacedNotification($campaign, $oldMarketer, $replacement))
        );
        $notifyAdmins($replacement);
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
            $this->syncListingCampaignEnabled($campaign);
        });

        // P6: a finished campaign settles the affiliate marketing fees accrued so far.
        app(AffiliateFeeSettlementService::class)->settleIfClosed($campaign, 'finished');

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

        // Notify marketer admins that the campaign has expired/ended.
        $campaign->invitations()->where('status', 'accepted')->with('marketer.marketerAdmins')->get()
            ->each(fn ($inv) => $inv->marketer->marketerAdmins->each(
                fn ($va) => $va->notify(new CampaignExpired($campaign, $va->id))
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
