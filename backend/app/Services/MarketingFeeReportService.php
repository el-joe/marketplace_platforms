<?php

namespace App\Services;

use App\Enums\MarketingFeeType;
use App\Models\Country;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignFeeAccrual;
use App\Models\MarketerCampaignFeeSettlement;
use App\Models\MarketerCampaignInvitation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * P8: every marketing-fee / marketer-payout figure of the reports, read ONLY from stored snapshots and ledgers
 * (campaign fee snapshot, invitation fee/VAT/marketing/share columns, fee accrual rows, fee settlements) and never
 * recomputed from current settings. Money is grouped by country AND currency (the currency stored on the row) and by
 * billing type (FBM (cross_dock) / FBN / FBP, plus a `total` row per country+currency); currencies are never blended.
 *
 * Every figure is attributed to the date of its own event: fee collected = platform_fee_paid_at, refund =
 * platform_fee_refunded_at, credit = marketer_credited_at, reversal = marketer_credit_reversed_at, accrual row =
 * created_at, settlement = paid_at (pending ones: created_at).
 */
class MarketingFeeReportService
{
    public const TOTAL = 'total';

    /** @var array<string, array{country_id: ?string, currency: ?string, type: string}> */
    private array $campaignMeta = [];

    /** @var array<string, string> */
    private array $countryNames = [];

    /** Metric keys of one platform report row (all 2-decimal money, `campaigns_*` are counts). */
    public const PLATFORM_METRICS = [
        'influencer_fees', 'influencer_vat', 'influencer_marketing_fees', 'affiliate_marketing_fees', 'marketing_fees',
        'payouts_credited', 'payouts_reversed', 'payouts_clawback', 'payouts_net',
        'affiliate_commissions', 'refunds', 'refunds_vat', 'refunds_net_of_vat', 'platform_margin', 'vat_liability',
    ];

    /**
     * Admin finance report: per country + currency, per billing type and in total.
     *
     * platform_margin = influencer fees - fee-share payouts (net of reversals/clawbacks) + marketing fees
     *                   (influencer + affiliate) - refunds (refunded VAT excluded, it was never revenue).
     *
     * @param  array{country_id?: ?string, currency?: ?string, type?: ?string}  $filters
     * @return array{from: string, to: string, rows: array<int, array<string, mixed>>}
     */
    public function platformReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $buckets = [];

        // Influencer fees / VAT / marketing share collected from vendors (paid, and later refunded ones: gross).
        $this->invitationSums('platform_fee_paid_at', $start, $end, ['paid', 'refunded'],
            'SUM(platform_fee_amount) AS fee, SUM(platform_fee_vat_amount) AS vat, SUM(platform_fee_marketing_amount) AS mkt')
            ->each(function ($r) use (&$buckets) {
                $this->add($buckets, $r->campaign_id, $r->ccy, 'influencer_fees', $r->fee);
                $this->add($buckets, $r->campaign_id, $r->ccy, 'influencer_vat', $r->vat);
                $this->add($buckets, $r->campaign_id, $r->ccy, 'influencer_marketing_fees', $r->mkt);
            });

        // Refunds of fees to the vendor (the refund amount is fee + VAT + marketing; VAT kept apart).
        $this->invitationSums('platform_fee_refunded_at', $start, $end, ['refunded'],
            'SUM(platform_fee_refund_amount) AS refund, SUM(platform_fee_vat_amount) AS vat')
            ->each(function ($r) use (&$buckets) {
                $this->add($buckets, $r->campaign_id, $r->ccy, 'refunds', $r->refund);
                $this->add($buckets, $r->campaign_id, $r->ccy, 'refunds_vat', $r->vat);
            });

        // Fee-share payouts to influencers: credits, reversals (wallet debit made), outstanding clawbacks.
        $this->invitationSums('marketer_credited_at', $start, $end, null, 'SUM(marketer_payout_amount) AS payout', 'payouts_credit')
            ->each(function ($r) use (&$buckets) {
                $this->add($buckets, $r->campaign_id, $r->ccy, 'payouts_credited', $r->payout);
            });
        $this->invitationSums('marketer_credit_reversed_at', $start, $end, null, 'SUM(marketer_payout_amount) AS payout', 'payouts_reversal')
            ->each(function ($r) use (&$buckets) {
                $this->add($buckets, $r->campaign_id, $r->ccy, 'payouts_reversed', $r->payout);
            });
        $this->invitationSums('platform_fee_refunded_at', $start, $end, null, 'SUM(marketer_clawback_amount) AS payout', 'payouts_clawback')
            ->each(function ($r) use (&$buckets) {
                $this->add($buckets, $r->campaign_id, $r->ccy, 'payouts_clawback', $r->payout);
            });

