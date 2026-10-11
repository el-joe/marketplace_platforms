<?php

namespace App\Services\Admin\Commissions;

use App\Models\Admin;
use App\Models\AdminListing;
use App\Models\Category;
use App\Models\CategoryCommissionTier;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\CountryCategory;
use App\Models\FlashSale;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerCommissionRule;
use App\Models\MarketplaceShippingRule;
use App\Models\TravelCategory;
use App\Models\Vendor;
use App\Models\VendorAcquisitionCommission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Option lists and selected records for the in-page commission editors. Read-only: every write goes
 * through the existing endpoint or CommissionSettingsController. Vendor-bearing lists honour the
 * assigned-vendor scope; option lists are capped so a huge catalogue cannot bloat the page.
 */
class CommissionEditorData
{
    public const OPTION_LIMIT = 500;

    /**
     * @param  list<string>|null  $scopedVendorIds  null = unrestricted
     */
    public function __construct(private readonly Request $request, private readonly ?array $scopedVendorIds = null) {}

    /**
     * @return array<string, mixed>
     */
    public function for(string $key): array
    {
        return match ($key) {
            'platform-rules' => $this->platformRules(),
            'vendor-flat-rate', 'vendor-discount' => $this->vendorCommission(),
            'acquisition' => $this->acquisition(),
            'marketer-rules' => $this->marketerRules(),
            'campaigns' => $this->campaigns(),
            'country-amounts' => $this->countryAmounts(),
            'flash-bonus' => $this->flashBonus(),
            'shipping-rules' => $this->shippingRules(),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function platformRules(): array
    {
        $category = $this->request->filled('category') ? Category::whereNull('deleted_at')->find($this->request->query('category')) : null;

        return [
            'categories' => $this->categoryOptions(),
            'countries' => $this->countries(),
            'category' => $category,
            'overrides' => $category ? CountryCategory::where('category_id', $category->id)->with('country:id,name_en,name_ar')->get() : collect(),
            'tiers' => $category ? CategoryCommissionTier::where('category_id', $category->id)->orderBy('country_id')->orderBy('sort_order')->get() : collect(),
        ];
    }

    /** @return array<string, mixed> */
    private function vendorCommission(): array
    {
        $vendors = $this->vendorQuery()->orderBy('store_name')->limit(self::OPTION_LIMIT)->get(['id', 'store_name', 'commission_rate', 'commission_discount_type', 'commission_discount_flat', 'commission_discount_percentage', 'commission_discount_notes']);
        $selected = $this->request->filled('vendor') ? $this->vendorQuery()->find($this->request->query('vendor')) : null;

        return ['vendors' => $vendors, 'vendor' => $selected];
    }

    /** @return array<string, mixed> */
    private function acquisition(): array
    {
        return [
            'vendors' => $this->vendorQuery()->orderBy('store_name')->limit(self::OPTION_LIMIT)->get(['id', 'store_name']),
            'agents' => Admin::orderBy('name')->limit(self::OPTION_LIMIT)->get(['id', 'name', 'email']),
            'deals' => VendorAcquisitionCommission::query()->with(['vendor:id,store_name', 'admin:id,name'])
                ->when($this->scopedVendorIds !== null, fn (Builder $q) => $q->whereIn('vendor_id', $this->scopedVendorIds))
                ->latest()->paginate(CommissionTabData::ROW_LIMIT)->withQueryString(),
        ];
    }

    /** @return array<string, mixed> */
    private function marketerRules(): array
    {
        $editing = $this->request->filled('edit') ? MarketerCommissionRule::with('marketer:id,name')->whereNotNull('marketer_id')->find($this->request->query('edit')) : null;

        return [
            'marketers' => Marketer::orderBy('name')->limit(self::OPTION_LIMIT)->get(['id', 'name', 'email']),
            'categoriesByScope' => [
                MarketerCommissionRule::SCOPE_PRODUCTS => $this->options(Category::query()->whereNull('deleted_at'), 'name_en'),
                MarketerCommissionRule::SCOPE_OPEN_MARKET => $this->options(ClassifiedCategory::query(), 'name_en'),
                MarketerCommissionRule::SCOPE_TRAVEL => $this->options(TravelCategory::query(), 'name_en'),
            ],
            'rules' => MarketerCommissionRule::query()->with('marketer:id,name')->whereNotNull('marketer_id')->latest()->paginate(CommissionTabData::ROW_LIMIT)->withQueryString(),
            'editing' => $editing,
        ];
    }

    /** @return array<string, mixed> */
    private function campaigns(): array
    {
        return [
            'campaigns' => MarketerCampaign::query()
                ->orderByRaw("case when status = 'pending_admin' then 0 else 1 end")
                ->latest()->paginate(CommissionTabData::ROW_LIMIT)->withQueryString(),
        ];
    }

    /** @return array<string, mixed> */
    private function countryAmounts(): array
    {
        return [
            'categories' => $this->categoryOptions(),
            'countries' => $this->countries(),
            'rows' => MarketerCommissionCountrySetting::query()->with(['category:id,name_en,name_ar', 'country:id,name_en,name_ar'])->latest()->paginate(CommissionTabData::ROW_LIMIT)->withQueryString(),
        ];
    }

    /** @return array<string, mixed> */
    private function flashBonus(): array
    {
        return [
            'flashSales' => FlashSale::orderByDesc('created_at')->limit(100)->get(['id', 'name_en', 'name_ar', 'status']),
            'marketers' => Marketer::orderBy('name')->limit(self::OPTION_LIMIT)->get(['id', 'name', 'email']),
        ];
    }

    /** @return array<string, mixed> */
    private function shippingRules(): array
    {
        $listing = $this->request->filled('listing') ? AdminListing::with('productVariant.product:id,name_en')->find($this->request->query('listing')) : null;

        return [
            'listings' => AdminListing::with('productVariant.product:id,name_en')->latest()->limit(200)->get(['id', 'product_variant_id']),
            'listing' => $listing,
            'rule' => $listing ? MarketplaceShippingRule::where('admin_listing_id', $listing->id)->first() : null,
        ];
    }

    /** @return Builder<Vendor> */
    private function vendorQuery(): Builder
    {
        return Vendor::query()->when($this->scopedVendorIds !== null, fn (Builder $q) => $q->whereIn('id', $this->scopedVendorIds));
    }

    /** @return Collection<int, Category> */
    private function categoryOptions(): Collection
    {
        return Category::whereNull('deleted_at')->orderBy('name_en')->limit(self::OPTION_LIMIT)->get(['id', 'name_en', 'name_ar']);
    }

    /** @return Collection<int, Country> */
    private function countries(): Collection
    {
        return Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'currency_code']);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Collection<int, array{id: string, name: string}>
     */
    private function options(Builder $query, string $column): Collection
    {
        return $query->orderBy($column)->limit(self::OPTION_LIMIT)->get(['id', 'name_en', 'name_ar'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => (string) ($m->name_en ?: $m->name_ar)]);
    }
}
