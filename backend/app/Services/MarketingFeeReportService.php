<?php

namespace App\Services;

use App\Enums\CommissionVertical;
use App\Enums\MarketerType;
use App\Models\Country;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Services\Marketer\MarketerEarningsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Campaign-fee and marketer-commission figures of the v3 model, read from stored snapshots only (never recomputed
 * from current settings). Money is grouped by country AND currency and never blended across currencies.
 *
 * Two kinds of platform income:
 *   campaign fee      charged to the campaign owner when the campaign goes live (fixed + variable part,
 *                     marketer_campaigns.marketing_fee_*), per vertical (product / classified / travel);
 *   commission fee    taken out of every commission before it reaches the marketer
 *                     (conversions.commission_fee_amount, influencer fixed amounts: influencer_fixed_fee_amount),
 *                     per vertical and marketer type (influencer / affiliate / broker).
 *
 * Commission states (gross amounts, each dated by its own event): pending = created and waiting for approval (or an
 * influencer amount reserved from the budget), approved = approved / paid out, reversed = cancelled or returned
 * (an influencer amount released back to the budget).
 */
class MarketingFeeReportService
{
    public const TOTAL = 'total';

    /** Metric keys of one platform report row (2-decimal money; `campaigns` is a count). */
    public const PLATFORM_METRICS = [
        'campaigns', 'campaign_fee_fixed', 'campaign_fee_variable', 'campaign_fee_earned', 'campaign_fee_pending',
        'commission_pending', 'commission_approved', 'commission_reversed',
        'commission_fee_earned', 'commission_paid_net',
    ];

    /** @var array<string, string> */
    private array $countryNames = [];

    /**
     * Admin finance report: one row per country + currency + vertical + marketer type, plus a `total` row per
     * country + currency. Campaign-fee metrics sit on the rows of marketer type '' (they do not depend on a type).
     *
     * @param  array{country_id?: ?string, currency?: ?string, vertical?: ?string, marketer_type?: ?string}  $filters
     * @return array{from: string, to: string, rows: array<int, array<string, mixed>>}
     */
    public function platformReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $buckets = [];

        // Campaign fees earned (paid in the period) and still pending (campaigns created in the period).
        MarketerCampaign::withTrashed()
            ->where('campaign_fee_status', 'paid')
            ->whereBetween('campaign_fee_paid_at', [$start, $end])
            ->get(['id', 'country_id', 'currency', 'campaign_category', 'marketing_fee_fixed', 'marketing_fee_variable', 'marketing_fee_total'])
            ->each(function (MarketerCampaign $c) use (&$buckets) {
                $this->add($buckets, $c->country_id, $c->currency, $c->campaign_category, '', 'campaigns', 1);
                $this->add($buckets, $c->country_id, $c->currency, $c->campaign_category, '', 'campaign_fee_fixed', $c->marketing_fee_fixed);
                $this->add($buckets, $c->country_id, $c->currency, $c->campaign_category, '', 'campaign_fee_variable', $c->marketing_fee_variable);
                $this->add($buckets, $c->country_id, $c->currency, $c->campaign_category, '', 'campaign_fee_earned', $c->marketing_fee_total);
            });

