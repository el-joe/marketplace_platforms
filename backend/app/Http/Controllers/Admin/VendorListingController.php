<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\ShippingMethod;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Services\ListingCertificationGate;
use App\Services\Shared\PageCacheService;
use App\Services\ShippingMethodResolverService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorListingController extends Controller
{
    use HasDataTable;

    public function index(): View
    {
        return view('admin.vendor-listings.index', [
            'countries' => Country::query()->orderBy('name_en')->pluck('name_en', 'id'),
            'statuses' => collect(VendorListingStatus::cases())
                ->mapWithKeys(fn($status) => [$status->value => Str::headline($status->value)]),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Vendor Listings'],
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['searchable_columns' => ['p.name_en', 'p.name_ar', 'pv.sku', 'vendor_listings.vendor_sku'], 'orderable_column' => 'p.name_en'],
            ['orderable_column' => 'pv.variant_name'],
            ['searchable_columns' => ['v.business_name'], 'orderable_column' => 'v.business_name'],
            ['searchable_columns' => ['co.name_en'], 'orderable_column' => 'co.name_en'],
            ['orderable_column' => 'vendor_listings.currency'],
            ['orderable_column' => 'vendor_listings.price'],
            ['orderable_column' => 'vendor_listings.status'],
            [],
            ['orderable_column' => 'vendor_listings.created_at'],
            [],
        ];

        $query = VendorListing::query()
            ->with(['primaryShippingMethod'])
            ->join('product_variants as pv', 'pv.id', '=', 'vendor_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('countries as co', 'co.id', '=', 'vendor_listings.country_id')
            ->join('vendors as v', 'v.id', '=', 'vendor_listings.vendor_id')
            ->leftJoin('category_shipping_methods as csm', function ($join) {
                $join->on('csm.category_id', '=', 'p.category_id')->where('csm.is_default', true);
            })
            ->leftJoin('shipping_methods as dsm', 'dsm.id', '=', 'csm.shipping_method_id')
            ->whereNull('p.deleted_at')
            ->select([
                'vendor_listings.id',
                'vendor_listings.primary_shipping_method_id',
                'vendor_listings.price',
                'vendor_listings.currency',
                'vendor_listings.status',
                'vendor_listings.created_at',
                'p.name_en as product_name',
                'pv.sku as variant_sku',
                'pv.variant_name as variant_name',
                'co.name_en as country_name',
                'v.business_name as vendor_name',
                'dsm.name as default_shipping_method_name',
                'dsm.badge_label_en as default_shipping_badge_label',
                'dsm.badge_color_hex as default_shipping_badge_color',
                'dsm.badge_text_color_hex as default_shipping_badge_text_color',
            ]);

        $query = $this->applyFilters($query, $request, [
            'country_id' => fn($q, $v) => $q->where('vendor_listings.country_id', $v),
            'status' => fn($q, $v) => $q->where('vendor_listings.status', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $effectiveMethod = $row->primaryShippingMethod ?? ($row->default_shipping_method_name ? (object) [
                'name' => $row->default_shipping_method_name,
                'badge_label_en' => $row->default_shipping_badge_label,
                'badge_color_hex' => $row->default_shipping_badge_color,
                'badge_text_color_hex' => $row->default_shipping_badge_text_color,
            ] : null);

            return [
                'id' => $row->id,
                'product_name' => e($row->product_name),
                'variant_name' => e($row->variant_name),
                'vendor_name' => e($row->vendor_name),
                'country' => e($row->country_name),
                'currency' => e($row->currency),
                'price' => $row->price,
                'status' => $row->status?->value,
                'shipping' => $effectiveMethod ? [
                    'label' => $effectiveMethod->badge_label_en ?: $effectiveMethod->name,
                    'color' => $effectiveMethod->badge_color_hex ?? '#e5e7eb',
                    'text_color' => $effectiveMethod->badge_text_color_hex ?? '#374151',
                    'is_inherited' => $row->primaryShippingMethod === null,
                ] : null,
                'created_at' => $row->created_at?->format('Y-m-d H:i'),
                'show_url' => route('admin.vendor-listings.show', $row->id),
                'edit_url' => route('admin.vendor-listings.edit', $row->id),
            ];
        });
    }

    public function show(VendorListing $vendorListing): View
    {
        $vendorListing->load([
            'productVariant.product.brand',
            'productVariant.product.category',
            'vendor',
            'country',
            'warehouse',
            'primaryShippingMethod',
            'marketplaceShippingRule',
        ]);

        $availableShippingMethods = app(ShippingMethodResolverService::class)
            ->getAvailableForListing($vendorListing->id, 'vendor_listing', $vendorListing->country_id);

        return view('admin.vendor-listings.show', [
            'listing' => $vendorListing,
            'availableShippingMethods' => $availableShippingMethods,
            'categoryDefaultShippingMethod' => $vendorListing->productVariant->product->category
                ?->defaultShippingMethod()->first(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Vendor Listings', 'url' => route('admin.vendor-listings.index')],
                ['label' => e($vendorListing->productVariant->product->name_en)],
            ],
        ]);
    }

    public function edit(VendorListing $vendorListing): View
    {
        return view('admin.vendor-listings.edit', [
            'listing' => $vendorListing,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'shippingMethods' => app(ShippingMethodResolverService::class)
                ->getAvailableForListing($vendorListing->id, 'vendor_listing', $vendorListing->country_id),
            'statuses' => collect(VendorListingStatus::cases())
                ->mapWithKeys(fn($status) => [$status->value => Str::headline($status->value)]),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Vendor Listings', 'url' => route('admin.vendor-listings.index')],
                ['label' => e($vendorListing->productVariant->product->name_en), 'url' => route('admin.vendor-listings.show', $vendorListing)],
                ['label' => 'Edit'],
            ],
        ]);
    }

    public function update(Request $request, VendorListing $vendorListing): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorListingStatus::class)],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'primary_shipping_method_id' => ['nullable', 'exists:shipping_methods,id'],
        ]);

        if ($data['status'] === VendorListingStatus::Active->value) {
            ListingCertificationGate::assertCanGoLive($vendorListing);
        }

        $vendorListing->update($data);

        return redirect()
            ->route('admin.vendor-listings.show', $vendorListing)
            ->with('success', 'Listing updated successfully.');
    }

    /**
     * POST /admin/vendor-listings/{vendorListing}/clear-cache
     * Clears all customer-facing API caches for this listing.
     */
    public function clearCache(VendorListing $vendorListing, PageCacheService $pageCache): JsonResponse
    {
        abort_unless(auth('admin')->user()?->hasPermissionTo('admin_listings.edit'), 403);

        $pageCache->bustVendorListing($vendorListing);

        return response()->json([
            'success' => true,
            'message' => 'All customer API caches cleared for this listing.',
        ]);
    }
}
