<?php

namespace App\Services;

use App\Enums\ClassifiedListingStatus;
use App\Enums\CommissionVertical;
use App\Enums\MarketerType;
use App\Enums\TravelPackageStatus;
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
use App\Models\MarketerListing;
use App\Models\TravelPackage;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\TravelAgency;
use App\Notifications\Admin\CampaignMarketerReassignedNotification;
use App\Notifications\Admin\NewCampaignPendingNotification;
use App\Notifications\Marketer\CampaignExpired;
use App\Notifications\TravelAgency\CampaignUpdated;
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
use App\Services\Marketer\CampaignFeeService;
use App\Services\Marketer\InfluencerFixedService;
use App\Services\Marketer\MarketerCommissionCalculator;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use App\Support\Marketer\CampaignSubject;
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
    public function __construct(
        private readonly InfluencerFixedService $influencerFixed,
        private readonly CampaignFeeService $campaignFees,
    ) {}

    /** Default for the `marketer_campaign_allowed_fulfilment_models` setting (cross_dock = billed as FBM). */
    public const DEFAULT_ALLOWED_FULFILMENT_MODELS = ['fbn', 'fbm', 'cross_dock'];

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
     *
     * v3 money model ($data keys): max_commission_budget (required > 0 for vendor campaigns), marketer_ids (everyone
     * invited), influencer_ids (the influencers among them whose fixed amount is snapshotted and reserved from the
     * budget; rejected when their total exceeds it), open_to_affiliates, ends_at (optional), title, notes, ad fields.
     * Commission is never typed: affiliates / brokers earn per sale by their own rule, influencers a fixed amount.
     */
    public function createCampaign(CampaignOwner $owner, CampaignSource $source, array $data): MarketerCampaign
    {
        $source->validate();

        return DB::transaction(function () use ($owner, $source, $data) {
            $category = null;
            $listing = null;
            $subject = null;

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
            // Classified listings and trips: no stock or fulfilment, but the item must be live and belong to the owner.
            if ($source->isClassifiedListing() || $source->isTravelPackage()) {
                $subject = $this->assertEligibleSubject($owner, $source);
                $data['country_id'] = $subject->countryId;
                $data['currency'] = $subject->currency;
            }

            $marketerVendors = Marketer::whereIn('id', $data['marketer_ids'] ?? [])
                ->where('global_status', 'active')
                ->with('marketerJobs')
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
                $data['max_commission_budget'] = $data['max_commission_budget'] ?? 0;
            }

            $budget = round((float) ($data['max_commission_budget'] ?? 0), 2);
            $ownerFunded = ($listing instanceof VendorListing || $subject) && $owner->isOwnerFunded();
            if ($ownerFunded && $budget <= 0) {
                throw new \RuntimeException(__('marketer_campaigns.errors.budget_required'));
            }

            // Owner-funded campaigns (vendor listings, classified listings, trips) snapshot the server-side campaign
            // fee (never client numbers) so later setting changes do not rewrite what the owner was shown;
            // platform/marketer-owned ones carry no charge.
            $feeSnapshot = $ownerFunded ? $this->feeSnapshot($listing ?? $subject->model, $budget) : [];
            $feeSnapshot['campaign_fee_status'] = $this->campaignFees->initialStatus((float) ($feeSnapshot['marketing_fee_total'] ?? 0), $ownerFunded);

            $campaign = MarketerCampaign::create($feeSnapshot + [
                // A travel agency owns its campaign (owner_type travel_agency, owner_id = agency) with no vendor.
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
                'commission_type' => MarketerCampaign::COMMISSION_TYPE,
                'max_commission_budget' => $budget,
                'open_to_affiliates' => (bool) ($data['open_to_affiliates'] ?? false),
                'ends_at' => $data['ends_at'] ?? null,
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

            // The influencers' fixed amounts are frozen now and held back from the budget (rejected when they do not fit).
            // By default every influencer-only marketer among the invitees is an influencer slot.
            $influencerIds = array_key_exists('influencer_ids', $data)
                ? array_values(array_intersect((array) $data['influencer_ids'], $marketerVendors->pluck('id')->all()))
                : $marketerVendors->filter(fn (Marketer $m) => $this->actsAsInfluencer($m))->pluck('id')->values()->all();
            if (! $isPeer && $influencerIds !== []) {
                $this->influencerFixed->reserve(
                    $campaign,
                    $this->influencerFixed->snapshot($marketerVendors->whereIn('id', $influencerIds), $campaign->country_id, $campaign)
                );
            }

            if ($listing) {
                $listing->update(['campaign_enabled' => true]);
            }
            if ($subject) {
                $subject->model->update(['campaign_enabled' => true]);
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
            $this->notifyOwner($campaign, fn () => new CampaignPendingAdminNotification($campaign), 'pending');

            ProcessCampaignAutoApproveJob::dispatch($campaign->id)
                ->delay(now()->addHours($autoApproveHours));

            return $campaign;
        });
    }

    /**
     * Campaign fee snapshot columns: the fixed + variable fee on the Max Commission Budget (the campaign total).
     *
     * @return array<string, mixed>
     */
    private function feeSnapshot(VendorListing|ClassifiedListing|TravelPackage $listing, float $budget): array
    {
        $subject = CampaignSubject::of($listing);
        $quote = app(MarketerCampaignQuoteService::class)->quote($subject, [], $budget);

        return [
            'fulfillment_type' => $subject->feeType()?->value,
            'campaign_subtotal' => $quote['campaign_subtotal'],
            'marketing_fee_fixed' => $quote['marketing']['fixed_fee'],
            'marketing_fee_variable' => $quote['marketing']['variable_fee'],
            'marketing_fee_total' => $quote['marketing']['total'],
            'campaign_grand_total' => $quote['campaign_grand_total'],
        ];
    }

    /**
     * Owner/eligibility rules of a classified-listing or trip campaign (the counterpart of the vendor-listing rules):
     *
     *  - a vendor may promote only its own ACTIVE, unexpired classified listing and must be a classified vendor;
     *  - a travel agency may promote only its own ACTIVE trip that has not departed yet and still has seats;
     *  - the platform (admin) and marketer requests may promote any such item.
     *
     * @throws \RuntimeException with a translated message when the item cannot be promoted
     */
    public function assertEligibleSubject(CampaignOwner $owner, CampaignSource $source): CampaignSubject
    {
        if ($source->isClassifiedListing()) {
            $listing = ClassifiedListing::forVendors()->findOrFail($source->classifiedListingId);

            if ($owner->isVendor() && (string) $listing->seller_id !== (string) $owner->id) {
                throw new \RuntimeException(__('marketer_campaigns.errors.not_your_item'));
            }
            if ($owner->isTravelAgency()) {
                throw new \RuntimeException(__('marketer_campaigns.errors.owner_vertical_mismatch'));
            }
            if ($listing->status !== ClassifiedListingStatus::Active || $listing->isExpired()) {
                throw new \RuntimeException(__('marketer_campaigns.errors.classified_not_active'));
            }

            return CampaignSubject::of($listing);
        }

        $package = TravelPackage::with('agency')->findOrFail($source->travelPackageId);

        if ($owner->isTravelAgency() && (string) $package->travel_agency_id !== (string) $owner->id) {
            throw new \RuntimeException(__('marketer_campaigns.errors.not_your_item'));
        }
        if ($owner->isVendor()) {
            throw new \RuntimeException(__('marketer_campaigns.errors.owner_vertical_mismatch'));
        }
        if ($package->status !== TravelPackageStatus::Active || ($package->departure_date && $package->departure_date->isPast() && ! $package->departure_date->isToday())) {
            throw new \RuntimeException(__('marketer_campaigns.errors.trip_not_active'));
        }
        if ($package->seatsRemaining() === 0) {
            throw new \RuntimeException(__('marketer_campaigns.errors.trip_sold_out'));
        }
        if (! $package->agency?->country_id) {
            throw new \RuntimeException(__('marketer_campaigns.errors.agency_country_missing'));
        }

        return CampaignSubject::of($package);
    }

    /**
     * Owner-side notices: the vendor's admins (vendor campaigns) or the agency's active members (trip campaigns).
     * $vendorNotification builds the vendor notification lazily; agencies get a CampaignUpdated carrying $event.
     */
    private function notifyOwner(MarketerCampaign $campaign, \Closure $vendorNotification, string $event): void
    {
        if ($campaign->vendor_id) {
            $campaign->vendor?->vendorAdmins?->each(fn ($va) => $va->notify($vendorNotification()));

            return;
        }

        if ($campaign->owner_type === MarketerCampaign::OWNER_TRAVEL_AGENCY && $campaign->owner_id) {
            TravelAgency::find($campaign->owner_id)?->activeMembers()
                ->each(fn ($member) => $member->notify(new CampaignUpdated($campaign, $event)));
        }
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
     * Peer campaign goes live at once: the promoter's
     * invitation is created and accepted (no seller fee: there is no seller to charge and the
     * promotion is part of the promoter's monthly obligation), his referral listing is built.
     */
    private function activatePeerCampaign(MarketerCampaign $campaign, MarketerListing $listing): MarketerCampaign
    {
        $promoter = Marketer::findOrFail($campaign->requested_by_marketer_id);

        $campaign->update([
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
        $data['max_commission_budget'] ??= 0;

        return $this->createCampaign(CampaignOwner::marketer($marketer), $source, $data);
    }

    /**
     * enhancement.md P-14 task 3: the listing owner (vendor or admin) approves a marketer-originated request: sets
     * the Max Commission Budget (`max_commission_budget`), flips ownership to the real owner, activates the campaign
     * and auto-accepts the requesting marketer's invitation (they already asked to promote it).
     *
     * An influencer requester is reserved his fixed amount out of that budget (rejected when it does not fit);
     * an affiliate / broker earns per sale from the budget like everyone else.
     *
     * @param  array{max_commission_budget: int|float|string}  $data
     */
    public function approveMarketerRequest(MarketerCampaign $campaign, array $data): void
    {
        if ($campaign->status !== 'marketer_requested') {
            throw new \RuntimeException('Only a marketer_requested campaign can be approved this way.');
        }

        $budget = round((float) ($data['max_commission_budget'] ?? 0), 2);
        if ($budget <= 0) {
            throw new \RuntimeException(__('marketer_campaigns.errors.budget_required'));
        }

        DB::transaction(function () use ($campaign, $budget) {
            $ownerType = $campaign->admin_listing_id ? 'platform' : 'vendor';
            $ownerId = $ownerType === 'vendor' ? $campaign->vendorListing?->vendor_id : null;

            $fee = $ownerType === 'vendor' && $campaign->vendorListing
                ? $this->feeSnapshot($campaign->vendorListing, $budget)
                : [];
            $fee['campaign_fee_status'] = $this->campaignFees->initialStatus((float) ($fee['marketing_fee_total'] ?? 0), $ownerType === 'vendor');

            $campaign->update($fee + [
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'vendor_id' => $ownerId,
                'max_commission_budget' => $budget,
                'status' => 'active',
                'reviewed_at' => now(),
            ]);

            $requester = Marketer::with('marketerJobs')->findOrFail($campaign->requested_by_marketer_id);
            if ($this->actsAsInfluencer($requester)) {
                $this->influencerFixed->reserve($campaign, $this->influencerFixed->snapshot([$requester], $campaign->country_id, $campaign));
            }

            $invitation = $this->dispatchInvitation($campaign, $campaign->requested_by_marketer_id);
            $this->acceptInvitation($invitation->fresh());
        });

        $this->campaignFees->charge($campaign->fresh());
    }

    /** True when the marketer earns a fixed amount per campaign (influencer only, not also affiliate / broker). */
    private function actsAsInfluencer(Marketer $marketer): bool
    {
        $held = MarketerType::heldBy($marketer);

        return in_array(MarketerType::Influencer, $held, true)
            && ! in_array(MarketerType::Affiliate, $held, true)
            && ! in_array(MarketerType::Broker, $held, true);
    }

    /**
     * Admin approves a campaign: it goes live, invitations are dispatched, and the campaign fee is charged
     * (vendor wallet first, else the next payout statement).
     */
    public function approveCampaign(MarketerCampaign $campaign, Admin $admin): void
    {
        $this->goLive($campaign, [
            'status' => 'active',
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ], fn () => $this->notifyOwner(
            $campaign,
            fn () => new CampaignApprovedNotification($campaign, $campaign->invitations()->count()),
            'approved'
        ));
    }

    /**
     * Shared by manual and automatic approval: flip the status, dispatch the invitations (not before: a marketer
     * must never hold a live link to a campaign that is later rejected), notify, then charge the campaign fee.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function goLive(MarketerCampaign $campaign, array $attributes, \Closure $notify): void
    {
        DB::transaction(function () use ($campaign, $attributes, $notify) {
            $campaign->update($attributes);

            foreach ((array) ($campaign->requested_marketer_vendor_ids ?? []) as $marketerId) {
                $this->dispatchInvitation($campaign, $marketerId);
            }

            $notify();
        });

        $this->campaignFees->charge($campaign->fresh());
    }

    /**
     * Admin rejects a campaign.
     *
     * enhancement.md P-14 task 4: rejection cancels any pending invitations and pauses/archives whatever marketer
     * listings already exist for this campaign. The influencers' reserved fixed amounts go back to the budget and an
     * unpaid campaign fee is voided.
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
            $this->releaseUnpaidMoney($campaign);
            $this->syncListingCampaignEnabled($campaign);

            $this->notifyOwner($campaign, fn () => new CampaignRejectedNotification($campaign), 'rejected');
        });
    }

    /**
     * enhancement.md P-14 task 4: cancel a campaign (vendor/admin self-service cancel, distinct from admin
     * rejection): same cleanup as rejection. Influencers are NOT paid on a cancelled campaign (not fulfilled).
     */
    public function cancelCampaign(MarketerCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => 'cancelled']);
            $this->cancelPendingInvitationsAndArchiveListings($campaign);
            $this->releaseUnpaidMoney($campaign);
            $this->syncListingCampaignEnabled($campaign);
        });
    }

    /** Reject / cancel: reserved influencer amounts return to the budget; an unpaid campaign fee is never charged. */
    private function releaseUnpaidMoney(MarketerCampaign $campaign): void
    {
        $campaign->invitations()
            ->where('influencer_fixed_status', MarketerCampaignInvitation::FIXED_RESERVED)
            ->get()
            ->each(fn (MarketerCampaignInvitation $invitation) => $this->influencerFixed->release($invitation, $campaign));

        // Pending campaigns have a reservation on the campaign row but no invitations yet: only paid amounts stay.
        $campaign->update([
            'influencer_fixed_reserved' => round((float) $campaign->invitations()
                ->where('influencer_fixed_status', MarketerCampaignInvitation::FIXED_PAID)
                ->sum('influencer_fixed_amount'), 2),
        ]);

        $this->campaignFees->void($campaign);
    }

    /**
     * Statuses after which a campaign no longer occupies its listing.
     * ('completed' is a legacy spelling of 'done'.)
     */
    public const TERMINAL_STATUSES = ['cancelled', 'rejected', 'done', 'completed'];

    /**
     * Keep campaign_enabled true on the promoted item (vendor listing, admin listing, classified listing or trip)
     * while ANY non-terminal campaign exists for it, and false only when none remain.
     */
    public function syncListingCampaignEnabled(MarketerCampaign $campaign): void
    {
        [$listing, $column, $listingId] = match ($campaign->campaign_category) {
            'classified' => [$campaign->classifiedListing, 'classified_listing_id', $campaign->classified_listing_id],
            'travel' => [$campaign->travelPackage, 'travel_package_id', $campaign->travel_package_id],
            default => $campaign->vendor_listing_id
                ? [$campaign->vendorListing, 'vendor_listing_id', $campaign->vendor_listing_id]
                : [$campaign->adminListing, 'admin_listing_id', $campaign->admin_listing_id],
        };
        if (! $listing || ! $listingId) {
            return;
        }

        $hasOpen = MarketerCampaign::where($column, $listingId)
            ->whereNotIn('status', self::TERMINAL_STATUSES)
            ->exists();

        if ((bool) $listing->campaign_enabled !== $hasOpen) {
            $listing->update(['campaign_enabled' => $hasOpen]);
        }
    }

    /**
     * The category the campaign's commission rules and fees are looked up by (product category, classified
     * category; none for trips). Single answer shared by the calculator, the wizard and the reports.
     */
    public function campaignCategoryId(MarketerCampaign $campaign): ?string
    {
        return app(MarketerCommissionCalculator::class)->categoryIdFor($campaign);
    }

    /**
     * Edit a still-pending campaign: title, notes, ad types, end date and the Max Commission Budget. A new budget
     * re-snapshots the campaign fee and must still cover the influencers' reserved fixed amounts.
     * Marketer list changes are NOT handled here (use inviteMarketers()/cancel).
     */
    public function updateCampaign(MarketerCampaign $campaign, array $data): MarketerCampaign
    {
        if (! in_array($campaign->status, ['pending_admin', 'marketer_requested'], true)) {
            throw new \RuntimeException('Only a pending campaign can be edited.');
        }

        return DB::transaction(function () use ($campaign, $data) {
            $attributes = [
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'selected_ad_types' => $data['selected_ad_types'] ?? null,
                'vendor_ad_notes' => $data['vendor_ad_notes'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
            ];

            if (isset($data['max_commission_budget']) && $campaign->status === 'pending_admin') {
                $budget = round((float) $data['max_commission_budget'], 2);
                if ($budget <= 0) {
                    throw new \RuntimeException(__('marketer_campaigns.errors.budget_required'));
                }
                if ($budget + 0.004 < (float) $campaign->influencer_fixed_reserved) {
                    throw new \RuntimeException(__('marketer_campaigns.errors.fixed_exceeds_budget', [
                        'fixed' => \App\Support\Money::format($campaign->influencer_fixed_reserved), 'budget' => \App\Support\Money::format($budget),
                    ]));
                }

                $attributes['max_commission_budget'] = $budget;
                $subject = CampaignSubject::fromCampaign($campaign);
                $ownerFunded = $campaign->vendor_id || $campaign->owner_type === MarketerCampaign::OWNER_TRAVEL_AGENCY;
                if ($subject && $ownerFunded) {
                    $fee = $this->feeSnapshot($subject->model, $budget);
                    $attributes += $fee + ['campaign_fee_status' => $this->campaignFees->initialStatus((float) $fee['marketing_fee_total'], true)];
                }
            }

            $campaign->update($attributes);

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
     * Auto-approve a campaign (called by job after the configured timeout). v3: nothing to resolve (no typed
     * commission), so a pending campaign simply goes live.
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

        $this->goLive($campaign, [
            'status' => 'auto_approved',
            'auto_approved' => true,
            'reviewed_at' => now(),
        ], fn () => $this->notifyOwner($campaign, fn () => new CampaignAutoApprovedNotification($campaign), 'auto_approved'));
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

        // The influencer's fixed amount, frozen at campaign creation, travels with his invitation.
        $this->influencerFixed->applyTo($campaign, $invitation);

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

        $marketers = Marketer::whereIn('id', $marketerIds)->with('marketerJobs')->get()->keyBy('id');

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

            // An influencer added to a running campaign is reserved his own fixed amount; no room left = not invited.
            if ($this->actsAsInfluencer($marketer) && ! $this->influencerFixed->reserveFor($campaign, $marketer)) {
                $skipped[$marketerId] = 'budget_exceeded';

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

            // An influencer's reserved fixed amount stays reserved: it is paid at campaign completion if he is
            // still on the campaign (accepted) by then, and released back to the budget otherwise.
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
                'marketer_note' => $marketerNote,
            ]);

            // The sample was created when the invitation was dispatched; legacy
            // invitations (created before that) get theirs now. Idempotent.
            $this->syncInvitationSamples($campaign, $invitation);

            $this->notifyOwner($campaign, fn () => new VendorCampaignInvitationResponseNotification($invitation, 'accepted'), 'invitation_accepted');
            SendVendorInvitationResponseWhatsAppJob::dispatch($invitation->id, 'accepted')->afterCommit();

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
        $influencerSlot = $oldInvitation->influencer_fixed_status === MarketerCampaignInvitation::FIXED_RESERVED;

        // An open campaign already reached every eligible affiliate / broker: there is nobody left to replace one with.
        $replacement = ! $influencerSlot && $campaign->open_to_affiliates ? null : Marketer::whereHas('marketerJobs', fn ($q) => $q->whereIn('key', $oldJobKeys))
            ->whereNotIn('id', $alreadyInvited)
            ->where('global_status', 'active')
            ->when($campaign->country_id, fn ($q) => $q->where('country_id', $campaign->country_id))
            ->when($influencerSlot, fn ($q) => $q->whereHas('marketerJobs', fn ($j) => $j->where('key', MarketerType::Influencer->value)))
            ->withCount([
                'campaignInvitations as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
                'campaignInvitations as recent_requests_count' => fn ($q) => $q->where('created_at', '>=', $windowStart),
            ])
            ->having('accepted_count', '>=', $minAccepted)
            ->orderBy('recent_requests_count', 'asc')
            ->orderBy('accepted_count', 'asc')
            ->first();

        // A replacement influencer is reserved HIS OWN fixed amount (the predecessor's goes back to the budget first);
        // when it does not fit the remaining budget the slot is not refilled.
        if ($influencerSlot) {
            if ($replacement && ! $this->influencerFixed->reserveFor($campaign, $replacement, $oldInvitation)) {
                $replacement = null;
            }
            $this->influencerFixed->release($oldInvitation->fresh());
            $campaign->refresh();
        }

        $oldInvitation->marketer->marketerAdmins->each(
            fn ($ma) => $ma->notify(new CampaignInvitationRejectedNotification($oldInvitation))
        );

        $reason = $oldInvitation->status === 'timed_out' ? 'timed_out' : 'rejected';
        // Vendor: tell them the marketer rejected / timed out and a replacement is being searched.
        $this->notifyOwner($campaign, fn () => new VendorCampaignInvitationResponseNotification($oldInvitation, $reason), 'invitation_'.$reason);
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

        $this->notifyOwner($campaign, fn () => new VendorMarketerReplacedNotification($campaign, $oldMarketer, $replacement), 'marketer_replaced');
        $notifyAdmins($replacement);
    }

    /**
     * Close a campaign for good: stock gone, Max Commission Budget used up, or the end date reached.
     *
     * Pending invitations are cancelled, marketer listings archived, and the influencers are settled: every
     * influencer still on the campaign (accepted) is paid his fixed amount net of the commission fee; the others
     * get their reservation released. Idempotent.
     */
    public function markCampaignDone(MarketerCampaign $campaign): void
    {
        $campaign->refresh();
        if (in_array($campaign->status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => 'done']);
            // enhancement.md P-14 task 4: done cancels pending invitations
            // and archives marketer listings, same as reject/cancel.
            $this->cancelPendingInvitationsAndArchiveListings($campaign);
            $this->influencerFixed->settleCampaign($campaign);
            $this->syncListingCampaignEnabled($campaign);
        });

        $totalConversions = (int) $campaign->conversions()->count();
        $totalCommissionEarned = (float) $campaign->conversions()->sum('commission_amount') + (float) $campaign->invitations()->sum('influencer_fixed_amount');

        $this->notifyOwner($campaign, fn () => new CampaignDoneNotification($campaign, $totalConversions, $totalCommissionEarned), 'done');

        $campaign->invitations()->where('status', 'accepted')->with('marketer.marketerAdmins')->get()
            ->each(fn ($inv) => $inv->marketer->marketerAdmins->each(function ($va) use ($campaign, $inv) {
                $va->notify(new CampaignDoneNotification(
                    $campaign,
                    (int) $inv->total_conversions,
                    (float) $inv->total_commission_earned
                ));
                // Notify marketer admins that the campaign has expired/ended.
                $va->notify(new CampaignExpired($campaign, $va->id));
            }));
    }

    /**
     * Daily sweep (CompleteEndedMarketerCampaignsJob): campaigns whose end date passed are completed, which pays
     * the fulfilled influencers.
     *
     * @return int campaigns completed
     */
    public function completeEndedCampaigns(): int
    {
        $count = 0;

        MarketerCampaign::whereIn('status', ['active', 'auto_approved', 'paused'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->each(function (MarketerCampaign $campaign) use (&$count) {
                $this->markCampaignDone($campaign);
                $count++;
            });

        return $count;
    }

    /**
     * An affiliate / broker joins a campaign that is open to all registered affiliates and brokers.
     * Returns the (existing or new) invitation, already accepted. Influencers cannot join: they are picked by the vendor.
     */
    public function joinOpenCampaign(MarketerCampaign $campaign, Marketer $marketer): MarketerCampaignInvitation
    {
        if (! $campaign->open_to_affiliates || ! in_array($campaign->status, ['active', 'auto_approved'], true)) {
            throw new \RuntimeException(__('marketer_campaigns.errors.not_open'));
        }
        if ($this->actsAsInfluencer($marketer) || $marketer->country_id !== $campaign->country_id) {
            throw new \RuntimeException(__('marketer_campaigns.errors.not_eligible'));
        }

        $existing = $campaign->invitations()->where('marketer_id', $marketer->id)->whereIn('status', ['pending', 'accepted'])->first();
        if ($existing) {
            if ($existing->status === 'pending') {
                $this->acceptInvitation($existing);
            }

            return $existing->fresh();
        }

        $invitation = $this->dispatchInvitation($campaign, $marketer->id);
        $this->acceptInvitation($invitation->fresh());

        return $invitation->fresh();
    }

    /**
     * enhancement.md P-14 task 4: resolve a campaign's source and react. The sweep (MonitorCampaignStockJob) and the
     * ListingStockChanged listener call this for every live campaign; each vertical has its own "no longer
     * sellable" test:
     *
     *  product     0 stock marks the campaign done, below min_stock_for_campaign pauses it, back above resumes it;
     *  classified  a sold / expired / rejected listing is done (a sale ends the campaign); a paused listing pauses
     *              the campaign and an active one resumes it;
     *  travel      a sold-out, expired or departed trip is done; a trip pulled back from sale pauses the campaign
     *              and an active one resumes it.
     */
    public function checkStockAndUpdateStatus(MarketerCampaign $campaign): void
    {
        match ($campaign->campaign_category) {
            'classified' => $this->checkClassifiedAvailability($campaign),
            'travel' => $this->checkTravelAvailability($campaign),
            default => $this->checkProductStock($campaign),
        };
    }

    private function checkProductStock(MarketerCampaign $campaign): void
    {
        $listing = $campaign->vendor_listing_id
            ? $campaign->vendorListing
            : ($campaign->admin_listing_id ? $campaign->adminListing : null);

        if (! $listing) {
            return;
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

        // A campaign that used up its budget or reached its end date is over: stock coming back never reopens it.
        if ($stock >= $minStock && $campaign->status === 'paused' && $this->mayResume($campaign)) {
            $this->resumeCampaignAfterRestock($campaign);
        }
    }

    private function checkClassifiedAvailability(MarketerCampaign $campaign): void
    {
        $listing = $campaign->classifiedListing;
        if (! $listing || ! in_array($campaign->status, ['active', 'auto_approved', 'paused'], true)) {
            return;
        }

        if (in_array($listing->status, [ClassifiedListingStatus::Sold, ClassifiedListingStatus::Expired, ClassifiedListingStatus::Rejected], true) || $listing->isExpired()) {
            $this->markCampaignDone($campaign);

            return;
        }

        if ($listing->status === ClassifiedListingStatus::Paused && $campaign->status !== 'paused') {
            $this->pauseCampaignForLowStock($campaign);
        } elseif ($listing->status === ClassifiedListingStatus::Active && $campaign->status === 'paused' && $this->mayResume($campaign)) {
            $this->resumeCampaignAfterRestock($campaign);
        }
    }

    private function checkTravelAvailability(MarketerCampaign $campaign): void
    {
        $package = $campaign->travelPackage;
        if (! $package || ! in_array($campaign->status, ['active', 'auto_approved', 'paused'], true)) {
            return;
        }

        $departed = $package->departure_date && $package->departure_date->isPast() && ! $package->departure_date->isToday();
        if ($departed || in_array($package->status, [TravelPackageStatus::SoldOut, TravelPackageStatus::Expired], true) || $package->seatsRemaining() === 0) {
            $this->markCampaignDone($campaign);

            return;
        }

        if ($package->status !== TravelPackageStatus::Active && $campaign->status !== 'paused') {
            $this->pauseCampaignForLowStock($campaign);
        } elseif ($package->status === TravelPackageStatus::Active && $campaign->status === 'paused' && $this->mayResume($campaign)) {
            $this->resumeCampaignAfterRestock($campaign);
        }
    }

    /** A budget-exhausted or past-its-end-date campaign is over: availability coming back never reopens it. */
    private function mayResume(MarketerCampaign $campaign): bool
    {
        return ! $campaign->isBudgetExhausted() && ! ($campaign->ends_at && $campaign->ends_at->isPast());
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