        // Affiliate marketing fees collected from vendors (settlements) and refunded after settlement.
        MarketerCampaignFeeSettlement::query()
            ->where('status', MarketerCampaignFeeSettlement::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->groupBy('campaign_id', 'currency', 'kind')
            ->selectRaw('campaign_id, currency AS ccy, kind, SUM(amount) AS amount')
            ->get()
            ->each(function ($r) use (&$buckets) {
                $metric = $r->kind === MarketerCampaignFeeSettlement::KIND_REFUND ? 'refunds' : 'affiliate_marketing_fees';
                $this->add($buckets, $r->campaign_id, $r->ccy, $metric, $r->amount);
            });

        // Affiliate commissions earned (net of reversals) = commission base of the fee accrual rows.
        MarketerCampaignFeeAccrual::query()
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('campaign_id')
            ->selectRaw('campaign_id, SUM(commission_base) AS base')
            ->get()
            ->each(function ($r) use (&$buckets) {
                $this->add($buckets, $r->campaign_id, null, 'affiliate_commissions', $r->base);
            });

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'rows' => $this->finishRows($buckets, self::PLATFORM_METRICS, $filters, fn (array $m) => $this->derive($m)),
        ];
    }

    /**
     * Marketing Fees "Report" tab, part 1: influencer campaigns from their creation-time snapshot, per country +
     * currency + type: campaigns, influencers (accepted), subtotal, fixed, variable, marketing fees, grand total,
     * collected vs pending (what the invitations were/are charged: fee + VAT + marketing).
     *
     * @param  array{country_id?: ?string, currency?: ?string, type?: ?string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function campaignTypeReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $buckets = [];
        $metrics = ['campaigns', 'influencers', 'subtotal', 'fixed_fee', 'variable_fee', 'marketing_fees', 'grand_total',
            'collected', 'pending', 'marketing_collected', 'marketing_pending'];

        $campaigns = MarketerCampaign::withTrashed()
            ->whereNotNull('campaign_subtotal')
            ->whereNotNull('vendor_id')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        foreach ($campaigns as $c) {
            $this->add($buckets, $c->id, $c->currency, 'campaigns', 1);
            $this->add($buckets, $c->id, $c->currency, 'subtotal', $c->campaign_subtotal);
            $this->add($buckets, $c->id, $c->currency, 'fixed_fee', $c->marketing_fee_fixed);
            $this->add($buckets, $c->id, $c->currency, 'variable_fee', $c->marketing_fee_variable);
            $this->add($buckets, $c->id, $c->currency, 'marketing_fees', $c->marketing_fee_total);
            $this->add($buckets, $c->id, $c->currency, 'grand_total', $c->campaign_grand_total);
        }

        $ids = $campaigns->pluck('id')->all();
        if ($ids !== []) {
            MarketerCampaignInvitation::query()
                ->whereIn('campaign_id', $ids)
                ->where('platform_fee_payer', 'vendor')
                ->whereIn('platform_fee_status', ['paid', 'pending'])
                ->groupBy('campaign_id', 'platform_fee_currency', 'platform_fee_status')
                ->selectRaw('campaign_id, platform_fee_currency AS ccy, platform_fee_status AS st, COUNT(*) AS n,
                    SUM(platform_fee_amount + platform_fee_vat_amount + platform_fee_marketing_amount) AS total,
                    SUM(platform_fee_marketing_amount) AS mkt')
                ->get()
                ->each(function ($r) use (&$buckets) {
                    $paid = $r->st === 'paid';
                    $this->add($buckets, $r->campaign_id, $r->ccy, $paid ? 'collected' : 'pending', $r->total);
                    $this->add($buckets, $r->campaign_id, $r->ccy, $paid ? 'marketing_collected' : 'marketing_pending', $r->mkt);
                    $this->add($buckets, $r->campaign_id, $r->ccy, 'influencers', $r->n);
                });
        }

        return $this->finishRows($buckets, $metrics, $filters);
    }

    /**
     * Marketing Fees "Report" tab, part 2: affiliate marketing-fee accruals per country + currency + type.
     * accrued = positive accrual rows, refunded = reversal rows (shown positive), settled = charged to the vendor
     * (paid settlements), pending = charged but not yet paid, settlement_refunds = credited back after settlement.
     *
     * @param  array{country_id?: ?string, currency?: ?string, type?: ?string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function affiliateAccrualReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $buckets = [];
        $metrics = ['orders', 'commission', 'accrued', 'refunded', 'net_accrued', 'settled', 'pending', 'settlement_refunds'];

        MarketerCampaignFeeAccrual::query()
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('campaign_id', 'entry_type')
            ->selectRaw('campaign_id, entry_type, COUNT(*) AS n, SUM(commission_base) AS base, SUM(marketing_total_delta) AS fee')
            ->get()
            ->each(function ($r) use (&$buckets) {
                $this->add($buckets, $r->campaign_id, null, 'commission', $r->base);
                if ($r->entry_type === MarketerCampaignFeeAccrual::ENTRY_REVERSAL) {
                    $this->add($buckets, $r->campaign_id, null, 'refunded', abs((float) $r->fee));
                } else {
                    $this->add($buckets, $r->campaign_id, null, 'orders', $r->n);
                    $this->add($buckets, $r->campaign_id, null, 'accrued', $r->fee);
                }
                $this->add($buckets, $r->campaign_id, null, 'net_accrued', $r->fee);
            });

        MarketerCampaignFeeSettlement::query()
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('campaign_id', 'currency', 'kind', 'status')
            ->selectRaw('campaign_id, currency AS ccy, kind, status, SUM(amount) AS amount')
            ->get()
            ->each(function ($r) use (&$buckets) {
                if ($r->kind === MarketerCampaignFeeSettlement::KIND_REFUND) {
                    if ($r->status === MarketerCampaignFeeSettlement::STATUS_PAID) {
                        $this->add($buckets, $r->campaign_id, $r->ccy, 'settlement_refunds', $r->amount);
                    }

                    return;
                }
                $metric = $r->status === MarketerCampaignFeeSettlement::STATUS_PAID ? 'settled' : 'pending';
                $this->add($buckets, $r->campaign_id, $r->ccy, $metric, $r->amount);
            });

        return $this->finishRows($buckets, $metrics, $filters);
    }

    /**
     * Vendor totals per currency (all time unless a range is given): influencer fees (fee + VAT), marketing fees on
     * influencer campaigns, affiliate marketing fees, refunds (influencer refunds + affiliate refunds after settlement).
     *
     * @return array<string, array<string, float>> keyed by currency
     */
    public function vendorTotals(string $vendorId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $totals = [];
        $bump = function (string $currency, string $key, mixed $value) use (&$totals) {
            $totals[$currency] ??= ['influencer_fees' => 0.0, 'influencer_vat' => 0.0, 'marketing_fees' => 0.0, 'affiliate_marketing_fees' => 0.0, 'refunds' => 0.0, 'total_charged' => 0.0];
            $totals[$currency][$key] = round($totals[$currency][$key] + (float) $value, 2);
        };

        $invitations = MarketerCampaignInvitation::query()
            ->where('platform_fee_payer', 'vendor')
            ->join('marketer_campaigns', 'marketer_campaigns.id', '=', 'marketer_campaign_invitations.campaign_id')
            ->where('marketer_campaigns.vendor_id', $vendorId);

        (clone $invitations)->whereIn('platform_fee_status', ['paid', 'refunded'])
            ->when($from, fn ($q) => $q->where('platform_fee_paid_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('platform_fee_paid_at', '<=', $to->copy()->endOfDay()))
            ->groupBy('ccy')
            ->selectRaw('COALESCE(platform_fee_currency, marketer_campaigns.currency) AS ccy, SUM(platform_fee_amount) AS fee, SUM(platform_fee_vat_amount) AS vat, SUM(platform_fee_marketing_amount) AS mkt')
            ->get()
            ->each(function ($r) use ($bump) {
                $bump($r->ccy, 'influencer_fees', (float) $r->fee + (float) $r->vat);
                $bump($r->ccy, 'influencer_vat', $r->vat);
                $bump($r->ccy, 'marketing_fees', $r->mkt);
            });

        (clone $invitations)->where('platform_fee_status', 'refunded')
            ->when($from, fn ($q) => $q->where('platform_fee_refunded_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('platform_fee_refunded_at', '<=', $to->copy()->endOfDay()))
            ->groupBy('ccy')
            ->selectRaw('COALESCE(platform_fee_currency, marketer_campaigns.currency) AS ccy, SUM(platform_fee_refund_amount) AS refund')
            ->get()
            ->each(fn ($r) => $bump($r->ccy, 'refunds', $r->refund));

        MarketerCampaignFeeSettlement::query()
            ->where('vendor_id', $vendorId)
            ->where('status', MarketerCampaignFeeSettlement::STATUS_PAID)
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to->copy()->endOfDay()))
            ->groupBy('currency', 'kind')
            ->selectRaw('currency AS ccy, kind, SUM(amount) AS amount')
            ->get()
            ->each(function ($r) use ($bump) {
                $bump($r->ccy, $r->kind === MarketerCampaignFeeSettlement::KIND_REFUND ? 'refunds' : 'affiliate_marketing_fees', $r->amount);
            });

        foreach ($totals as $currency => $t) {
            $totals[$currency]['total_charged'] = round($t['influencer_fees'] + $t['marketing_fees'] + $t['affiliate_marketing_fees'], 2);
        }

        return $totals;
    }

    /**
     * Fee-share earnings of one marketer: one row per accepted influencer invitation with its snapshot state, plus
     * totals per currency (credited, reversed, outstanding clawback, net).
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, array<string, float>>}
     */
    public function marketerFeeShare(string $marketerId): array
    {
        $invitations = MarketerCampaignInvitation::query()
            ->where('marketer_id', $marketerId)
            ->where('status', 'accepted')
            ->where('platform_fee_payer', 'vendor')
            ->where('marketer_payout_amount', '>', 0)
            ->with(['campaign' => fn ($q) => $q->withTrashed()->select('id', 'title', 'currency', 'status', 'fulfillment_type')])
            ->orderByDesc('responded_at')
            ->get();

        $totals = [];
        $rows = $invitations->map(function (MarketerCampaignInvitation $i) use (&$totals) {
            $currency = $i->platform_fee_currency ?? $i->campaign?->currency ?? '';
            $credited = $i->marketer_credited_at !== null;
            $reversed = $i->marketer_credit_reversal_wallet_tx_id !== null;
            $clawback = ! $reversed && (float) $i->marketer_clawback_amount > 0;
            $amount = round((float) $i->marketer_payout_amount, 2);

            $state = match (true) {
                $reversed => 'reversed',
                $clawback => 'clawback',
                $i->marketer_credit_reversed_at !== null => 'never_credited',
                $credited => 'credited',
                default => 'pending',
            };

            $t = &$totals[$currency];
            $t ??= ['credited' => 0.0, 'reversed' => 0.0, 'clawback' => 0.0, 'net' => 0.0, 'pending' => 0.0];
            if ($credited) {
                $t['credited'] = round($t['credited'] + $amount, 2);
            } elseif ($state === 'pending') {
                $t['pending'] = round($t['pending'] + $amount, 2);
            }
            if ($reversed) {
                $t['reversed'] = round($t['reversed'] + $amount, 2);
            }
            if ($clawback) {
                $t['clawback'] = round($t['clawback'] + (float) $i->marketer_clawback_amount, 2);
            }
            $t['net'] = round($t['credited'] - $t['reversed'] - $t['clawback'], 2);
            unset($t);

            return [
                'invitation_id' => $i->id,
                'campaign_id' => $i->campaign_id,
                'campaign_title' => $i->campaign?->title ?: '#'.substr((string) $i->campaign_id, 0, 8),
                'currency' => $currency,
                'fee' => round((float) $i->platform_fee_amount, 2),
                'share_pct' => (float) $i->marketer_fee_share_pct,
                'amount' => $amount,
                'credited_at' => $i->marketer_credited_at,
                'reversed_at' => $reversed ? $i->marketer_credit_reversed_at : null,
                'clawback_amount' => $clawback ? round((float) $i->marketer_clawback_amount, 2) : 0.0,
                'state' => $state,
            ];
        });

        return ['rows' => $rows->values(), 'totals' => $totals];
    }

    /**
     * Affiliate totals of one marketer per campaign (net of reversed sales): orders, commission, and the marketing
     * fees those sales generated for the seller.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, array<string, float>>}
     */
    public function marketerAffiliateTotals(string $marketerId): array
    {
        $rows = MarketerCampaignFeeAccrual::query()
            ->join('marketer_campaign_invitations', 'marketer_campaign_invitations.id', '=', 'marketer_campaign_fee_accruals.invitation_id')
            ->join('marketer_campaigns', 'marketer_campaigns.id', '=', 'marketer_campaign_fee_accruals.campaign_id')
            ->where('marketer_campaign_invitations.marketer_id', $marketerId)
            ->groupBy('marketer_campaign_fee_accruals.campaign_id', 'marketer_campaigns.title', 'marketer_campaigns.currency')
            ->selectRaw('marketer_campaign_fee_accruals.campaign_id AS campaign_id, marketer_campaigns.title AS title, marketer_campaigns.currency AS currency,
                SUM(CASE WHEN entry_type = \'accrual\' THEN 1 ELSE 0 END) AS orders,
                SUM(commission_base) AS commission, SUM(marketing_total_delta) AS marketing_fees')
            ->get()
            ->map(fn ($r) => [
                'campaign_id' => $r->campaign_id,
                'campaign_title' => $r->title ?: '#'.substr((string) $r->campaign_id, 0, 8),
                'currency' => $r->currency,
                'orders' => (int) $r->orders,
                'commission' => round((float) $r->commission, 2),
                'marketing_fees' => round((float) $r->marketing_fees, 2),
            ]);

        $totals = [];
        foreach ($rows as $r) {
            $t = &$totals[$r['currency']];
            $t ??= ['campaigns' => 0, 'orders' => 0, 'commission' => 0.0, 'marketing_fees' => 0.0];
            $t['campaigns']++;
            $t['orders'] += $r['orders'];
            $t['commission'] = round($t['commission'] + $r['commission'], 2);
            $t['marketing_fees'] = round($t['marketing_fees'] + $r['marketing_fees'], 2);
            unset($t);
        }

        return ['rows' => $rows->values(), 'totals' => $totals];
    }

    /**
     * Fee-share + affiliate figures of one admin-visible marketer profile (same data as the marketer panel).
     *
     * @return array{fee_share: array, affiliate: array}
     */
    public function marketerEarnings(string $marketerId): array
    {
        return ['fee_share' => $this->marketerFeeShare($marketerId), 'affiliate' => $this->marketerAffiliateTotals($marketerId)];
    }

    // ── internals ───────────────────────────────────────────────────────────

    /**
     * Invitation sums per campaign + currency for one event-date column. `$kind` selects extra conditions:
     * payouts_credit (credited only), payouts_reversal (a reversal wallet debit was made), payouts_clawback
     * (reversal still outstanding).
     */
    private function invitationSums(string $dateColumn, Carbon $start, Carbon $end, ?array $statuses, string $selects, ?string $kind = null): Collection
    {
        return MarketerCampaignInvitation::query()
            ->where('marketer_campaign_invitations.platform_fee_payer', 'vendor')
            ->join('marketer_campaigns', 'marketer_campaigns.id', '=', 'marketer_campaign_invitations.campaign_id')
            ->whereBetween('marketer_campaign_invitations.'.$dateColumn, [$start, $end])
            ->when($statuses, fn ($q) => $q->whereIn('marketer_campaign_invitations.platform_fee_status', $statuses))
            ->when($kind === 'payouts_reversal', fn ($q) => $q->whereNotNull('marketer_campaign_invitations.marketer_credit_reversal_wallet_tx_id'))
            ->when($kind === 'payouts_clawback', fn ($q) => $q->whereNull('marketer_campaign_invitations.marketer_credit_reversed_at')->where('marketer_campaign_invitations.marketer_clawback_amount', '>', 0))
            ->groupBy('marketer_campaign_invitations.campaign_id', 'ccy')
            ->selectRaw('marketer_campaign_invitations.campaign_id AS campaign_id, COALESCE(marketer_campaign_invitations.platform_fee_currency, marketer_campaigns.currency) AS ccy, '.$selects)
            ->get();
    }

    /** @param array<string, array<string, mixed>> $buckets */
    private function add(array &$buckets, string $campaignId, ?string $currency, string $metric, mixed $value): void
    {
        $meta = $this->meta($campaignId);
        $ccy = $currency ?: $meta['currency'];
        $key = ($meta['country_id'] ?? '').'|'.$ccy.'|'.$meta['type'];

        $buckets[$key]['country_id'] = $meta['country_id'];
        $buckets[$key]['currency'] = $ccy;
        $buckets[$key]['type'] = $meta['type'];
        $buckets[$key]['m'][$metric] = round(($buckets[$key]['m'][$metric] ?? 0) + (float) $value, 4);
    }

    /** @return array{country_id: ?string, currency: ?string, type: string} */
    private function meta(string $campaignId): array
    {
        if (! isset($this->campaignMeta[$campaignId])) {
            $c = MarketerCampaign::withTrashed()->with(['vendorListing', 'adminListing'])->find($campaignId);
            $type = $c?->fulfillment_type instanceof MarketingFeeType
                ? $c->fulfillment_type
                : MarketingFeeType::fromListingModel($c?->vendorListing?->fulfillment_model ?? $c?->adminListing?->fulfillment_type);

            $this->campaignMeta[$campaignId] = ['country_id' => $c?->country_id, 'currency' => $c?->currency, 'type' => $type->value];
        }

        return $this->campaignMeta[$campaignId];
    }

    /**
     * Turns buckets into display rows: filters, adds the per-(country,currency) `total` row, sorts, labels.
     *
     * @param  array<string, array<string, mixed>>  $buckets
     * @param  array<int, string>  $metricKeys
     * @param  array{country_id?: ?string, currency?: ?string, type?: ?string}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function finishRows(array $buckets, array $metricKeys, array $filters, ?callable $derive = null): array
    {
        $grouped = [];
        foreach ($buckets as $b) {
            if (! empty($filters['country_id']) && $b['country_id'] !== $filters['country_id']) {
                continue;
            }
            if (! empty($filters['currency']) && $b['currency'] !== $filters['currency']) {
                continue;
            }
            if (! empty($filters['type']) && $b['type'] !== $filters['type']) {
                continue;
            }

            $gk = ($b['country_id'] ?? '').'|'.$b['currency'];
            $grouped[$gk]['country_id'] = $b['country_id'];
            $grouped[$gk]['currency'] = $b['currency'];
            $grouped[$gk]['types'][$b['type']] = $b['m'];
        }

        $this->loadCountryNames(array_filter(array_column($grouped, 'country_id')));
        $order = [MarketingFeeType::Fbm->value, MarketingFeeType::Fbn->value, MarketingFeeType::Fbp->value];
        $rows = [];

        foreach ($grouped as $g) {
            $total = array_fill_keys($metricKeys, 0.0);
            $typeRows = [];
            foreach ($order as $type) {
                if (! isset($g['types'][$type])) {
                    continue;
                }
                $m = array_merge(array_fill_keys($metricKeys, 0.0), $g['types'][$type]);
                foreach ($metricKeys as $k) {
                    $total[$k] += $m[$k] ?? 0;
                }
                $typeRows[$type] = $m;
            }

            foreach ($typeRows + [self::TOTAL => $total] as $type => $m) {
                $m = $derive ? $derive($m) : $m;
                $row = [
                    'country_id' => $g['country_id'],
                    'country_name' => $this->countryNames[$g['country_id']] ?? '—',
                    'currency' => $g['currency'],
                    'type' => $type,
                    'type_label' => $type === self::TOTAL ? __('common.total') : MarketingFeeType::from($type)->label(),
                ];
                foreach ($m as $k => $v) {
                    $row[$k] = round((float) $v, 2);
                }
                $rows[] = $row;
            }
        }

        usort($rows, fn ($a, $b) => [$a['country_name'], $a['currency']] <=> [$b['country_name'], $b['currency']]);

        return $rows;
    }

    /** @param array<string, float> $m */
    private function derive(array $m): array
    {
        $m['marketing_fees'] = round($m['influencer_marketing_fees'] + $m['affiliate_marketing_fees'], 2);
        $m['payouts_net'] = round($m['payouts_credited'] - $m['payouts_reversed'] - $m['payouts_clawback'], 2);
        $m['refunds_net_of_vat'] = round($m['refunds'] - $m['refunds_vat'], 2);
        $m['platform_margin'] = round($m['influencer_fees'] - $m['payouts_net'] + $m['marketing_fees'] - $m['refunds_net_of_vat'], 2);
        $m['vat_liability'] = round($m['influencer_vat'] - $m['refunds_vat'], 2);

        return $m;
    }

    /** @param array<int, string> $ids */
    private function loadCountryNames(array $ids): void
    {
        $missing = array_diff($ids, array_keys($this->countryNames));
        if ($missing !== []) {
            foreach (Country::whereIn('id', $missing)->get(['id', 'name_en']) as $c) {
                $this->countryNames[$c->id] = $c->name_en;
            }
        }
    }
}
