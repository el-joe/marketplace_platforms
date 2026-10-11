<?php

namespace App\Services\Admin\Commissions;

use App\Models\AdminListing;
use App\Models\Category;
use App\Models\Commission;
use App\Models\FlashSale;
use App\Models\FlashSaleMarketerInvitation;
use App\Models\MarketerCampaign;
use App\Models\MarketerCategoryCommission;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerCommissionRule;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketerProfile;
use App\Models\MarketplaceShippingRule;
use App\Models\OpenMarketCategoryCommission;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\TravelAgencyCampaignOffer;
use App\Models\Vendor;
use App\Models\VendorAcquisitionCommission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only data for each /commissions tab, in one uniform shape the generic tab view renders:
 *
 *   stats   => list of [label, value]
 *   notices => list of [tone (info|warning|danger), text]
 *   tables  => list of [title, columns, rows, total, empty]
 *
 * Nothing here calculates a commission. Rates and amounts are shown exactly as stored: money is a
 * base-currency integer (no /100), percentages are percent, acquisition rates are basis points.
 */
class CommissionTabData
{
    /** Rows rendered per table; `total` carries the real count so the UI can say "showing N of M". */
    public const ROW_LIMIT = 25;

    /**
     * @param  list<string>|null  $scopedVendorIds  vendors a scoped (assigned-only) admin may see; null = unrestricted
     */
    public function __construct(private readonly ?array $scopedVendorIds = null) {}

    /**
     * @return array{stats: list<array{0: string, 1: string}>, notices: list<array{0: string, 1: string}>, tables: list<array{title: string, columns: list<string>, rows: list<list<string>>, total: int, empty: string}>}
     */
    public function for(string $key): array
    {
        $method = 'load'.str_replace(' ', '', ucwords(str_replace('-', ' ', $key)));

        return method_exists($this, $method) ? $this->{$method}() : $this->blank();
    }

