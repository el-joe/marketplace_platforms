<?php

namespace App\Services\Marketer;

use App\Enums\CommissionVertical;
use App\Enums\MarketerType;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Services\MarketerCampaignQuoteService;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSubject;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The submit side of the shared campaign wizard, identical for every owner and vertical: a product vendor, a classified
 * vendor and a travel agency all post the same fields (marketer_ids, open_to_affiliates, max_commission_budget,
 * ends_at, ad fields) and get the same rules:
 *
 *   - picked marketers must be influencers of the item's country (the wizard only offers those);
 *   - nobody may already be tied to another live campaign of the same item;
 *   - "open to all" reaches every eligible affiliate / broker of the country (never an influencer slot);
 *   - the influencers' fixed amounts must fit inside the Max Commission Budget.
 *
 * Panels only differ in how they authorise the request and where they redirect afterwards.
 */
class CampaignWizardService
{
    public function __construct(
        private readonly MarketerCampaignService $campaigns,
        private readonly MarketerCampaignQuoteService $quotes,
    ) {}

    /** Column of marketer_campaigns holding the promoted item of a subject. */
    public static function itemColumn(CampaignSubject $subject): string
    {
        return match ($subject->vertical) {
            CommissionVertical::Classified => 'classified_listing_id',
            CommissionVertical::Travel => 'travel_package_id',
            default => 'vendor_listing_id',
        };
    }

    /**
     * Marketers already tied to another non-terminal campaign of the same item: either a pending/accepted invitation,
     * or (for campaigns still awaiting review, which have no invitations yet) a requested marketer.
     *
     * @param  array<int, string>  $marketerIds
     * @return array<string, string> marketer id => name
     */
    public function conflictingMarketers(string $itemColumn, string $itemId, array $marketerIds, ?string $exceptCampaignId = null): array
    {
        if ($marketerIds === []) {
            return [];
        }

        $taken = [];
        MarketerCampaign::where($itemColumn, $itemId)
            ->whereNotIn('status', MarketerCampaignService::TERMINAL_STATUSES)
            ->when($exceptCampaignId, fn ($q) => $q->where('id', '!=', $exceptCampaignId))
            ->with(['invitations' => fn ($q) => $q->whereIn('status', ['pending', 'accepted'])])
            ->get()
            ->each(function (MarketerCampaign $campaign) use (&$taken) {
                foreach ($campaign->invitations as $invitation) {
                    $taken[$invitation->marketer_id] = true;
                }
                if (in_array($campaign->status, ['pending_admin', 'marketer_requested'], true)) {
                    foreach ((array) $campaign->requested_marketer_vendor_ids as $id) {
                        $taken[$id] = true;
                    }
                }
            });

        $conflictIds = array_values(array_intersect($marketerIds, array_keys($taken)));

        return $conflictIds ? Marketer::whereIn('id', $conflictIds)->pluck('name', 'id')->all() : [];
    }

    public function conflictMessage(array $conflicts): string
    {
        return __('marketer_campaigns.errors.marketer_conflict', ['names' => implode(__('marketer_campaigns.errors.names_separator'), $conflicts)]);
    }

    /**
     * Validate the wizard input against the item and create the campaign.
     *
     * @param  array<string, mixed>  $input  request fields (already shape-validated by the controller)
     * @return array{campaign: MarketerCampaign, quote: array<string, mixed>}
     *
     * @throws ValidationException with field errors the wizard shows
     */
    public function submit(CampaignOwner $owner, CampaignSubject $subject, array $input): array
    {
        $itemColumn = self::itemColumn($subject);
        $itemId = (string) $subject->model->id;
        $openToAffiliates = filter_var($input['open_to_affiliates'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $eligible = $this->quotes->eligibleMarketers($subject)->keyBy('id');
        $influencerOptions = $this->quotes->influencerOptions($subject)->keyBy('id');

        $picked = array_values(array_unique(array_filter((array) ($input['marketer_ids'] ?? []), 'is_string')));
        if (array_diff($picked, $influencerOptions->keys()->all())) {
            throw ValidationException::withMessages(['marketer_ids' => __('partner.marketer_campaigns.marketer_not_eligible')]);
        }

        $conflicts = $this->conflictingMarketers($itemColumn, $itemId, $picked);
        if ($conflicts) {
            throw ValidationException::withMessages(['marketer_ids' => $this->conflictMessage($conflicts)]);
        }

        $marketerIds = $picked;
        if ($openToAffiliates) {
            $openIds = $eligible
                ->filter(fn ($m) => array_filter(MarketerType::heldBy($m), fn (MarketerType $t) => $t !== MarketerType::Influencer) !== []
                    && ! in_array($m->id, $picked, true))
                ->keys()->all();
            $openIds = array_values(array_diff($openIds, array_keys($this->conflictingMarketers($itemColumn, $itemId, $openIds))));
            $marketerIds = array_values(array_unique(array_merge($marketerIds, $openIds)));
        }

        if ($marketerIds === []) {
            throw ValidationException::withMessages(['marketer_ids' => __('partner.marketer_campaigns.select_at_least_one')]);
        }

        $budget = round((float) ($input['max_commission_budget'] ?? 0), 2);
        $quote = $this->quotes->quote($subject, $picked, $budget, $openToAffiliates);
        if (! $quote['fits_budget']) {
            throw ValidationException::withMessages(['max_commission_budget' => __('marketer_campaigns.errors.fixed_exceeds_budget', [
                'fixed' => Money::format($quote['influencer_fixed_total']), 'budget' => Money::format($budget),
            ])]);
        }

        $campaign = $this->campaigns->createCampaign($owner, $subject->source(), [
            'max_commission_budget' => $budget,
            'country_id' => $subject->countryId,
            'currency' => $subject->currency,
            'marketer_ids' => $marketerIds,
            'influencer_ids' => $picked,
            'open_to_affiliates' => $openToAffiliates,
            'ends_at' => ! empty($input['ends_at']) ? Carbon::parse($input['ends_at'])->endOfDay() : null,
            'selected_ad_types' => ($input['selected_ad_types'] ?? null) ?: null,
            'vendor_ad_notes' => ($input['vendor_ad_notes'] ?? null) ?: null,
        ]);

        return ['campaign' => $campaign, 'quote' => $quote];
    }
}
