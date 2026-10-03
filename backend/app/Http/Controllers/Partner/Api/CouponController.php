<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreCouponRequest;
use App\Http\Requests\Vendor\UpdateCouponRequest;
use App\Http\Resources\Vendor\VendorCouponResource;
use App\Http\Responses\ApiResponse;
use App\Models\Coupon;
use App\Policies\VendorCouponPolicy;
use App\Services\Vendor\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $coupons,
        private readonly VendorCouponPolicy $policy,
    ) {}

    private function actor()
    {
        return Auth::guard('vendor_api')->user();
    }

    /** GET /api/partner/v1/coupons */
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor();

        $query = Coupon::where('vendor_id', $actor->vendor_id)
            ->when($request->filled('search'), fn ($q) => $q->where('code', 'like', "%{$request->search}%"))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest();

        return ApiResponse::paginated($query->paginate((int) ($request->per_page ?? 20)), VendorCouponResource::class);
    }

    /** GET /api/partner/v1/coupons/{id} */
    public function show(string $id): JsonResponse
    {
        $coupon = Coupon::with('products:id,name_en,name_ar')->findOrFail($id);
        $actor = $this->actor();

        abort_unless($this->policy->view($actor, $coupon), 403);

        return ApiResponse::success(VendorCouponResource::make($coupon));
    }

    /** POST /api/partner/v1/coupons */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $actor = $this->actor();

        abort_unless($this->policy->create($actor), 403);

        try {
            $coupon = $this->coupons->create($actor->vendor, $actor, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully.',
            'data' => VendorCouponResource::make($coupon),
        ], 201);
    }

    /** PUT /api/partner/v1/coupons/{id} */
    public function update(UpdateCouponRequest $request, string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $actor = $this->actor();

        abort_unless($this->policy->update($actor, $coupon), 403);

        try {
            $this->coupons->update($coupon, $actor->vendor, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Coupon updated successfully.']);
    }

    /** POST /api/partner/v1/coupons/{id}/toggle-status */
    public function toggleStatus(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        abort_unless($this->policy->toggleActive($this->actor(), $coupon), 403);

        $coupon->update(['is_active' => ! $coupon->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $coupon->is_active,
            'message' => $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.',
        ]);
    }

    /** DELETE /api/partner/v1/coupons/{id} */
    public function destroy(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $actor = $this->actor();

        abort_unless($this->policy->update($actor, $coupon), 403);

        if ($coupon->times_used > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete a coupon that has already been used.'], 422);
        }

        $this->coupons->delete($coupon);

        return response()->json(['success' => true, 'message' => 'Coupon deleted successfully.']);
    }
}
