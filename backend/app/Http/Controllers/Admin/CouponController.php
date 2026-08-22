<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Http\Resources\Admin\CouponResource;
use App\Http\Resources\Admin\CouponUsageResource;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Country;
use App\Models\Customer;
use App\Services\Admin\CouponService;
use App\Traits\HasDataTable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CouponController extends Controller
{
    use HasDataTable, AuthorizesRequests;

    public function __construct(private readonly CouponService $coupons)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('viewAny', Coupon::class);

        return view('admin.coupons.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.coupons')],
            ],
            'stats' => [
                'total' => Coupon::count(),
                'active' => Coupon::active()->count(),
                'expired' => Coupon::where('valid_until', '<', now())->count(),
                'used_today' => CouponUsage::whereDate('used_at', today())->count(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('viewAny', Coupon::class);

        $columns = $this->columnDefinitions();

        $query = Coupon::query()
            ->select([
                'coupons.id',
                'coupons.code',
                'coupons.name',
                'coupons.type',
                'coupons.value',
                'coupons.scope',
                'coupons.vendor_id',
                'coupons.times_used',
                'coupons.usage_limit_total',
                'coupons.customer_eligibility',
                'coupons.valid_from',
                'coupons.valid_until',
                'coupons.is_active',
                'coupons.is_stackable',
                'coupons.created_at',
            ]);

        $query = $this->applyFilters($query, $request, [
            'is_active' => fn($q, $v) => $q->where('coupons.is_active', (bool) $v),
            'type' => fn($q, $v) => $q->where('coupons.type', $v),
            'scope' => fn($q, $v) => $q->where('coupons.scope', $v),
            'expired' => function ($q, $v) {
                if ($v === '1') {
                    $q->where('coupons.valid_until', '<', now());
                } elseif ($v === '0') {
                    $q->where('coupons.valid_until', '>=', now());
                }
            },
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $isAdminManaged = in_array($row->scope->value, CouponService::ADMIN_MANAGEABLE_SCOPES, true);

            return [
                'id' => $row->id,
                'code' => e($row->code),
                'name' => e($row->name),
                'type' => $row->type?->value,
                'value' => $row->value,
                'scope' => $row->scope?->value,
                'is_admin_managed' => $isAdminManaged,
                'times_used' => (int) $row->times_used,
                'usage_limit_total' => $row->usage_limit_total ? (int) $row->usage_limit_total : null,
                'customer_eligibility' => $row->customer_eligibility?->value,
                'valid_from' => $row->valid_from,
                'valid_until' => $row->valid_until,
                'is_active' => (bool) $row->is_active,
                'is_stackable' => (bool) $row->is_stackable,
                'created_at' => $row->created_at,
                'is_expired' => $row->valid_until < now()->toDateTimeString(),
                'show_url' => route('admin.coupons.show', $row->id),
                'edit_url' => $isAdminManaged ? route('admin.coupons.edit', $row->id) : null,
                'delete_url' => $isAdminManaged ? route('admin.coupons.destroy', $row->id) : null,
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('create', Coupon::class);

        return view('admin.coupons.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.coupons'), 'url' => route('admin.coupons.index')],
                ['label' => __('admin.coupons_section.new_coupon')],
            ],
            'categories' => Category::query()->where('is_active', true)->whereNull('deleted_at')->orderBy('name_en')->get(['id', 'name_en']),
            'countries' => Country::query()->whereNull('deleted_at')->orderBy('name_en')->get(['id', 'name_en']),
            'selectedCustomers' => collect(),
        ]);
    }

    public function store(StoreCouponRequest $request): JsonResponse|RedirectResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('create', Coupon::class);

        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        try {
            $coupon = $this->coupons->create($request->validated(), $admin);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            return back()->withInput()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('CouponController@store failed', ['error' => $e->getMessage()]);

            if ($request->wantsJson()) {
                return response()->json(['message' => __('admin.coupons_section.create_failed')], 500);
            }
            return back()->withInput()->withErrors(['error' => __('admin.coupons_section.create_failed')]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.coupons.edit', $coupon->id),
            ]);
        }

        return redirect()->route('admin.coupons.edit', $coupon->id)
            ->with('success', __('admin.coupons_section.coupon_created'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show (detail + usage analytics)
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Coupon $coupon): View
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('view', $coupon);

        $coupon->load(['vendor:id,store_name', 'category:id,name_en']);

        $totalDiscountGranted = CouponUsage::query()->where('coupon_id', $coupon->id)->sum('discount_amount');

        $dailyRedemptions = CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->where('used_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(used_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $recentUsages = CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->with(['customer:id,name', 'order:id,order_number'])
            ->orderByDesc('used_at')
            ->limit(25)
            ->get();

        $isAdminManaged = in_array($coupon->scope->value, CouponService::ADMIN_MANAGEABLE_SCOPES, true);

        return view('admin.coupons.show', [
            'coupon' => CouponResource::make($coupon)->resolve(),
            'couponModel' => $coupon,
            'isAdminManaged' => $isAdminManaged,
            'usageCount' => CouponUsage::query()->where('coupon_id', $coupon->id)->count(),
            'categories' => $isAdminManaged
                ? Category::query()->where('is_active', true)->whereNull('deleted_at')->orderBy('name_en')->get(['id', 'name_en'])
                : collect(),
            'countries' => $isAdminManaged
                ? Country::query()->whereNull('deleted_at')->orderBy('name_en')->get(['id', 'name_en'])
                : collect(),
            'selectedCustomers' => $coupon->eligible_customer_ids
                ? Customer::query()->whereIn('id', $coupon->eligible_customer_ids)->get(['id', 'name', 'email'])
                : collect(),
            'totalDiscountGranted' => (float) $totalDiscountGranted,
            'dailyRedemptions' => $dailyRedemptions,
            'recentUsages' => CouponUsageResource::collection($recentUsages)->resolve(),
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.coupons'), 'url' => route('admin.coupons.index')],
                ['label' => e($coupon->code)],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $coupon): View
    {
        $model = Coupon::findOrFail($coupon);

        Gate::forUser(Auth::guard('admin')->user())->authorize('update', $model);

        $usageCount = CouponUsage::query()->where('coupon_id', $model->id)->count();

        return view('admin.coupons.edit', [
            'coupon' => $model,
            'usageCount' => $usageCount,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.coupons'), 'url' => route('admin.coupons.index')],
                ['label' => e($model->code)],
            ],
            'categories' => Category::query()->where('is_active', true)->whereNull('deleted_at')->orderBy('name_en')->get(['id', 'name_en']),
            'countries' => Country::query()->whereNull('deleted_at')->orderBy('name_en')->get(['id', 'name_en']),
            'selectedCustomers' => $model->eligible_customer_ids
                ? Customer::query()->whereIn('id', $model->eligible_customer_ids)->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    public function update(UpdateCouponRequest $request, string $coupon): JsonResponse
    {
        $model = Coupon::findOrFail($coupon);

        Gate::forUser(Auth::guard('admin')->user())->authorize('update', $model);

        try {
            $this->coupons->update($model, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('CouponController@update failed', ['coupon' => $coupon, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('admin.coupons_section.update_failed')], 500);
        }

        return response()->json(['success' => true]);
    }

    public function toggleActive(Coupon $coupon): JsonResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('toggleActive', $coupon);

        $coupon->update(['is_active' => !$coupon->is_active]);

        return response()->json(['success' => true, 'is_active' => $coupon->is_active]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(string $coupon): JsonResponse
    {
        $model = Coupon::findOrFail($coupon);

        Gate::forUser(Auth::guard('admin')->user())->authorize('delete', $model);

        $result = $this->coupons->deleteOrDeactivate($model);

        if ($result['deactivated']) {
            return response()->json([
                'success' => true,
                'deactivated' => true,
                'message' => __('admin.coupons_section.delete_blocked_used', ['count' => $model->times_used]),
            ]);
        }

        return response()->json(['success' => true, 'deactivated' => false]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Clear Cache
    // ─────────────────────────────────────────────────────────────────────────

    public function clearCache(): JsonResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('viewAny', Coupon::class);

        $this->coupons->bustProductCouponsCache();

        return response()->json(['success' => true, 'message' => __('admin.coupons_section.cache_cleared')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk Actions
    // ─────────────────────────────────────────────────────────────────────────

    public function bulkAction(Request $request): JsonResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('viewAny', Coupon::class);

        $action = $request->input('action');
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return response()->json(['message' => __('admin.coupons_section.no_coupons_selected')], 422);
        }

        $allowed = ['activate', 'deactivate', 'delete'];
        if (!in_array($action, $allowed, true)) {
            return response()->json(['message' => __('admin.coupons_section.invalid_action')], 422);
        }

        // Bulk actions never touch vendor/product-scoped coupons — those are
        // read-only from the admin panel.
        $manageableIds = Coupon::query()
            ->whereIn('id', $ids)
            ->whereIn('scope', CouponService::ADMIN_MANAGEABLE_SCOPES)
            ->pluck('id');

        match ($action) {
            'activate' => Coupon::query()->whereIn('id', $manageableIds)->update(['is_active' => true]),
            'deactivate' => Coupon::query()->whereIn('id', $manageableIds)->update(['is_active' => false]),
            'delete' => Coupon::query()
                ->whereIn('id', $manageableIds)
                ->whereNotIn('id', CouponUsage::query()->pluck('coupon_id'))
                ->delete(),
        };

        $this->coupons->bustProductCouponsCache();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generate Code
    // ─────────────────────────────────────────────────────────────────────────

    public function generateCode(): JsonResponse
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Coupon::where('code', $code)->exists());

        return response()->json(['code' => $code]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Search (Select2 AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function searchCustomers(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        $customers = Customer::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'results' => $customers->map(fn($c) => ['id' => $c->id, 'text' => "{$c->name} ({$c->email})"]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Usages
    // ─────────────────────────────────────────────────────────────────────────

    public function usages(Coupon $coupon): JsonResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('view', $coupon);

        $usages = CouponUsage::where('coupon_id', $coupon->id)
            ->with(['customer:id,name,email', 'order:id,order_number,status'])
            ->orderByDesc('used_at')
            ->paginate(20);

        return response()->json([
            'data' => $usages->items(),
            'total' => $usages->total(),
            'current_page' => $usages->currentPage(),
            'last_page' => $usages->lastPage(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Usage Chart
    // ─────────────────────────────────────────────────────────────────────────

    public function usageChart(Coupon $coupon): JsonResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('view', $coupon);

        $counts = CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->where('used_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(used_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $labels = [];
        $data = [];

        for ($day = now()->subDays(29)->startOfDay(); $day <= now()->endOfDay(); $day->addDay()) {
            $date = $day->toDateString();
            $labels[] = $date;
            $data[] = (int) ($counts[$date] ?? 0);
        }

        return response()->json(['labels' => $labels, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────────

    private function columnDefinitions(): array
    {
        return [
            ['title' => 'Code', 'data' => 'code', 'name' => 'code', 'orderable_column' => 'coupons.code', 'searchable_columns' => ['coupons.code', 'coupons.name']],
            ['title' => 'Name', 'data' => 'name', 'name' => 'name', 'orderable_column' => 'coupons.name', 'searchable' => false],
            ['title' => 'Type', 'data' => 'type', 'name' => 'type', 'orderable_column' => 'coupons.type', 'searchable' => false],
            ['title' => 'Value', 'data' => 'value', 'name' => 'value', 'orderable_column' => 'coupons.value', 'searchable' => false],
            ['title' => 'Scope', 'data' => 'scope', 'name' => 'scope', 'orderable_column' => 'coupons.scope', 'searchable' => false],
            ['title' => 'Used', 'data' => 'times_used', 'name' => 'times_used', 'orderable_column' => 'coupons.times_used', 'searchable' => false],
            ['title' => 'Active', 'data' => 'is_active', 'name' => 'is_active', 'orderable_column' => 'coupons.is_active', 'searchable' => false],
            ['title' => 'Valid Until', 'data' => 'valid_until', 'name' => 'valid_until', 'orderable_column' => 'coupons.valid_until', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false],
        ];
    }
}
