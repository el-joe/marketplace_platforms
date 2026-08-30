<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreCouponRequest;
use App\Http\Requests\Vendor\UpdateCouponRequest;
use App\Http\Resources\Vendor\VendorCouponResource;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\VendorListing;
use App\Policies\VendorCouponPolicy;
use App\Services\Vendor\CouponService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CouponController extends Controller
{
    use HasDataTable;

    public function __construct(
        private readonly CouponService $coupons,
        private readonly VendorCouponPolicy $policy,
    ) {
    }

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index / DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('vendor.coupons.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $vendorId = $this->vendorId();

        $columns = [
            ['title' => 'Code', 'data' => 'code', 'name' => 'code', 'orderable_column' => 'coupons.code', 'searchable_columns' => ['coupons.code', 'coupons.name']],
            ['title' => 'Name', 'data' => 'name', 'name' => 'name', 'searchable' => false],
            ['title' => 'Type', 'data' => 'type', 'name' => 'type', 'orderable_column' => 'coupons.type', 'searchable' => false],
            ['title' => 'Scope', 'data' => 'scope', 'name' => 'scope', 'orderable_column' => 'coupons.scope', 'searchable' => false],
            ['title' => 'Value', 'data' => 'value', 'name' => 'value', 'orderable_column' => 'coupons.value', 'searchable' => false],
            ['title' => 'Used', 'data' => 'times_used', 'name' => 'times_used', 'orderable_column' => 'coupons.times_used', 'searchable' => false],
            ['title' => 'Active', 'data' => 'is_active', 'name' => 'is_active', 'orderable_column' => 'coupons.is_active', 'searchable' => false],
            ['title' => 'Valid Until', 'data' => 'valid_until', 'name' => 'valid_until', 'orderable_column' => 'coupons.valid_until', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false],
        ];

        $query = Coupon::query()
            ->where('coupons.vendor_id', $vendorId)
            ->whereIn('coupons.scope', CouponService::VENDOR_MANAGEABLE_SCOPES)
            ->select([
                'coupons.id',
                'coupons.code',
                'coupons.name',
                'coupons.type',
                'coupons.scope',
                'coupons.value',
                'coupons.times_used',
                'coupons.usage_limit_total',
                'coupons.valid_until',
                'coupons.is_active',
            ]);

        $query = $this->applyFilters($query, $request, [
            'is_active' => fn ($q, $v) => $q->where('coupons.is_active', (bool) $v),
            'type' => fn ($q, $v) => $q->where('coupons.type', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'code' => e($row->code),
                'name' => e($row->name),
                'type' => $row->type->value,
                'scope' => $row->scope->value,
                'value' => $row->value,
                'times_used' => (int) $row->times_used,
                'usage_limit_total' => $row->usage_limit_total ? (int) $row->usage_limit_total : null,
                'valid_until' => $row->valid_until,
                'is_active' => (bool) $row->is_active,
                'is_expired' => $row->valid_until < now()->toDateTimeString(),
                'show_url' => route('partner.coupons.show', $row->id),
                'edit_url' => route('partner.coupons.edit', $row->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        $vendor = Auth::guard('vendor')->user()->vendor()->with('country')->first();
        $vendorCurrency = $vendor->country?->currency_code
            ?? throw new \RuntimeException("Vendor {$vendor->id} has no country configured.");

        return view('vendor.coupons.create', [
            'coupon' => null,
            'vendor_currency' => $vendorCurrency,
        ]);
    }

    public function productSearch(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $vendorId = $this->vendorId();

        $vendorProductIds = VendorListing::query()
            ->where('vendor_id', $vendorId)
            ->whereNotIn('status', ['archived'])
            ->with('productVariant:id,product_id')
            ->get()
            ->pluck('productVariant.product_id')
            ->filter()
            ->unique()
            ->values();

        if ($vendorProductIds->isEmpty()) {
            return response()->json([]);
        }

        $products = Product::query()
            ->whereIn('id', $vendorProductIds)
            ->where('status', 'active')
            ->where(function ($query) use ($q) {
                $query->where('name_en', 'like', '%' . $q . '%')
                    ->orWhere('name_ar', 'like', '%' . $q . '%')
                    ->orWhere('model_number', 'like', '%' . $q . '%')
                    ->orWhere('gtin', 'like', $q . '%');
            })
            ->addSelect([
                'primary_image' => ProductImage::select('path')
                    ->whereColumn('product_id', 'products.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
                'primary_image_disk' => ProductImage::select('disk')
                    ->whereColumn('product_id', 'products.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
            ])
            ->limit(20)
            ->get(['id', 'name_en', 'name_ar', 'model_number']);

        return response()->json($products->map(function ($product) {
            $imageUrl = null;
            if ($product->primary_image) {
                $imageUrl = Storage::disk($product->primary_image_disk ?? 'public')
                    ->url($product->primary_image);
            }

            return [
                'id' => $product->id,
                'name' => $product->name_ar ?: $product->name_en,
                'name_en' => $product->name_en,
                'name_ar' => $product->name_ar,
                'model' => $product->model_number,
                'image_url' => $imageUrl,
            ];
        }));
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $actor = Auth::guard('vendor')->user();

        abort_unless($this->policy->create($actor), 403);

        try {
            $coupon = $this->coupons->create($actor->vendor, $actor, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'success' => true,
            'redirect' => route('partner.coupons.show', $coupon->id),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show / Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $id): View
    {
        $coupon = Coupon::with('products:id,name_en,name_ar')->findOrFail($id);
        $actor = Auth::guard('vendor')->user();

        abort_unless($this->policy->view($actor, $coupon), 403);

        $couponData = VendorCouponResource::make($coupon)->resolve();

        $vendor = $actor->vendor()->with('country')->first();
        $vendorCurrency = $vendor->country?->currency_code ?? $couponData['currency'] ?? null;
        if (! $vendorCurrency) {
            abort(422, 'Cannot determine currency for this coupon.');
        }

        return view('vendor.coupons.create', [
            'coupon' => $couponData,
            'vendor_currency' => $vendorCurrency,
        ]);
    }

    public function edit(string $id): View
    {
        return $this->show($id);
    }

    public function update(UpdateCouponRequest $request, string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $actor = Auth::guard('vendor')->user();

        abort_unless($this->policy->update($actor, $coupon), 403);

        try {
            $this->coupons->update($coupon, $actor->vendor, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true]);
    }

    public function analytics(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        abort_unless($this->policy->view(Auth::guard('vendor')->user(), $coupon), 403);

        $dailyUsage = CouponUsage::where('coupon_id', $coupon->id)
            ->where('used_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(used_at) as date, COUNT(*) as uses, SUM(discount_amount) as total_discount')
            ->groupByRaw('DATE(used_at)')
            ->orderBy('date')
            ->get();

        $filled = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row = $dailyUsage->firstWhere('date', $date);
            $filled[] = [
                'date' => $date,
                'uses' => $row ? (int) $row->uses : 0,
                'total_discount' => $row ? (int) $row->total_discount : 0,
            ];
        }

        $totals = CouponUsage::where('coupon_id', $coupon->id)
            ->selectRaw('COUNT(*) as total_uses, SUM(discount_amount) as total_discount_given, COUNT(DISTINCT customer_id) as unique_customers')
            ->first();

        $recentUsages = CouponUsage::where('coupon_id', $coupon->id)
            ->with('order:id,order_number,total')
            ->latest('used_at')
            ->limit(5)
            ->get()
            ->map(fn ($u) => [
                'order_number' => $u->order?->order_number ?? '—',
                'discount_amount' => (int) $u->discount_amount,
                'used_at' => $u->used_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'coupon_id' => $coupon->id,
            'daily_usage' => $filled,
            'total_uses' => (int) ($totals->total_uses ?? 0),
            'total_discount' => (int) ($totals->total_discount_given ?? 0),
            'unique_customers' => (int) ($totals->unique_customers ?? 0),
            'recent_usages' => $recentUsages,
        ]);
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        abort_unless($this->policy->toggleActive(Auth::guard('vendor')->user(), $coupon), 403);

        $coupon->update(['is_active' => !$coupon->is_active]);

        return response()->json(['success' => true, 'is_active' => $coupon->is_active]);
    }

    public function destroy(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $actor = Auth::guard('vendor')->user();

        abort_unless($this->policy->update($actor, $coupon), 403);

        if ($coupon->times_used > 0) {
            return response()->json(['message' => __('partner.coupons.messages.coupon_used_cannot_delete')], 422);
        }

        $this->coupons->delete($coupon);

        return response()->json(['success' => true]);
    }
}