    /**
     * Vendor-scoped rule rows for the editable vendor-rules tab.
     *
     * @return Builder<Commission>
     */
    public function vendorRulesQuery(): Builder
    {
        return Commission::query()
            ->with(['vendor:id,store_name', 'category:id,name_en,name_ar'])
            ->when($this->scopedVendorIds !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->whereNull('vendor_id')->orWhereIn('vendor_id', $this->scopedVendorIds)
            ))
            ->orderByDesc('priority')
            ->orderByDesc('created_at');
    }

    // ── A. Platform & vendor ────────────────────────────────────────────────

    private function loadPlatformRules(): array
    {
        $query = Category::query()->where(fn (Builder $q) => $q
            ->where('commission_fbp_pct', '>', 0)->orWhere('commission_fbp_fixed', '>', 0)
            ->orWhere('commission_fbn_pct', '>', 0)->orWhere('commission_fbn_fixed', '>', 0));

        $categories = (clone $query)->orderBy('name_en')->limit(self::ROW_LIMIT)->get();

        return $this->shape(
            stats: [
                ['categories_with_rate', $this->n((clone $query)->count())],
                ['country_overrides', $this->n($this->countTable('country_categories', fn ($q) => $q
                    ->where('commission_fbp_pct', '>', 0)->orWhere('commission_fbp_fixed', '>', 0)
                    ->orWhere('commission_fbn_pct', '>', 0)->orWhere('commission_fbn_fixed', '>', 0)))],
                ['price_tiers', $this->n($this->countTable('category_commission_tiers'))],
            ],
            notices: [['info', 'platform_rules_fbm']],
            tables: [$this->table('platform_rules_table', ['category', 'fbp_pct', 'fbp_fixed', 'fbn_pct', 'fbn_fixed'],
                $categories->map(fn (Category $c) => [
                    $this->name($c), $this->pct($c->commission_fbp_pct), $this->n($c->commission_fbp_fixed),
                    $this->pct($c->commission_fbn_pct), $this->n($c->commission_fbn_fixed),
                ])->all(), (clone $query)->count())],
        );
    }

    private function loadVendorRules(): array
    {
        $base = $this->vendorRulesQuery();
        $rules = (clone $base)->limit(self::ROW_LIMIT)->get();
        $platformWide = Commission::whereNull('vendor_id')->whereNull('category_id')->count();
        $tiered = Commission::where('rate_type', 'tiered')->count();

        $notices = [['warning', 'vendor_rules_override']];
        if ($platformWide > 0) {
            $notices[] = ['danger', 'vendor_rules_platform_wide_exists'];
        }
        if ($tiered > 0) {
            $notices[] = ['warning', 'vendor_rules_tiered_ignored'];
        }

        return $this->shape(
            stats: [['rules', $this->n((clone $base)->count())], ['platform_wide_rules', $this->n($platformWide)]],
            notices: $notices,
            tables: [$this->table('vendor_rules_table',
                ['vendor', 'category', 'rate_pct', 'rate_type', 'min', 'max', 'effective_from', 'effective_until', 'priority'],
                $rules->map(fn (Commission $r) => [
                    $r->vendor?->store_name ?? '—', $r->category ? $this->name($r->category) : '—', $this->pct($r->rate_pct),
                    $r->rate_type->value, $this->n($r->min_commission), $r->max_commission === null ? '—' : $this->n($r->max_commission),
                    $r->effective_from?->toDateString() ?? '—', $r->effective_until?->toDateString() ?? '—', $this->n($r->priority),
                ])->all(), (clone $base)->count())],
        );
    }

    private function loadVendorFlatRate(): array
    {
        $query = $this->vendorScope(Vendor::query())->where('commission_rate', '>', 0);

        return $this->shape(
            stats: [['vendors_with_override', $this->n((clone $query)->count())]],
            notices: [['info', 'vendor_flat_semantics']],
            tables: [$this->table('vendor_flat_table', ['vendor', 'rate_pct'],
                (clone $query)->orderBy('store_name')->limit(self::ROW_LIMIT)->get(['id', 'store_name', 'commission_rate'])
                    ->map(fn (Vendor $v) => [$v->store_name, $this->pct($v->commission_rate)])->all(), (clone $query)->count())],
        );
    }

    private function loadVendorDiscount(): array
    {
        $query = $this->vendorScope(Vendor::query())->whereNotNull('commission_discount_type')->where('commission_discount_type', '!=', 'none');

        return $this->shape(
            stats: [['vendors_with_discount', $this->n((clone $query)->count())]],
            notices: [['info', 'vendor_discount_applied_after']],
            tables: [$this->table('vendor_discount_table', ['vendor', 'discount_type', 'flat', 'pct', 'notes'],
                (clone $query)->orderBy('store_name')->limit(self::ROW_LIMIT)->get()
                    ->map(fn (Vendor $v) => [
                        $v->store_name, $this->s($v->commission_discount_type),
                        $this->n($v->commission_discount_flat), $this->pct($v->commission_discount_percentage), $v->commission_discount_notes ?: '—',
                    ])->all(), (clone $query)->count())],
        );
    }

    private function loadPlanDiscount(): array
    {
        $plans = SubscriptionPlan::query()->orderBy('sort_order')->get();

        return $this->shape(
            stats: [['plans_with_discount', $this->n($plans->where('commission_discount_pct', '>', 0)->count())]],
            notices: [['danger', 'plan_discount_not_applied']],
            tables: [$this->table('plan_discount_table', ['plan', 'discount_pct', 'active'],
                $plans->map(fn (SubscriptionPlan $p) => [$p->name_en ?: $p->name_ar, $this->pct($p->commission_discount_pct), $p->is_active ? 'yes' : 'no'])->all(), $plans->count())],
        );
    }

    private function loadAdminListing(): array
    {
        return $this->shape(
            stats: [['admin_listings', $this->n(AdminListing::count())]],
            notices: [['info', 'admin_listing_system']],
            tables: [],
        );
    }

    private function loadFlashOverride(): array
    {
        $query = FlashSale::query()->whereNotNull('commission_override_pct');

        return $this->shape(
            stats: [['flash_sales_with_override', $this->n((clone $query)->count())]],
            notices: [['warning', 'flash_override_analytics_only']],
            tables: [$this->table('flash_override_table', ['flash_sale', 'override_pct', 'status'],
                (clone $query)->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (FlashSale $f) => [$f->name_en ?: $f->name_ar, $this->pct($f->commission_override_pct), $this->s($f->status)])->all(), (clone $query)->count())],
        );
    }

    // ── B. Marketer ─────────────────────────────────────────────────────────

    private function loadCampaigns(): array
    {
        $byType = MarketerCampaign::query()->selectRaw('commission_type, COUNT(*) as c')->groupBy('commission_type')->pluck('c', 'commission_type');

        return $this->shape(
            stats: $byType->map(fn ($count, $type) => ['campaign_type:'.$type, $this->n($count)])->values()->all(),
            notices: [['info', 'campaigns_tiered_note'], ['warning', 'campaigns_suborder_note']],
            tables: [$this->table('campaigns_table', ['campaign', 'type', 'marketer_amount', 'platform_amount', 'budget', 'spent', 'status'],
                MarketerCampaign::query()->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (MarketerCampaign $c) => [
                        $c->title ?: $c->id, $this->s($c->commission_type), $this->n($c->marketer_commission_amount),
                        $this->n($c->platform_commission_amount), $c->max_commission_budget === null ? '—' : $this->n($c->max_commission_budget),
                        $this->n($c->commission_budget_spent), $this->s($c->status),
                    ])->all(), MarketerCampaign::count())],
        );
    }

    private function loadMarketerRules(): array
    {
        $query = MarketerCommissionRule::query()->with('marketer:id,name');
        $perScope = MarketerCommissionRule::query()->selectRaw('scope, COUNT(*) as c')->groupBy('scope')->pluck('c', 'scope');

        return $this->shape(
            stats: array_merge(
                collect(MarketerCommissionRule::SCOPES)->map(fn ($s) => ['scope:'.$s, $this->n($perScope[$s] ?? 0)])->all(),
                [['platform_defaults', $this->n(MarketerCommissionRule::whereNull('marketer_id')->count())]],
            ),
            notices: [['info', 'marketer_rules_order'], ['warning', 'marketer_rules_no_default_writer'], ['warning', 'marketer_rules_travel_unconsumed']],
            tables: [$this->table('marketer_rules_table', ['marketer', 'scope', 'mode', 'rate_pct', 'flat', 'min', 'excluded'],
                (clone $query)->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (MarketerCommissionRule $r) => [
                        $r->marketer?->name ?? 'platform_default', $r->scope, $this->s($r->commission_mode), $this->pct($r->commission_rate),
                        $this->n($r->commission_flat_amount), $this->n($r->commission_min_amount), $this->n(count($r->excluded_category_ids ?? [])),
                    ])->all(), (clone $query)->count())],
        );
    }

    private function loadLegacyStores(): array
    {
        $ruleKeys = MarketerCommissionRule::query()->whereNotNull('marketer_id')->get(['marketer_id', 'scope', 'category_id'])
            ->map(fn ($r) => $r->marketer_id.'|'.$r->scope.'|'.($r->category_id ?? '*'))->flip();

        $orphans = collect();
        MarketerCategoryCommission::query()->get(['marketer_id', 'category_id'])->each(function ($l) use ($ruleKeys, $orphans) {
            if (! $ruleKeys->has($l->marketer_id.'|products|'.($l->category_id ?? '*'))) {
                $orphans->push([$l->marketer_id, 'products', $l->category_id ?? '*']);
            }
        });
        OpenMarketCategoryCommission::query()->get(['marketer_id', 'classified_category_id'])->each(function ($l) use ($ruleKeys, $orphans) {
            if (! $ruleKeys->has($l->marketer_id.'|open_market|'.($l->classified_category_id ?? '*'))) {
                $orphans->push([$l->marketer_id, 'open_market', $l->classified_category_id ?? '*']);
            }
        });

        return $this->shape(
            stats: [
                ['legacy_products_rows', $this->n(MarketerCategoryCommission::count())],
                ['legacy_open_market_rows', $this->n(OpenMarketCategoryCommission::count())],
                ['legacy_without_rule', $this->n($orphans->count())],
            ],
            notices: [['warning', 'legacy_mirror'], $orphans->isNotEmpty() ? ['danger', 'legacy_orphans'] : ['info', 'legacy_in_sync']],
            tables: [$this->table('legacy_orphans_table', ['marketer_id', 'scope', 'category_id'],
                $orphans->take(self::ROW_LIMIT)->map(fn ($r) => array_map('strval', $r))->all(), $orphans->count())],
        );
    }

    private function loadCountryAmounts(): array
    {
        $query = MarketerCommissionCountrySetting::query()->with(['category:id,name_en,name_ar', 'country:id,name_en,name_ar']);

        return $this->shape(
            stats: [['country_rows', $this->n(MarketerCommissionCountrySetting::count())]],
            notices: [['info', 'country_amounts_fallback']],
            tables: [$this->table('country_amounts_table', ['category', 'country', 'influencer_amount', 'affiliate_amount', 'currency'],
                (clone $query)->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (MarketerCommissionCountrySetting $s) => [
                        $s->category ? $this->name($s->category) : '—', $s->country ? $this->name($s->country) : '—',
                        $this->n($s->influencer_commission_amount), $this->n($s->affiliate_commission_amount), $this->s($s->currency),
                    ])->all(), MarketerCommissionCountrySetting::count())],
        );
    }

    private function loadListingPercentages(): array
    {
        $columnsExist = Schema::hasColumn('vendor_listings', 'influencer_commission_percentage')
            && Schema::hasColumn('vendor_listings', 'affiliate_commission_percentage');

        return $this->shape(
            stats: [
                ['min_influencer_pct', $this->pct(Setting::get('min_influencer_commission_percentage', 0))],
                ['min_affiliate_pct', $this->pct(Setting::get('min_affiliate_commission_percentage', 0))],
                ['schema_columns', $columnsExist ? 'present' : 'missing'],
            ],
            notices: [$columnsExist ? ['warning', 'listing_pct_unconsumed'] : ['danger', 'listing_pct_columns_missing']],
            tables: [],
        );
    }

    private function loadMarketerDiscount(): array
    {
        $query = MarketerProfile::query()->with('marketer:id,name')->whereNotNull('commission_discount_type')->where('commission_discount_type', '!=', 'none');

        return $this->shape(
            stats: [['marketers_with_discount', $this->n((clone $query)->count())]],
            notices: [['info', 'marketer_discount_vs_platform_share']],
            tables: [$this->table('marketer_discount_table', ['marketer', 'discount_type', 'flat', 'pct', 'notes'],
                (clone $query)->limit(self::ROW_LIMIT)->get()
                    ->map(fn (MarketerProfile $p) => [
                        $p->marketer?->name ?? '—', $this->s($p->commission_discount_type),
                        $this->n($p->commission_discount_flat), $this->pct($p->commission_discount_percentage), $p->commission_discount_notes ?: '—',
                    ])->all(), (clone $query)->count())],
        );
    }

    private function loadFlashBonus(): array
    {
        $query = FlashSaleMarketerInvitation::query()->with(['flashSale:id,name_en,name_ar', 'marketer:id,name'])
            ->where(fn (Builder $q) => $q->where('extra_commission_rate', '>', 0)->orWhere('extra_commission_flat_amount', '>', 0));

        return $this->shape(
            stats: [['invitations_with_bonus', $this->n((clone $query)->count())]],
            notices: [['info', 'flash_bonus_rules']],
            tables: [$this->table('flash_bonus_table', ['flash_sale', 'marketer', 'mode', 'rate_pct', 'flat', 'status'],
                (clone $query)->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (FlashSaleMarketerInvitation $i) => [
                        $i->flashSale ? ($i->flashSale->name_en ?: $i->flashSale->name_ar) : '—', $i->marketer?->name ?? '—',
                        $this->s($i->extra_commission_mode), $this->pct($i->extra_commission_rate),
                        $this->n($i->extra_commission_flat_amount), $this->s($i->status),
                    ])->all(), (clone $query)->count())],
        );
    }

    private function loadPlatformShare(): array
    {
        $query = MarketerCampaign::query()->where('platform_commission_amount', '>', 0);

        return $this->shape(
            stats: [['campaigns_with_platform_share', $this->n((clone $query)->count())]],
            notices: [['info', 'platform_share_owner'], ['info', 'platform_share_vs_discount']],
            tables: [$this->table('platform_share_table', ['campaign', 'owner_type', 'marketer_amount', 'platform_amount', 'status'],
                (clone $query)->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (MarketerCampaign $c) => [
                        $c->title ?: $c->id, $this->s($c->owner_type), $this->n($c->marketer_commission_amount),
                        $this->n($c->platform_commission_amount), $this->s($c->status),
                    ])->all(), (clone $query)->count())],
        );
    }

    // ── C. Acquisition agents ───────────────────────────────────────────────

    private function loadAcquisition(): array
    {
        $query = VendorAcquisitionCommission::query()->with(['vendor:id,store_name', 'admin:id,name'])
            ->when($this->scopedVendorIds !== null, fn (Builder $q) => $q->whereIn('vendor_id', $this->scopedVendorIds));

        return $this->shape(
            stats: [['active_deals', $this->n((clone $query)->where('status', 'active')->count())]],
            notices: [['info', 'acquisition_internal'], ['info', 'acquisition_bps']],
            tables: [$this->table('acquisition_table', ['vendor', 'agent', 'rate_bps', 'min_sales', 'max_sales', 'valid_until', 'status', 'total_earned'],
                (clone $query)->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (VendorAcquisitionCommission $c) => [
                        $c->vendor?->store_name ?? '—', $c->admin?->name ?? '—',
                        $this->n($c->commission_rate).' bps ('.rtrim(rtrim(number_format($c->commission_rate / 100, 2, '.', ''), '0'), '.').'%)',
                        $this->n($c->monthly_min_sales), $this->n($c->monthly_max_sales), $c->valid_until?->toDateString() ?? '—',
                        $this->s($c->status), $this->n($c->total_earned).' '.$c->currency,
                    ])->all(), (clone $query)->count())],
        );
    }

    // ── D. Travel ───────────────────────────────────────────────────────────

    private function loadTravelPlatform(): array
    {
        return $this->shape(
            stats: [['travel_rate', $this->pct((float) config('travel.platform_commission_rate') * 100)]],
            notices: [['warning', 'travel_report_only']],
            tables: [],
        );
    }

    private function loadTravelOffers(): array
    {
        $query = TravelAgencyCampaignOffer::query()->with('agency:id,name')->withCount('packages');

        return $this->shape(
            stats: [['offers', $this->n(TravelAgencyCampaignOffer::count())]],
            notices: [['warning', 'travel_offers_scope_unconsumed'], ['info', 'travel_offers_no_admin_editor']],
            tables: [$this->table('travel_offers_table', ['offer', 'agency', 'rate', 'type', 'packages', 'status'],
                (clone $query)->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (TravelAgencyCampaignOffer $o) => [
                        $o->name, $o->agency?->name ?? '—', $this->pct($o->offered_commission_rate),
                        $this->s($o->commission_type), $this->n($o->packages_count), $this->s($o->status),
                    ])->all(), TravelAgencyCampaignOffer::count())],
        );
    }

    // ── E. Shipping rules & related fees ────────────────────────────────────

    private function loadShippingRules(): array
    {
        $query = MarketplaceShippingRule::query()->where(fn (Builder $q) => $q->where('commission_value', '>', 0)->orWhere('extra_delivery_fee', '>', 0));

        return $this->shape(
            stats: [['shipping_rules', $this->n((clone $query)->count())]],
            notices: [['danger', 'shipping_commission_unused'], ['info', 'shipping_extra_fee_applied']],
            tables: [$this->table('shipping_rules_table', ['rule_for', 'commission_type', 'commission_value', 'extra_delivery_fee'],
                (clone $query)->latest()->limit(self::ROW_LIMIT)->get()
                    ->map(fn (MarketplaceShippingRule $r) => [
                        $r->vendor_listing_id ? 'vendor_listing:'.$r->vendor_listing_id : 'admin_listing:'.$r->admin_listing_id,
                        $this->s($r->commission_type), $this->n($r->commission_value), $this->n($r->extra_delivery_fee),
                    ])->all(), (clone $query)->count())],
        );
    }

    private function loadRelatedFees(): array
    {
        $fees = MarketerInfluencerFeeCountrySetting::query()->with('country:id,name_en,name_ar')->get();

        return $this->shape(
            stats: [],
            notices: [['info', 'related_fees_not_commissions'], ['info', 'related_fees_influencer_fee']],
            tables: [$this->table('influencer_fee_table', ['country', 'fee_per_influencer', 'currency'],
                $fees->map(fn (MarketerInfluencerFeeCountrySetting $f) => [$f->country ? $this->name($f->country) : '—', $this->n($f->fee_per_influencer), $this->s($f->currency)])->all(), $fees->count())],
        );
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function vendorScope(Builder $query): Builder
    {
        return $query->when($this->scopedVendorIds !== null, fn (Builder $q) => $q->whereIn('id', $this->scopedVendorIds));
    }

    private function countTable(string $table, ?callable $where = null): int
    {
        $query = DB::table($table);
        if ($where) {
            $query->where($where);
        }

        return $query->count();
    }

    private function name(object $model): string
    {
        return (string) ($model->name_en ?: $model->name_ar ?: '—');
    }

    private function s(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) ($value ?? '');
    }

    private function n(int|float|string|null $value): string
    {
        return (string) ($value ?? 0);
    }

    private function pct(int|float|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'%';
    }

    private function blank(): array
    {
        return $this->shape([], [], []);
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $columns
     */
    private function table(string $title, array $columns, array $rows, int $total): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => $rows, 'total' => $total, 'empty' => $title.'_empty'];
    }

    private function shape(array $stats, array $notices, array $tables): array
    {
        return ['stats' => $stats, 'notices' => $notices, 'tables' => $tables];
    }
}
