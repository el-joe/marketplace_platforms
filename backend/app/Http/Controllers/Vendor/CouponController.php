<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\CouponIndexRequest;
use App\Http\Requests\Vendor\StoreCouponRequest;
use App\Http\Requests\Vendor\UpdateCouponRequest;
use App\Http\Resources\Vendor\VendorCouponResource;
use App\Http\Resources\Vendor\VendorCouponUsageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\VendorAdmin;
use App\Policies\VendorCouponPolicy;
use App\Services\Vendor\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $coupons,
        private readonly VendorCouponPolicy $policy,
    ) {
    }

    private function actor(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    public function index(CouponIndexRequest $request): JsonResponse
    {
        $vendorId = $this->actor()->vendor_id;

        $query = Coupon::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('scope', CouponService::VENDOR_MANAGEABLE_SCOPES)
            ->with('products:id')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest();

        $coupons = $query->paginate((int) ($request->per_page ?? 20));

        return ApiResponse::paginated($coupons, VendorCouponResource::class);
    }

    public function show(string $id): JsonResponse
    {
        $coupon = Coupon::with('products:id')->findOrFail($id);

        abort_unless($this->policy->view($this->actor(), $coupon), 403);

        return ApiResponse::success(new VendorCouponResource($coupon));
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $actor = $this->actor();

        abort_unless($this->policy->create($actor), 403);

        if (!in_array($request->input('scope'), CouponService::VENDOR_MANAGEABLE_SCOPES, true)) {
            abort(403, __('partner.coupons.messages.vendor_scope_restricted'));
        }

        try {
            $coupon = $this->coupons->create($actor->vendor, $actor, $request->validated());
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors());
        }

        return ApiResponse::success(
            new VendorCouponResource($coupon->load('products:id')),
            __('partner.coupons.create.success_created'),
            201
        );
    }

    public function update(UpdateCouponRequest $request, string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $actor = $this->actor();

        abort_unless($this->policy->update($actor, $coupon), 403);

        if (!in_array($request->input('scope'), CouponService::VENDOR_MANAGEABLE_SCOPES, true)) {
            abort(403, __('partner.coupons.messages.vendor_scope_restricted'));
        }

        try {
            $coupon = $this->coupons->update($coupon, $actor->vendor, $request->validated());
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors());
        }

        return ApiResponse::success(new VendorCouponResource($coupon->load('products:id')), __('partner.coupons.create.success_updated'));
    }

    public function toggleActive(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        abort_unless($this->policy->toggleActive($this->actor(), $coupon), 403);

        $coupon->update(['is_active' => !$coupon->is_active]);

        return ApiResponse::success(['is_active' => $coupon->is_active], __('partner.coupons.messages.coupon_status_updated'));
    }

    public function destroy(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $actor = $this->actor();

        abort_unless($this->policy->update($actor, $coupon), 403);

        if ($coupon->times_used > 0) {
            return ApiResponse::error(__('partner.coupons.messages.coupon_used_cannot_delete'), [], 422);
        }

        try {
            $this->coupons->delete($coupon);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors());
        }

        return ApiResponse::success(null, __('partner.coupons.messages.coupon_deleted'));
    }

    public function usages(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        abort_unless($this->policy->view($this->actor(), $coupon), 403);

        $usages = CouponUsage::where('coupon_id', $coupon->id)
            ->with('order:id,order_number')
            ->orderByDesc('used_at')
            ->paginate(20);

        return ApiResponse::paginated($usages, VendorCouponUsageResource::class);
    }
}