        MarketerCampaign::withTrashed()
            ->where('campaign_fee_status', 'pending')
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'country_id', 'currency', 'campaign_category', 'marketing_fee_total'])
            ->each(fn (MarketerCampaign $c) => $this->add($buckets, $c->country_id, $c->currency, $c->campaign_category, '', 'campaign_fee_pending', $c->marketing_fee_total));

        // Affiliate / broker commissions, per state and event date.
        $gross = 'COALESCE(c.gross_commission_amount, c.commission_amount)';
        $fee = 'COALESCE(c.commission_fee_amount, 0)';
        $net = 'COALESCE(c.net_commission_amount, '.$gross.' - '.$fee.')';

        foreach ([
            ['pending', 'c.created_at', 'commission_pending'],
            ['approved', 'c.approved_at', 'commission_approved'],
            ['reversed', 'c.reversed_at', 'commission_reversed'],
        ] as [$status, $dateColumn, $metric]) {
            $this->conversionSums($status, $dateColumn, $start, $end, "SUM({$gross}) AS gross, SUM({$fee}) AS fee, SUM({$net}) AS net")
                ->each(function ($r) use (&$buckets, $metric, $status) {
                    $this->add($buckets, $r->country_id, $r->ccy, $r->vertical, (string) $r->marketer_type, $metric, $r->gross);
                    if ($status === 'approved') {
                        $this->add($buckets, $r->country_id, $r->ccy, $r->vertical, (string) $r->marketer_type, 'commission_fee_earned', $r->fee);
                        $this->add($buckets, $r->country_id, $r->ccy, $r->vertical, (string) $r->marketer_type, 'commission_paid_net', $r->net);
                    }
                });
        }

        // Influencer fixed amounts: reserved -> pending, paid -> approved (+ fee, net), released -> reversed.
        foreach ([
            ['reserved', 'influencer_fixed_reserved_at', 'commission_pending'],
            ['paid', 'influencer_fixed_paid_at', 'commission_approved'],
            ['released', 'influencer_fixed_released_at', 'commission_reversed'],
        ] as [$status, $dateColumn, $metric]) {
            MarketerCampaignInvitation::query()
                ->join('marketer_campaigns as mc', 'mc.id', '=', 'marketer_campaign_invitations.campaign_id')
                ->where('marketer_campaign_invitations.influencer_fixed_status', $status)
                ->whereBetween('marketer_campaign_invitations.'.$dateColumn, [$start, $end])
                ->groupBy('mc.country_id', 'mc.currency', 'mc.campaign_category')
                ->selectRaw('mc.country_id AS country_id, mc.currency AS ccy, mc.campaign_category AS vertical,
                    SUM(marketer_campaign_invitations.influencer_fixed_amount) AS gross,
                    SUM(COALESCE(marketer_campaign_invitations.influencer_fixed_fee_amount, 0)) AS fee,
                    SUM(COALESCE(marketer_campaign_invitations.influencer_fixed_net_amount, 0)) AS net')
                ->get()
                ->each(function ($r) use (&$buckets, $metric, $status) {
                    $this->add($buckets, $r->country_id, $r->ccy, $r->vertical, MarketerType::Influencer->value, $metric, $r->gross);
                    if ($status === 'paid') {
                        $this->add($buckets, $r->country_id, $r->ccy, $r->vertical, MarketerType::Influencer->value, 'commission_fee_earned', $r->fee);
                        $this->add($buckets, $r->country_id, $r->ccy, $r->vertical, MarketerType::Influencer->value, 'commission_paid_net', $r->net);
                    }
                });
        }

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'rows' => $this->finishRows($buckets, self::PLATFORM_METRICS, $filters),
        ];
    }

    /**
     * Marketing Fees "Report" tab, part 1: campaign fees from the creation-time snapshot, per country + currency +
     * vertical: campaigns, subtotal (the commission budget the fee is based on), fixed part, variable part, total
     * fee, grand total, collected (paid) vs pending.
     *
     * @param  array{country_id?: ?string, currency?: ?string, vertical?: ?string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function campaignFeeReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $buckets = [];
        $metrics = ['campaigns', 'subtotal', 'fixed_fee', 'variable_fee', 'campaign_fee', 'grand_total', 'collected', 'pending', 'voided'];

        MarketerCampaign::withTrashed()
            ->whereNotNull('campaign_subtotal')
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->each(function (MarketerCampaign $c) use (&$buckets) {
                $v = (string) $c->campaign_category;
                $this->add($buckets, $c->country_id, $c->currency, $v, '', 'campaigns', 1);
                $this->add($buckets, $c->country_id, $c->currency, $v, '', 'subtotal', $c->campaign_subtotal);
                $this->add($buckets, $c->country_id, $c->currency, $v, '', 'fixed_fee', $c->marketing_fee_fixed);
                $this->add($buckets, $c->country_id, $c->currency, $v, '', 'variable_fee', $c->marketing_fee_variable);
                $this->add($buckets, $c->country_id, $c->currency, $v, '', 'campaign_fee', $c->marketing_fee_total);
                $this->add($buckets, $c->country_id, $c->currency, $v, '', 'grand_total', $c->campaign_grand_total);
                $bucket = match ($c->campaign_fee_status) {
                    'paid' => 'collected',
                    'pending' => 'pending',
                    'voided' => 'voided',
                    default => null,
                };
                if ($bucket) {
                    $this->add($buckets, $c->country_id, $c->currency, $v, '', $bucket, $c->marketing_fee_total);
                }
            });

        return $this->finishRows($buckets, $metrics, $filters);
    }

    /**
     * Marketing Fees "Report" tab, part 2: commissions per country + currency + vertical + marketer type:
     * counts and gross amounts by state (pending / approved / reversed), the commission fee the platform earned and
     * the net paid to marketers. Influencer fixed amounts are included (one line per campaign).
     *
     * @param  array{country_id?: ?string, currency?: ?string, vertical?: ?string, marketer_type?: ?string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function commissionReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $rows = $this->platformReport($from, $to, $filters)['rows'];

        return array_values(array_filter($rows, fn (array $r) => $r['marketer_type'] !== '' || $r['vertical'] === self::TOTAL));
    }

    /**
     * Vendor / agency totals per currency (all time unless a range is given):
     *   campaign_fee              campaign fees already paid (wallet or payout statement);
     *   campaign_fee_pending      campaign fees waiting to be deducted from the wallet or the next statement;
     *   campaign_budget_commission commissions that came out of the campaigns' Max Commission Budget (approved
     *                              affiliate / broker commissions + influencer fixed amounts paid);
     *   total_charged             campaign_fee + campaign_budget_commission.
     *
     * @return array<string, array<string, float>> keyed by currency
     */
    public function vendorTotals(string $vendorId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $totals = [];
        $bump = function (?string $currency, string $key, mixed $value) use (&$totals) {
            $currency = (string) $currency;
            $totals[$currency] ??= ['campaign_fee' => 0.0, 'campaign_fee_pending' => 0.0, 'campaign_budget_commission' => 0.0, 'total_charged' => 0.0];
            $totals[$currency][$key] = round($totals[$currency][$key] + (float) $value, 2);
        };
        $start = $from?->copy()->startOfDay();
        $end = $to?->copy()->endOfDay();

        MarketerCampaign::withTrashed()->where('vendor_id', $vendorId)
            ->whereIn('campaign_fee_status', ['paid', 'pending'])
            ->get(['id', 'currency', 'campaign_fee_status', 'campaign_fee_paid_at', 'created_at', 'marketing_fee_total'])
            ->each(function (MarketerCampaign $c) use ($bump, $start, $end) {
                $date = $c->campaign_fee_status === 'paid' ? ($c->campaign_fee_paid_at ?? $c->created_at) : $c->created_at;
                if (($start && $date < $start) || ($end && $date > $end)) {
                    return;
                }
                $bump($c->currency, $c->campaign_fee_status === 'paid' ? 'campaign_fee' : 'campaign_fee_pending', $c->marketing_fee_total);
            });

        MarketerCampaignConversion::query()
            ->join('marketer_campaigns as mc', 'mc.id', '=', 'marketer_campaign_conversions.campaign_id')
            ->where('mc.vendor_id', $vendorId)
            ->where('marketer_campaign_conversions.status', 'approved')
            ->when($start, fn ($q) => $q->where('marketer_campaign_conversions.approved_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('marketer_campaign_conversions.approved_at', '<=', $end))
            ->groupBy('mc.currency')
            ->selectRaw('mc.currency AS ccy, SUM(COALESCE(marketer_campaign_conversions.gross_commission_amount, marketer_campaign_conversions.commission_amount)) AS gross')
            ->get()
            ->each(fn ($r) => $bump($r->ccy, 'campaign_budget_commission', $r->gross));

        MarketerCampaignInvitation::query()
            ->join('marketer_campaigns as mc', 'mc.id', '=', 'marketer_campaign_invitations.campaign_id')
            ->where('mc.vendor_id', $vendorId)
            ->where('marketer_campaign_invitations.influencer_fixed_status', MarketerCampaignInvitation::FIXED_PAID)
            ->when($start, fn ($q) => $q->where('marketer_campaign_invitations.influencer_fixed_paid_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('marketer_campaign_invitations.influencer_fixed_paid_at', '<=', $end))
            ->groupBy('mc.currency')
            ->selectRaw('mc.currency AS ccy, SUM(marketer_campaign_invitations.influencer_fixed_amount) AS gross')
            ->get()
            ->each(fn ($r) => $bump($r->ccy, 'campaign_budget_commission', $r->gross));

        foreach ($totals as $currency => $t) {
            $totals[$currency]['total_charged'] = round($t['campaign_fee'] + $t['campaign_budget_commission'], 2);
        }

        return $totals;
    }

    /**
     * Earnings of one marketer per campaign (conversions that are not reversed): orders, gross commission, platform
     * commission fee and net, with totals per currency.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, array<string, float|int>>}
     */
    public function marketerCampaignTotals(string $marketerId): array
    {
        $rows = MarketerCampaignConversion::query()
            ->join('marketer_campaign_invitations as i', 'i.id', '=', 'marketer_campaign_conversions.invitation_id')
            ->join('marketer_campaigns as mc', 'mc.id', '=', 'marketer_campaign_conversions.campaign_id')
            ->where('i.marketer_id', $marketerId)
            ->where('marketer_campaign_conversions.status', '!=', 'reversed')
            ->groupBy('mc.id', 'mc.title', 'mc.currency')
            ->selectRaw('mc.id AS campaign_id, mc.title AS title, mc.currency AS currency, COUNT(*) AS orders,
                SUM(COALESCE(marketer_campaign_conversions.gross_commission_amount, marketer_campaign_conversions.commission_amount)) AS gross,
                SUM(COALESCE(marketer_campaign_conversions.commission_fee_amount, 0)) AS fee,
                SUM(COALESCE(marketer_campaign_conversions.net_commission_amount, COALESCE(marketer_campaign_conversions.gross_commission_amount, marketer_campaign_conversions.commission_amount) - COALESCE(marketer_campaign_conversions.commission_fee_amount, 0))) AS net')
            ->get()
            ->map(fn ($r) => [
                'campaign_id' => $r->campaign_id,
                'campaign_title' => $r->title ?: '#'.substr((string) $r->campaign_id, 0, 8),
                'currency' => $r->currency,
                'orders' => (int) $r->orders,
                'gross' => round((float) $r->gross, 2),
                'fee' => round((float) $r->fee, 2),
                'net' => round((float) $r->net, 2),
            ]);

        $totals = [];
        foreach ($rows as $r) {
            $t = &$totals[$r['currency']];
            $t ??= ['campaigns' => 0, 'orders' => 0, 'gross' => 0.0, 'fee' => 0.0, 'net' => 0.0];
            $t['campaigns']++;
            $t['orders'] += $r['orders'];
            foreach (['gross', 'fee', 'net'] as $k) {
                $t[$k] = round($t[$k] + $r[$k], 2);
            }
            unset($t);
        }

        return ['rows' => $rows->values(), 'totals' => $totals];
    }

    /**
     * Everything the admin marketer profile and the marketer panel show: conversions per campaign + the influencer's
     * per-campaign fixed amount lines.
     *
     * @return array{campaigns: array, fixed: Collection}
     */
    public function marketerEarnings(string $marketerId): array
    {
        return [
            'campaigns' => $this->marketerCampaignTotals($marketerId),
            'fixed' => app(MarketerEarningsService::class)->fixedAmountLines($marketerId),
        ];
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** Conversion sums per campaign country + currency + vertical + marketer type for one status / event date. */
    private function conversionSums(string $status, string $dateColumn, Carbon $start, Carbon $end, string $selects): Collection
    {
        return DB::table('marketer_campaign_conversions as c')
            ->join('marketer_campaigns as mc', 'mc.id', '=', 'c.campaign_id')
            ->where('c.status', $status)
            ->whereBetween($dateColumn, [$start, $end])
            ->groupBy('mc.country_id', 'mc.currency', 'mc.campaign_category', 'c.marketer_type')
            ->selectRaw('mc.country_id AS country_id, mc.currency AS ccy, mc.campaign_category AS vertical, COALESCE(c.marketer_type, \'affiliate\') AS marketer_type, '.$selects)
            ->get();
    }

    /** @param array<string, array<string, mixed>> $buckets */
    private function add(array &$buckets, ?string $countryId, ?string $currency, ?string $vertical, string $marketerType, string $metric, mixed $value): void
    {
        $vertical = (string) ($vertical ?: CommissionVertical::Product->value);
        $key = ($countryId ?? '').'|'.$currency.'|'.$vertical.'|'.$marketerType;

        $buckets[$key]['country_id'] = $countryId;
        $buckets[$key]['currency'] = $currency;
        $buckets[$key]['vertical'] = $vertical;
        $buckets[$key]['marketer_type'] = $marketerType;
        $buckets[$key]['m'][$metric] = round(($buckets[$key]['m'][$metric] ?? 0) + (float) $value, 4);
    }

    /**
     * Turns buckets into display rows: filters, adds the per-(country,currency) `total` row, sorts, labels.
     *
     * @param  array<string, array<string, mixed>>  $buckets
     * @param  array<int, string>  $metricKeys
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function finishRows(array $buckets, array $metricKeys, array $filters): array
    {
        $groups = [];

        foreach ($buckets as $b) {
            if (! empty($filters['country_id']) && $b['country_id'] !== $filters['country_id']) {
                continue;
            }
            if (! empty($filters['currency']) && $b['currency'] !== $filters['currency']) {
                continue;
            }
            if (! empty($filters['vertical']) && $b['vertical'] !== $filters['vertical']) {
                continue;
            }
            if (! empty($filters['marketer_type']) && $b['marketer_type'] !== $filters['marketer_type']) {
                continue;
            }

            $gk = ($b['country_id'] ?? '').'|'.$b['currency'];
            $groups[$gk]['country_id'] = $b['country_id'];
            $groups[$gk]['currency'] = $b['currency'];
            $groups[$gk]['lines'][$b['vertical'].'|'.$b['marketer_type']] = $b;
        }

        $this->loadCountryNames(array_filter(array_column($groups, 'country_id')));
        $blank = array_fill_keys($metricKeys, 0.0);
        $verticalOrder = CommissionVertical::values();
        $typeOrder = ['', ...MarketerType::values()];
        $rows = [];

        foreach ($groups as $g) {
            $lines = $g['lines'];
            uksort($lines, function ($a, $b) use ($verticalOrder, $typeOrder) {
                [$va, $ta] = explode('|', $a);
                [$vb, $tb] = explode('|', $b);

                return [array_search($va, $verticalOrder), array_search($ta, $typeOrder)] <=> [array_search($vb, $verticalOrder), array_search($tb, $typeOrder)];
            });

            $total = $blank;
            foreach ($lines as $line) {
                $m = array_merge($blank, $line['m']);
                foreach ($metricKeys as $k) {
                    $total[$k] += $m[$k];
                }
                $rows[] = $this->row($g, $line['vertical'], $line['marketer_type'], $m);
            }
            $rows[] = $this->row($g, self::TOTAL, '', $total);
        }

        usort($rows, fn ($a, $b) => [$a['country_name'], $a['currency'], $a['vertical'] === self::TOTAL] <=> [$b['country_name'], $b['currency'], $b['vertical'] === self::TOTAL]);

        return $rows;
    }

    /** @param array<string, float> $m */
    private function row(array $group, string $vertical, string $marketerType, array $m): array
    {
        $row = [
            'country_id' => $group['country_id'],
            'country_name' => $this->countryNames[$group['country_id']] ?? '—',
            'currency' => $group['currency'],
            'vertical' => $vertical,
            'vertical_label' => $vertical === self::TOTAL ? __('common.total') : (CommissionVertical::tryFrom($vertical)?->label() ?? $vertical),
            'marketer_type' => $marketerType,
            'marketer_type_label' => $marketerType === '' ? '—' : (MarketerType::tryFrom($marketerType)?->label() ?? $marketerType),
            // Back-compat alias used by the report views: the row "type" is the vertical.
            'type' => $vertical,
            'type_label' => $vertical === self::TOTAL ? __('common.total') : (CommissionVertical::tryFrom($vertical)?->label() ?? $vertical),
        ];
        foreach ($m as $k => $v) {
            $row[$k] = round((float) $v, 2);
        }

        return $row;
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
