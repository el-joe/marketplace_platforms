<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductCountrySetting;
use App\Models\ProductHighlight;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\VendorListing;
use App\Models\VendorProductCertification;
use App\Services\ProductService;
use App\Services\VariantSlugService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(
        private readonly ProductService $productService,
        private readonly VariantSlugService $variantSlugService,
    ) {
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Index / Listing
    // ──────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportProducts($request);
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->pluck('name_en', 'id');

        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->pluck('name_en', 'id');

        return view('admin.products.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Products'],
            ],
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    /**
     * Shared base query for the datatable and export, with request-driven filters applied.
     *
     * Note: `products` has no `sku` or `is_active` column (see migrations). `sku` lives on the
     * default `product_variants` row, and "active" is expressed via the `status` enum
     * (draft|active|discontinued|restricted). Both are substituted accordingly below.
     */
    private function buildProductsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Product::query()
            ->leftJoin('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'products.brand_id')
            ->whereNull('products.deleted_at')
            ->select([
                'products.id',
                'products.name_en',
                'products.name_ar',
                'products.status',
                'products.is_featured',
                'products.total_sold',
                'products.created_at',
                'c.name_en as category_name',
                'b.name_en as brand_name',
            ])
            ->addSelect([
                'primary_image' => ProductImage::select('path')
                    ->whereColumn('product_id', 'products.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
                'seller_count' => VendorListing::selectRaw('COUNT(*)')
                    ->join('product_variants', 'product_variants.id', '=', 'vendor_listings.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('vendor_listings.status', 'active')
                    ->whereNull('vendor_listings.deleted_at'),
                'rating_avg' => VendorListing::selectRaw('SUM(vendor_listings.rating_avg * vendor_listings.rating_count) / NULLIF(SUM(vendor_listings.rating_count), 0)')
                    ->join('product_variants', 'product_variants.id', '=', 'vendor_listings.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('vendor_listings.status', 'active')
                    ->whereNull('vendor_listings.deleted_at'),
                'sku' => ProductVariant::select('sku')
                    ->whereColumn('product_id', 'products.id')
                    ->whereNull('deleted_at')
                    ->orderByDesc('is_default')
                    ->orderBy('position')
                    ->limit(1),
            ]);

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('products.status', $v),
            'category_id' => fn($q, $v) => $q->where('products.category_id', $v),
            'brand_id' => fn($q, $v) => $q->where('products.brand_id', $v),
            // Substitution: no products.is_active column exists; "active" maps to status = active.
            'is_active' => fn($q, $v) => $v
                ? $q->where('products.status', 'active')
                : $q->where('products.status', '!=', 'active'),
            'search' => fn($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('products.name_en', 'like', "%{$v}%")
                    ->orWhere('products.name_ar', 'like', "%{$v}%")
                    ->orWhereExists(function ($existsQuery) use ($v) {
                        $existsQuery->select(DB::raw(1))
                            ->from('product_variants')
                            ->whereColumn('product_variants.product_id', 'products.id')
                            ->whereNull('product_variants.deleted_at')
                            ->where('product_variants.sku', 'like', "%{$v}%");
                    });
            }),
        ]);

        return $query;
    }

    private function exportProducts(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $products = $this->buildProductsQuery($request)->orderByDesc('products.created_at')->get();

        $headers = ['ID', 'Name EN', 'Name AR', 'SKU', 'Category', 'Brand', 'Active', 'Created'];

        $rows = $products->map(fn($row) => [
            $row->id,
            $row->name_en,
            $row->name_ar,
            $row->sku,
            $row->category_name,
            $row->brand_name,
            $row->status?->value === 'active' ? 'Yes' : 'No',
            optional($row->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('products', $headers, $rows),
            'csv' => $this->exportCsv('products', $headers, $rows),
            'word' => $this->exportWord('products', 'Products', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = $this->columnDefinitions();

        $query = $this->buildProductsQuery($request);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'image' => $row->primary_image ? Storage::url($row->primary_image) : null,
                'name_en' => e($row->name_en),
                'name_ar' => e($row->name_ar ?? ''),
                'category' => e($row->category_name ?? '—'),
                'brand' => e($row->brand_name ?? '—'),
                'status' => $row->status?->value,
                'is_featured' => (bool) $row->is_featured,
                'seller_count' => (int) $row->seller_count,
                'rating_avg' => $row->rating_avg ? number_format((float) $row->rating_avg, 1) : '—',
                'total_sold' => (int) $row->total_sold,
                'created_at' => $row->created_at,
                'edit_url' => route('admin.products.edit', $row->id),
                'delete_url' => route('admin.products.destroy', $row->id),
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ──────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.products.create', $this->formData());
    }

    public function store(StoreProductRequest $request): JsonResponse|RedirectResponse
    {
        DB::beginTransaction();
        // try {
        $id = (string) Str::uuid();
        $slug = $request->slug ?: Str::slug($request->name_en) . '-' . Str::lower(Str::random(5));

        Product::query()->insert([
            'id' => $id,
            'name_en' => $request->name_en,
            'name_ar' => $request->name_ar,
            'slug' => $slug,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id ?: null,
            'gtin' => $request->gtin ?: null,
            'model_number' => $request->model_number ?: null,
            'description_en' => $request->description_en ?: null,
            'description_ar' => $request->description_ar ?: null,
            'short_desc_en' => $request->short_desc_en ?: null,
            'short_desc_ar' => $request->short_desc_ar ?: null,
            'status' => $request->status ?? 'draft',
            'has_variants' => $request->boolean('has_variants'),
            'is_featured' => $request->boolean('is_featured'),
            'requires_brand_auth' => $request->boolean('requires_brand_auth'),
            'is_age_restricted' => $request->boolean('is_age_restricted'),
            'min_age' => $request->min_age ?: null,
            'is_hazardous' => $request->boolean('is_hazardous'),
            'seo_title_en' => $request->seo_title ?: null,
            'seo_description_en' => $request->seo_description ?: null,
            'total_sold' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Default variant when product has no variants
        if (!$request->boolean('has_variants')) {
            ProductVariant::query()->insert([
                'id' => (string) Str::uuid(),
                'product_id' => $id,
                'sku' => $this->resolveVariantSku(null),
                'is_default' => true,
                'is_active' => true,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $this->syncVariants($id, $request->input('variants', []));
        }

        // Country settings for all launched countries
        $this->syncCountrySettings($id, $request->input('countries', []));

        // Attach uploaded images to this product
        $this->syncImages($id, $request->input('images', []));

        $this->syncHighlights($id, $request->input('highlights', []));
        $this->syncSpecifications($id, $request->input('specifications', []));

        DB::commit();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.products.index'),
            ]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');

        // } catch (\Throwable $e) {
        //     DB::rollBack();
        //     Log::error('Product creation failed', ['error' => $e->getMessage()]);

        //     if ($request->expectsJson()) {
        //         return response()->json(['message' => 'Failed to create product.'], 500);
        //     }

        //     return back()->withInput()->withErrors(['error' => 'Failed to create product. Please try again.']);
        // }
    }

    public function validateStore(StoreProductRequest $request): JsonResponse
    {
        return response()->json(['valid' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ──────────────────────────────────────────────────────────────────────────

    public function validateUpdate(UpdateProductRequest $request, string $product): JsonResponse
    {
        return response()->json(['valid' => true]);
    }

    public function edit(string $product): View
    {
        $productData = Product::query()->where('id', $product)->whereNull('deleted_at')->firstOrFail();

        // Alias bilingual SEO columns to the names the form expects
        $productData->seo_title = $productData->seo_title_en ?? null;
        $productData->seo_description = $productData->seo_description_en ?? null;

        $variants = ProductVariant::query()
            ->where('product_id', $product)
            ->whereNull('deleted_at')
            ->orderBy('position')
            ->get();

        $this->attachBestListingUrls($variants);

        $variantImageCounts = ProductImage::query()
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->selectRaw('product_variant_id, count(*) as total')
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id');

        $variants->each(function (ProductVariant $variant) use ($variantImageCounts) {
            $variant->images_count = (int) ($variantImageCounts[$variant->id] ?? 0);
        });

        $images = ProductImage::query()->from('product_images as pi')
            ->where('pi.product_id', $product)
            ->orderBy('pi.position')
            ->select('pi.id', 'pi.path', 'pi.is_primary', 'pi.alt_text_en', 'pi.size_bytes', 'pi.mime_type')
            ->get();

        $countrySettings = ProductCountrySetting::query()
            ->where('product_id', $product)
            ->get()
            ->keyBy('country_id');

        $categoryAttributes = [];
        if ($productData->category_id) {
            $categoryAttributes = CategoryAttribute::query()->from('category_attributes as ca')
                ->join('attributes as a', 'a.id', '=', 'ca.attribute_id')
                ->where('ca.category_id', $productData->category_id)
                ->where('a.is_variant_attribute', true)
                ->select('a.id', 'a.name_en')
                ->orderBy('a.sort_order')
                ->get();

            $valuesByAttr = DB::table('attribute_values')
                ->whereIn('attribute_id', $categoryAttributes->pluck('id'))
                ->orderBy('sort_order')
                ->get(['id', 'attribute_id', 'value_en'])
                ->groupBy('attribute_id');

            $categoryAttributes->each(function ($attr) use ($valuesByAttr) {
                $attr->values = $valuesByAttr->get($attr->id) ?? collect();
            });
        }

        $existingAttrValueIds = ProductVariantAttribute::query()
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->pluck('attribute_value_id')
            ->unique()
            ->values()
            ->toArray();

        $vendorCertCounts = VendorProductCertification::where('product_id', $product)
            ->selectRaw('country_id, status, count(*) as total')
            ->groupBy('country_id', 'status')
            ->get()
            ->groupBy('country_id');

        $highlights = ProductHighlight::query()
            ->where('product_id', $product)
            ->orderBy('position')
            ->get();

        $specifications = ProductSpecification::query()
            ->where('product_id', $product)
            ->orderBy('position')
            ->get();

        return view('admin.products.edit', array_merge($this->formData(), [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Products', 'url' => route('admin.products.index')],
                ['label' => e($productData->name_en)],
            ],
            'product' => $productData,
            'variants' => $variants,
            'images' => $images,
            'countrySettings' => $countrySettings,
            'vendorCertCounts' => $vendorCertCounts,
            'categoryAttributes' => $categoryAttributes,
            'existingAttrValues' => $existingAttrValueIds,
            'highlights' => $highlights,
            'specifications' => $specifications,
        ]));
    }

    public function update(UpdateProductRequest $request, string $product): JsonResponse
    {
        Product::query()->where('id', $product)->whereNull('deleted_at')->firstOrFail();

        DB::beginTransaction();
        // try {
        $data = [
            'name_en' => $request->name_en,
            'name_ar' => $request->name_ar,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id ?: null,
            'gtin' => $request->gtin ?: null,
            'model_number' => $request->model_number ?: null,
            'description_en' => $request->description_en ?: null,
            'description_ar' => $request->description_ar ?: null,
            'short_desc_en' => $request->short_desc_en ?: null,
            'short_desc_ar' => $request->short_desc_ar ?: null,
            'status' => $request->status,
            'has_variants' => $request->boolean('has_variants'),
            'is_featured' => $request->boolean('is_featured'),
            'requires_brand_auth' => $request->boolean('requires_brand_auth'),
            'is_age_restricted' => $request->boolean('is_age_restricted'),
            'min_age' => $request->min_age ?: null,
            'is_hazardous' => $request->boolean('is_hazardous'),
            'seo_title_en' => $request->seo_title ?: null,
            'seo_description_en' => $request->seo_description ?: null,
            'updated_at' => now(),
        ];

        if ($request->filled('slug')) {
            $data['slug'] = $request->slug;
        }

        Product::query()->where('id', $product)->update($data);

        if ($request->boolean('has_variants') && $request->has('variants')) {
            $this->syncVariants($product, $request->input('variants', []), update: true);
        }

        if ($request->has('countries')) {
            $this->syncCountrySettings($product, $request->input('countries', []), update: true);
        }

        $this->syncImages($product, $request->input('images', []));

        $this->syncHighlights($product, $request->input('highlights', []), update: true);
        $this->syncSpecifications($product, $request->input('specifications', []), update: true);

        DB::commit();

        return response()->json(['success' => true, 'message' => 'Product updated successfully.']);

        // } catch (\Throwable $e) {
        //     DB::rollBack();
        //     Log::error('Product update failed', ['id' => $product, 'error' => $e->getMessage()]);
        //     return response()->json(['message' => 'Failed to update product.'], 500);
        // }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Destroy / Bulk
    // ──────────────────────────────────────────────────────────────────────────

    public function destroy(string $product): JsonResponse
    {
        $productData = Product::where('id', $product)->whereNull('deleted_at')->firstOrFail();
        $activeSellers = VendorListing::query()
            ->whereIn('product_variant_id', $productData->variants->pluck('id'))
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        if ($activeSellers > 0) {
            return response()->json([
                'message' => "Cannot delete: {$activeSellers} active vendor listing(s) reference this product.",
            ], 422);
        }

        Product::query()
            ->where('id', $product)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Product deleted.']);
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:delete,publish,archive,feature',
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $ids = $request->input('ids');
        $action = $request->input('action');

        switch ($action) {
            case 'delete':
                Product::query()->whereIn('id', $ids)->update(['deleted_at' => now(), 'updated_at' => now()]);
                $message = count($ids) . ' product(s) deleted.';
                break;
            case 'publish':
                Product::query()->whereIn('id', $ids)->update(['status' => 'active', 'updated_at' => now()]);
                $message = count($ids) . ' product(s) published.';
                break;
            case 'archive':
                Product::query()->whereIn('id', $ids)->update(['status' => 'discontinued', 'updated_at' => now()]);
                $message = count($ids) . ' product(s) archived.';
                break;
            case 'feature':
                Product::query()->whereIn('id', $ids)->update(['is_featured' => true, 'updated_at' => now()]);
                $message = count($ids) . ' product(s) featured.';
                break;
            default:
                return response()->json(['message' => 'Unknown action.'], 422);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Variant generation
    // ──────────────────────────────────────────────────────────────────────────

    public function generateVariants(Request $request): JsonResponse
    {
        $request->validate(['value_ids' => 'required|array|min:1']);

        $grouped = Attribute::query()->from('attributes as a')
            ->join('attribute_values as av', 'av.attribute_id', '=', 'a.id')
            ->whereIn('av.id', $request->input('value_ids'))
            ->select('a.id as attr_id', 'a.name_en as attr_name', 'av.id as value_id', 'av.value_en as value_name')
            ->orderBy('a.sort_order')
            ->orderBy('av.sort_order')
            ->get()
            ->groupBy('attr_id');

        if ($grouped->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // Cartesian product
        $combinations = [[]];
        foreach ($grouped as $values) {
            $next = [];
            foreach ($combinations as $combo) {
                foreach ($values as $v) {
                    $next[] = array_merge($combo, [
                        [
                            'attr_id' => $v->attr_id,
                            'attr_name' => $v->attr_name,
                            'value_id' => $v->value_id,
                            'value_name' => $v->value_name,
                        ]
                    ]);
                }
            }
            $combinations = $next;
        }

        $variants = [];
        foreach ($combinations as $i => $combo) {
            $variants[] = [
                'index' => $i,
                'name' => collect($combo)->pluck('value_name')->join(' / '),
                'attributes' => $combo,
                'sku' => '',
                'barcode' => '',
                'weight_grams' => '',
                'is_default' => $i === 0,
                'is_active' => true,
            ];
        }

        return response()->json(['data' => $variants]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Variant slug management
    // ──────────────────────────────────────────────────────────────────────────

    public function regenerateVariantSlug(string $product, string $variant): JsonResponse
    {
        $variantModel = ProductVariant::query()
            ->where('id', $variant)
            ->where('product_id', $product)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $newSlug = $this->variantSlugService->buildSlug($variantModel);

        if ($newSlug === '') {
            return response()->json(['message' => 'Unable to build a slug: variant has no attribute values.'], 422);
        }

        $exists = ProductVariant::query()
            ->where('product_id', $product)
            ->where('slug', $newSlug)
            ->where('id', '!=', $variantModel->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return response()->json(['message' => "The slug '{$newSlug}' is already in use by another variant."], 422);
        }

        ProductVariant::query()->where('id', $variantModel->id)->update([
            'slug' => $newSlug,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'new_slug' => $newSlug]);
    }

    public function slugPreview(string $product, string $variant): JsonResponse
    {
        $productModel = Product::query()->whereNull('deleted_at')->findOrFail($product);

        $variantModel = ProductVariant::query()
            ->where('id', $variant)
            ->where('product_id', $product)
            ->whereNull('deleted_at')
            ->firstOrFail();

        return response()->json([
            'variant_slug' => $variantModel->slug,
            'product_slug' => $productModel->slug,
            'preview_url' => "/products/{$productModel->slug}/{$variantModel->slug}?listing=preview",
            'attribute_summary' => $variantModel->attributeSummary(),
        ]);
    }

    public function variantUrlInfo(string $variant): JsonResponse
    {
        $variantModel = ProductVariant::query()
            ->where('id', $variant)
            ->whereNull('deleted_at')
            ->with('product')
            ->firstOrFail();

        $attributes = $variantModel->variantAttributeValues()
            ->with('attribute')
            ->get()
            ->map(fn ($value) => [
                'name' => $value->attribute?->name_en,
                'value' => $value->value_en,
            ]);

        return response()->json([
            'variant_id' => $variantModel->id,
            'variant_name' => $variantModel->variant_name,
            'product_slug' => $variantModel->product?->slug,
            'product_name_en' => $variantModel->product?->name_en,
            'attribute_summary' => $attributes
                ->map(fn ($attr) => "{$attr['name']}: {$attr['value']}")
                ->implode(' | '),
            'preview_url' => "/products/{$variantModel->id}/(new listing — ID assigned after save)",
        ]);
    }

    public function variantDetail(string $product, string $variant): JsonResponse
    {
        $variantModel = ProductVariant::query()
            ->where('id', $variant)
            ->where('product_id', $product)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $attributes = $variantModel->variantAttributeValues()
            ->with('attribute')
            ->get()
            ->map(fn($value) => [
                'name' => $value->attribute?->name_en,
                'value' => $value->value_en,
            ])
            ->values();

        $imagesCount = $variantModel->images()->count();
        $vendorListingCount = $variantModel->vendorListings()->whereNull('deleted_at')->count();
        $adminListingCount = $variantModel->adminListings()->whereNull('deleted_at')->count();

        $bestListingId = $this->cheapestActiveVendorListingId($variantModel->id);

        return response()->json([
            'data' => [
                'variant_id' => $variantModel->id,
                'slug' => $variantModel->slug,
                'sku' => $variantModel->sku,
                'attributes' => $attributes,
                'attribute_summary' => $attributes
                    ->map(fn ($attr) => "{$attr['name']}: {$attr['value']}")
                    ->implode(' | '),
                'images_count' => $imagesCount,
                'vendor_listing_count' => $vendorListingCount,
                'admin_listing_count' => $adminListingCount,
                'listing_count' => $vendorListingCount + $adminListingCount,
                'best_listing_id' => $bestListingId,
                'customer_url' => $bestListingId
                    ? "/products/{$variantModel->id}/{$bestListingId}"
                    : null,
            ],
        ]);
    }

    /**
     * Attach `best_listing_id` and `customer_url` to each variant: the cheapest
     * active VendorListing for that variant across all countries (admin sees all).
     */
    private function attachBestListingUrls(\Illuminate\Support\Collection $variants): void
    {
        if ($variants->isEmpty()) {
            return;
        }

        $bestListingIds = VendorListing::query()
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->where('status', \App\Enums\VendorListingStatus::Active)
            ->whereNull('deleted_at')
            ->orderBy('price')
            ->get(['id', 'product_variant_id'])
            ->groupBy('product_variant_id')
            ->map(fn ($listings) => $listings->first()->id);

        $variants->each(function (ProductVariant $variant) use ($bestListingIds) {
            $bestListingId = $bestListingIds->get($variant->id);
            $variant->best_listing_id = $bestListingId;
            $variant->customer_url = $bestListingId
                ? "/products/{$variant->id}/{$bestListingId}"
                : null;
        });
    }

    private function cheapestActiveVendorListingId(string $variantId): ?string
    {
        return VendorListing::query()
            ->where('product_variant_id', $variantId)
            ->where('status', \App\Enums\VendorListingStatus::Active)
            ->whereNull('deleted_at')
            ->orderBy('price')
            ->value('id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Image management
    // ──────────────────────────────────────────────────────────────────────────

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'image|max:5120', // 5 MB per image
            'variant_id' => 'nullable|string',
        ]);

        $variantId = $request->input('variant_id');
        $variant = $variantId ? ProductVariant::query()->find($variantId) : null;

        $images = $request->file('images');
        $ids = [];

        foreach ($images as $file) {
            $path = $variant
                ? $file->store("products/variants/{$variant->id}", 'public')
                : $file->store('products/images', 'public');

            $imageId = (string) Str::uuid();

            ProductImage::query()->insert([
                'id' => $imageId,
                'product_id' => $variant?->product_id,
                'product_variant_id' => $variant?->id,
                'path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'position' => 0,
                'is_primary' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ids[] = $imageId;
        }


        return response()->json([
            'ids' => $ids,
            'urls' => array_map(fn($id) => Storage::url(ProductImage::query()->where('id', $id)->value('path')), $ids),
            'filenames' => array_map(fn($id) => ProductImage::query()->where('id', $id)->value('path'), $ids),
        ]);
    }

    public function deleteImage(string $mediaId): JsonResponse
    {
        $image = ProductImage::query()->where('id', $mediaId)->first();

        if (!$image) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        Storage::disk($image->disk ?? 'public')->delete($image->path);

        ProductImage::query()->where('id', $mediaId)->delete();

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GTIN duplicate check
    // ──────────────────────────────────────────────────────────────────────────

    public function checkDuplicate(Request $request): JsonResponse
    {
        $gtin = trim($request->input('gtin', ''));

        if (strlen($gtin) !== 13) {
            return response()->json(['data' => ['exists' => false]]);
        }

        $product = Product::query()
            ->where('gtin', $gtin)
            ->whereNull('deleted_at')
            ->select('id', 'name_en', 'status')
            ->first();

        if (!$product) {
            return response()->json(['data' => ['exists' => false]]);
        }

        return response()->json([
            'data' => [
                'exists' => true,
                'product' => [
                    'id' => $product->id,
                    'name_en' => $product->name_en,
                    'status' => $product->status?->value,
                    'url' => route('admin.products.edit', $product->id),
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GTIN check (spec alias, supports 8/12/13/14 digits)
    // ──────────────────────────────────────────────────────────────────────────

    public function checkGtin(Request $request): JsonResponse
    {
        $gtin = trim($request->input('gtin', ''));
        $exceptId = $request->input('except_id');

        if (!preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $gtin)) {
            return response()->json(['data' => ['exists' => false]]);
        }

        $product = $this->productService->checkGtinDuplicate($gtin, $exceptId);

        if (!$product) {
            return response()->json(['data' => ['exists' => false]]);
        }

        return response()->json([
            'data' => [
                'exists' => true,
                'product' => [
                    'id' => $product->id,
                    'name_en' => $product->name_en,
                    'edit_url' => route('admin.products.edit', $product->id),
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Image reordering
    // ──────────────────────────────────────────────────────────────────────────

    public function reorderImages(Request $request, string $product): JsonResponse
    {
        $request->validate(['ordered_ids' => ['required', 'array'], 'ordered_ids.*' => ['string']]);

        $model = Product::findOrFail($product);
        $this->productService->reorderImages($model, $request->input('ordered_ids'));

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Variant image management
    // ──────────────────────────────────────────────────────────────────────────

    public function variantImages(string $product, string $variant): JsonResponse
    {
        $variantModel = ProductVariant::query()
            ->where('id', $variant)
            ->where('product_id', $product)
            ->firstOrFail();

        $images = ProductImage::query()
            ->where('product_variant_id', $variantModel->id)
            ->orderBy('position')
            ->get(['id', 'path', 'disk', 'is_primary', 'position']);

        return response()->json([
            'images' => $images->map(fn(ProductImage $image) => [
                'id' => $image->id,
                'url' => $image->url,
                'is_primary' => $image->is_primary,
                'position' => $image->position,
            ])->values(),
        ]);
    }

    public function reorderVariantImages(Request $request, string $product, string $variant): JsonResponse
    {
        $request->validate(['ordered_ids' => ['required', 'array'], 'ordered_ids.*' => ['string']]);

        $variantModel = ProductVariant::query()
            ->where('id', $variant)
            ->where('product_id', $product)
            ->firstOrFail();

        foreach ($request->input('ordered_ids') as $pos => $imageId) {
            ProductImage::query()
                ->where('id', $imageId)
                ->where('product_variant_id', $variantModel->id)
                ->update(['position' => $pos]);
        }

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Country settings
    // ──────────────────────────────────────────────────────────────────────────

    public function countrySettings(string $product): JsonResponse
    {
        $settings = ProductCountrySetting::where('product_id', $product)
            ->with('country:id,name_en,name_ar,iso_code')
            ->get();

        return response()->json(['data' => $settings]);
    }

    public function updateCountrySetting(Request $request, string $setting): JsonResponse
    {
        $row = ProductCountrySetting::findOrFail($setting);

        $validated = $request->validate([
            'field' => ['required', 'string', 'in:is_available,name_override_en,name_override_ar,requires_local_cert,seo_title'],
            'value' => ['present'],
        ]);

        $row->update([$validated['field'] => $validated['value']]);

        return response()->json(['success' => true, 'data' => $row->fresh()]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Frequently bought together
    // ──────────────────────────────────────────────────────────────────────────

    public function frequentlyBoughtTogetherIndex(string $product): JsonResponse
    {
        $items = Product::findOrFail($product)
            ->frequentlyBoughtTogether()
            ->get(['products.id', 'products.name_en']);

        return response()->json([
            'results' => $items->map(fn($item) => [
                'id'   => $item->id,
                'text' => $item->name_en,
                'position' => $item->pivot->position,
            ])->values(),
        ]);
    }

    public function frequentlyBoughtTogetherSearch(Request $request, string $product): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $existingIds = Product::findOrFail($product)->frequentlyBoughtTogether()->pluck('products.id');

        $rows = Product::query()
            ->where('id', '!=', $product)
            ->whereNotIn('id', $existingIds)
            ->whereNull('deleted_at')
            ->when($q !== '', fn($query) => $query->where('name_en', 'like', "%{$q}%"))
            ->limit(20)
            ->get(['id', 'name_en']);

        return response()->json([
            'results' => $rows->map(fn($p) => ['id' => $p->id, 'text' => $p->name_en])->values(),
        ]);
    }

    public function frequentlyBoughtTogetherAdd(Request $request, string $product): JsonResponse
    {
        $data = $request->validate([
            'related_product_id' => ['required', 'uuid', 'exists:products,id'],
        ]);

        if ($data['related_product_id'] === $product) {
            return response()->json(['message' => 'A product cannot be paired with itself.'], 422);
        }

        $productModel = Product::findOrFail($product);

        $nextPosition = (int) $productModel->frequentlyBoughtTogether()->max('position') + 1;

        $productModel->frequentlyBoughtTogether()->syncWithoutDetaching([
            $data['related_product_id'] => ['position' => $nextPosition],
        ]);

        $related = Product::findOrFail($data['related_product_id']);

        return response()->json([
            'item' => ['id' => $related->id, 'text' => $related->name_en, 'position' => $nextPosition],
        ]);
    }

    public function frequentlyBoughtTogetherRemove(string $product, string $relatedProduct): JsonResponse
    {
        Product::findOrFail($product)->frequentlyBoughtTogether()->detach($relatedProduct);

        return response()->json(['success' => true]);
    }

    public function frequentlyBoughtTogetherReorder(Request $request, string $product): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ]);

        $productModel = Product::findOrFail($product);

        foreach ($data['items'] as $row) {
            $productModel->frequentlyBoughtTogether()->updateExistingPivot($row['id'], ['position' => $row['position']]);
        }

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function formData(): array
    {
        $data = [
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name_en')
                ->pluck('name_en', 'id'),

            'brands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('name_en')
                ->pluck('name_en', 'id'),

            'countries' => Country::query()
                ->where('is_active', true)
                ->where('is_launched', true)
                ->orderBy('name_en')
                ->get(),

            'categoryAttributes' => [],
        ];

        return $data;
    }

    private function syncVariants(string $productId, array $variants, bool $update = false): void
    {
        $incomingIds = collect($variants)
            ->pluck('id')
            ->filter(fn($id) => filled($id))
            ->values()
            ->all();

        if ($update) {
            // Soft-delete only variants removed from the submitted payload.
            ProductVariant::query()
                ->where('product_id', $productId)
                ->whereNull('deleted_at')
                ->when(!empty($incomingIds), fn($q) => $q->whereNotIn('id', $incomingIds))
                ->update(['deleted_at' => now(), 'updated_at' => now()]);
        }

        foreach ($variants as $i => $v) {
            $variantId = isset($v['id']) && $v['id'] !== '' ? (string) $v['id'] : null;

            $resolvedSku = $this->resolveVariantSku($v['sku'] ?? null, $variantId);

            $payload = [
                'sku' => $resolvedSku,
                'slug' => $this->resolveVariantSlug($productId, $v['slug'] ?? null, $variantId, $resolvedSku),
                'barcode' => $v['barcode'] ?: null,
                'weight_grams' => isset($v['weight_grams']) && $v['weight_grams'] !== '' ? (int) $v['weight_grams'] : null,
                'is_default' => isset($v['is_default']) && (bool) $v['is_default'],
                'is_active' => !isset($v['is_active']) || (bool) $v['is_active'],
                'position' => $i,
                'updated_at' => now(),
            ];

            if ($update && $variantId) {
                $updated = ProductVariant::query()
                    ->where('id', $variantId)
                    ->where('product_id', $productId)
                    ->update(array_merge($payload, ['deleted_at' => null]));

                if ($updated) {
                    if (array_key_exists('image_ids', $v)) {
                        $this->syncVariantImages($productId, $variantId, (array) $v['image_ids']);
                    }
                    continue;
                }
            }

            $newVariantId = (string) Str::uuid();

            ProductVariant::query()->insert(array_merge($payload, [
                'id' => $newVariantId,
                'product_id' => $productId,
                'created_at' => now(),
            ]));

            if (array_key_exists('image_ids', $v)) {
                $this->syncVariantImages($productId, $newVariantId, (array) $v['image_ids']);
            }
        }
    }

    private function resolveVariantSku(?string $requestedSku, ?string $ignoreVariantId = null): string
    {
        $sku = trim((string) $requestedSku);

        if ($sku === '') {
            return $this->generateUniqueVariantSku();
        }

        $exists = ProductVariant::query()
            ->where('sku', $sku)
            ->when($ignoreVariantId, fn($q) => $q->where('id', '!=', $ignoreVariantId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'variants' => ["The SKU '{$sku}' is already in use. Please provide a unique SKU."],
            ]);
        }

        return $sku;
    }

    private function resolveVariantSlug(string $productId, ?string $requestedSlug, ?string $ignoreVariantId, string $fallbackBase): string
    {
        $requestedSlug = trim((string) $requestedSlug);

        if ($requestedSlug !== '') {
            $slug = Str::slug($requestedSlug);

            $exists = ProductVariant::query()
                ->where('product_id', $productId)
                ->whereNull('deleted_at')
                ->where('slug', $slug)
                ->when($ignoreVariantId, fn($q) => $q->where('id', '!=', $ignoreVariantId))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'variants' => ["The slug '{$slug}' is already in use for this product. Please provide a unique slug."],
                ]);
            }

            return $slug;
        }

        $base = Str::slug($fallbackBase) ?: 'variant';
        $slug = $base;
        $counter = 1;

        while (
            ProductVariant::query()
                ->where('product_id', $productId)
                ->whereNull('deleted_at')
                ->where('slug', $slug)
                ->when($ignoreVariantId, fn($q) => $q->where('id', '!=', $ignoreVariantId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function generateUniqueVariantSku(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $candidate = 'SKU-' . strtoupper(Str::random(8));

            if (!ProductVariant::query()->where('sku', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'variants' => ['Unable to generate a unique SKU automatically. Please enter one manually.'],
        ]);
    }

    private function syncCountrySettings(string $productId, array $countriesInput, bool $update = false): void
    {
        $countries = Country::query()->where('is_launched', true)->get('id');

        foreach ($countries as $country) {
            $setting = $countriesInput[$country->id] ?? [];

            $exists = ProductCountrySetting::query()
                ->where('product_id', $productId)
                ->where('country_id', $country->id)
                ->exists();

            if ($exists && $update) {
                ProductCountrySetting::query()
                    ->where('product_id', $productId)
                    ->where('country_id', $country->id)
                    ->update([
                        'is_available' => (bool) ($setting['is_available'] ?? true),
                        'name_override_en' => $setting['name_override_en'] ?? null ?: null,
                        'name_override_ar' => $setting['name_override_ar'] ?? null ?: null,
                        'requires_local_cert' => (bool) ($setting['requires_local_cert'] ?? false),
                        'updated_at' => now(),
                    ]);
            } elseif (!$exists) {
                ProductCountrySetting::query()->insert([
                    'id' => (string) Str::uuid(),
                    'product_id' => $productId,
                    'country_id' => $country->id,
                    'is_available' => (bool) ($setting['is_available'] ?? true),
                    'name_override_en' => $setting['name_override_en'] ?? null ?: null,
                    'name_override_ar' => $setting['name_override_ar'] ?? null ?: null,
                    'requires_local_cert' => (bool) ($setting['requires_local_cert'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function syncImages(string $productId, array $imageIds): void
    {
        // Remove images belonging to this product that the user deleted from FilePond
        $removed = ProductImage::query()
            ->where('product_id', $productId)
            ->when(!empty($imageIds), fn($q) => $q->whereNotIn('id', $imageIds))
            ->get(['id', 'path', 'disk']);

        foreach ($removed as $img) {
            Storage::disk($img->disk ?? 'public')->delete($img->path);
            ProductImage::query()->where('id', $img->id)->delete();
        }

        // Assign product_id, position, and is_primary for each submitted image ID
        // (covers both newly uploaded images with product_id = null and existing ones)
        foreach ($imageIds as $i => $imageId) {
            ProductImage::query()
                ->where('id', $imageId)
                ->update([
                    'product_id' => $productId,
                    'position' => $i,
                    'is_primary' => $i === 0,
                    'updated_at' => now(),
                ]);
        }
    }

    private function syncVariantImages(string $productId, string $variantId, array $imageIds): void
    {
        // Remove variant images the user deleted before/after upload
        $removed = ProductImage::query()
            ->where('product_variant_id', $variantId)
            ->when(!empty($imageIds), fn($q) => $q->whereNotIn('id', $imageIds))
            ->get(['id', 'path', 'disk']);

        foreach ($removed as $img) {
            Storage::disk($img->disk ?? 'public')->delete($img->path);
            ProductImage::query()->where('id', $img->id)->delete();
        }

        // Re-parent submitted image IDs (uploaded pre-save as orphans, or already attached) to this variant
        foreach ($imageIds as $i => $imageId) {
            ProductImage::query()
                ->where('id', $imageId)
                ->update([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'position' => $i,
                    'is_primary' => $i === 0,
                    'updated_at' => now(),
                ]);
        }
    }

    private function syncHighlights(string $productId, array $highlights, bool $update = false): void
    {
        $rows = collect($highlights)
            ->filter(fn($h) => filled($h['text_en'] ?? null) && filled($h['text_ar'] ?? null))
            ->values();

        $incomingIds = $rows->pluck('id')->filter(fn($id) => filled($id))->values()->all();

        if ($update) {
            ProductHighlight::query()
                ->where('product_id', $productId)
                ->when(!empty($incomingIds), fn($q) => $q->whereNotIn('id', $incomingIds))
                ->delete();
        }

        foreach ($rows as $i => $h) {
            $highlightId = isset($h['id']) && $h['id'] !== '' ? (string) $h['id'] : null;

            $payload = [
                'text_en' => $h['text_en'],
                'text_ar' => $h['text_ar'],
                'position' => $i,
                'updated_at' => now(),
            ];

            if ($update && $highlightId) {
                $updated = ProductHighlight::query()
                    ->where('id', $highlightId)
                    ->where('product_id', $productId)
                    ->update($payload);

                if ($updated) {
                    continue;
                }
            }

            ProductHighlight::query()->insert(array_merge($payload, [
                'id' => (string) Str::uuid(),
                'product_id' => $productId,
                'created_at' => now(),
            ]));
        }
    }

    private function syncSpecifications(string $productId, array $specifications, bool $update = false): void
    {
        $rows = collect($specifications)
            ->filter(fn($s) => filled($s['key_en'] ?? null) && filled($s['key_ar'] ?? null) && filled($s['value_en'] ?? null) && filled($s['value_ar'] ?? null))
            ->values();

        $incomingIds = $rows->pluck('id')->filter(fn($id) => filled($id))->values()->all();

        if ($update) {
            ProductSpecification::query()
                ->where('product_id', $productId)
                ->when(!empty($incomingIds), fn($q) => $q->whereNotIn('id', $incomingIds))
                ->delete();
        }

        foreach ($rows as $i => $s) {
            $specificationId = isset($s['id']) && $s['id'] !== '' ? (string) $s['id'] : null;

            $payload = [
                'key_en' => $s['key_en'],
                'key_ar' => $s['key_ar'],
                'value_en' => $s['value_en'],
                'value_ar' => $s['value_ar'],
                'position' => $i,
                'updated_at' => now(),
            ];

            if ($update && $specificationId) {
                $updated = ProductSpecification::query()
                    ->where('id', $specificationId)
                    ->where('product_id', $productId)
                    ->update($payload);

                if ($updated) {
                    continue;
                }
            }

            ProductSpecification::query()->insert(array_merge($payload, [
                'id' => (string) Str::uuid(),
                'product_id' => $productId,
                'created_at' => now(),
            ]));
        }
    }

    private function columnDefinitions(): array
    {
        return [
            ['title' => '', 'data' => 'image', 'name' => 'image', 'orderable' => false, 'searchable' => false, 'className' => 'w-12 px-2'],
            ['title' => 'Name', 'data' => 'name_en', 'name' => 'name_en', 'searchable_columns' => ['products.name_en', 'products.name_ar'], 'orderable_column' => 'products.name_en'],
            ['title' => 'Category', 'data' => 'category', 'name' => 'category', 'orderable_column' => 'c.name_en', 'searchable' => false],
            ['title' => 'Brand', 'data' => 'brand', 'name' => 'brand', 'orderable_column' => 'b.name', 'searchable' => false],
            ['title' => 'Status', 'data' => 'status', 'name' => 'status', 'orderable_column' => 'products.status', 'searchable' => false],
            ['title' => 'Sellers', 'data' => 'seller_count', 'name' => 'seller_count', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
            ['title' => 'Rating', 'data' => 'rating_avg', 'name' => 'rating_avg', 'orderable_column' => 'rating_avg', 'searchable' => false, 'className' => 'text-right'],
            ['title' => 'Sold', 'data' => 'total_sold', 'name' => 'total_sold', 'orderable_column' => 'products.total_sold', 'searchable' => false, 'className' => 'text-right'],
            ['title' => 'Created', 'data' => 'created_at', 'name' => 'created_at', 'orderable_column' => 'products.created_at', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
        ];
    }
}
