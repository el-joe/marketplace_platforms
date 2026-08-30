<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaidAdSlotPricingModel;
use App\Http\Controllers\Controller;
use App\Models\BannerPlacementDefinition;
use App\Models\Country;
use App\Models\PaidAdSlot;
use App\Traits\HasDataTable;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdSlotController extends Controller
{
    use HasDataTable;

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $stats = [
            'total' => PaidAdSlot::count(),
            'available' => PaidAdSlot::where('is_available', true)->count(),
        ];

        $countries = Country::orderBy('name_en')->where('is_launched', true)->get(['id', 'name_en']);

        return view('admin.ad-slots.index', compact('stats', 'countries'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $query = PaidAdSlot::query()->with(['placementDefinition', 'country']);

        $query = $this->applyFilters($query, $request, [
            'is_available' => fn($q, $v) => $q->where('is_available', (int) $v),
            'country_id' => fn($q, $v) => $q->where('country_id', $v),
        ]);

        $columns = [
            ['searchable_columns' => ['paid_ad_slots.name', 'paid_ad_slots.slot_code'], 'orderable_column' => 'paid_ad_slots.name'],
            ['searchable_columns' => [], 'orderable_column' => null], // placement
            ['searchable_columns' => [], 'orderable_column' => null], // country
            ['searchable_columns' => [], 'orderable_column' => 'pricing_model'],
            ['searchable_columns' => [], 'orderable_column' => 'base_rate'],
            ['searchable_columns' => [], 'orderable_column' => null], // booking days
            ['searchable_columns' => [], 'orderable_column' => 'is_available'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        $canEdit = $admin->hasPermissionTo('ad_campaigns.edit');

        return $this->dataTableResponse($request, $query, $columns, function (PaidAdSlot $row) use ($canEdit) {
            $isAvailBadge = $row->is_available
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">' . __('admin.ad_campaigns.available_badge') . '</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">' . __('admin.ad_campaigns.unavailable_badge') . '</span>';

            $rate = '$' . number_format($row->base_rate, 2);
            $pricingModelLabel = ucwords(str_replace('_', '/', $row->pricing_model->value));

            $editUrl = route('admin.ad-slots.edit', $row->id);
            $bookingsUrl = route('admin.ad-slots.bookings', $row->id);

            $actions = '<div class="flex items-center gap-1">';
            $actions .= "<a href=\"{$bookingsUrl}\" class=\"btn btn-xs btn-secondary\">" . __('admin.ad_campaigns.bookings_link') . "</a>";
            if ($canEdit) {
                $actions .= "<a href=\"{$editUrl}\" class=\"btn btn-xs btn-ghost\">" . __('admin.edit') . "</a>";
            }
            $actions .= '</div>';

            return [
                'name' => e($row->name) . '<br><span class="text-xs text-gray-400 font-mono">' . e($row->slot_code) . '</span>',
                'placement' => e($row->placementDefinition?->name ?? '—'),
                'country' => $row->country
                    ? ($row->country->flag_emoji ? $row->country->flag_emoji . ' ' : '') . e($row->country->name_en)
                    : '<span class="text-gray-400 text-xs">' . __('admin.ad_campaigns.global_label') . '</span>',
                'pricing_model' => $pricingModelLabel,
                'base_rate' => $rate . ' <span class="text-xs text-gray-400">/ ' . strtolower($row->pricing_model->value) . '</span>',
                'booking_days' => $row->min_booking_days . ' – ' . ($row->max_booking_days ?? '∞') . ' days',
                'is_available' => $isAvailBadge,
                'actions' => $actions,
                'DT_RowData' => ['id' => $row->id],
            ];
        });
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $placements = BannerPlacementDefinition::where('is_active', true)->orderBy('sort_order')->get();
        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'flag_emoji']);

        return view('admin.ad-slots.create', compact('placements', 'countries'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slot_code' => ['required', 'string', 'max:50', 'unique:paid_ad_slots,slot_code'],
            'banner_placement_definition_id' => ['required', 'uuid', 'exists:banner_placement_definitions,id'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'pricing_model' => ['required', Rule::enum(PaidAdSlotPricingModel::class)],
            'base_rate_display' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'min_booking_days' => ['required', 'integer', 'min:1'],
            'max_booking_days' => ['nullable', 'integer', 'min:1'],
            'is_available' => ['boolean'],
            'requires_approval' => ['boolean'],
            'min_seller_tier' => ['nullable', 'string', 'max:20'],
            'notes_for_vendors' => ['nullable', 'string'],
        ]);

        PaidAdSlot::create([
            'name' => $validated['name'],
            'slot_code' => $validated['slot_code'],
            'placement_definition_id' => $validated['banner_placement_definition_id'],
            'country_id' => $validated['country_id'] ?? null,
            'pricing_model' => $validated['pricing_model'],
            'base_rate' => (int) round($validated['base_rate_display']),
            'currency' => $validated['currency'],
            'min_booking_days' => $validated['min_booking_days'],
            'max_booking_days' => $validated['max_booking_days'] ?? null,
            'is_available' => $request->boolean('is_available'),
            'requires_approval' => $request->boolean('requires_approval'),
            'min_seller_tier' => $validated['min_seller_tier'] ?? null,
            'notes_for_vendors' => $validated['notes_for_vendors'] ?? null,
            'created_by_admin_id' => $admin->id,
        ]);

        return response()->json([
            'message' => __('admin.ad_campaigns.ad_slot_created'),
            'redirect' => route('admin.ad-slots.index'),
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(PaidAdSlot $adSlot): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $placements = BannerPlacementDefinition::where('is_active', true)->orderBy('sort_order')->get();
        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'flag_emoji']);

        return view('admin.ad-slots.edit', compact('adSlot', 'placements', 'countries'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, PaidAdSlot $adSlot): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'banner_placement_definition_id' => ['required', 'uuid', 'exists:banner_placement_definitions,id'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'pricing_model' => ['required', Rule::enum(PaidAdSlotPricingModel::class)],
            'base_rate_display' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'min_booking_days' => ['required', 'integer', 'min:1'],
            'max_booking_days' => ['nullable', 'integer', 'min:1'],
            'is_available' => ['boolean'],
            'requires_approval' => ['boolean'],
            'min_seller_tier' => ['nullable', 'string', 'max:20'],
            'notes_for_vendors' => ['nullable', 'string'],
        ]);

        $adSlot->update([
            'name' => $validated['name'],
            'placement_definition_id' => $validated['banner_placement_definition_id'],
            'country_id' => $validated['country_id'] ?? null,
            'pricing_model' => $validated['pricing_model'],
            'base_rate' => (int) round($validated['base_rate_display']),
            'currency' => $validated['currency'],
            'min_booking_days' => $validated['min_booking_days'],
            'max_booking_days' => $validated['max_booking_days'] ?? null,
            'is_available' => $request->boolean('is_available'),
            'requires_approval' => $request->boolean('requires_approval'),
            'min_seller_tier' => $validated['min_seller_tier'] ?? null,
            'notes_for_vendors' => $validated['notes_for_vendors'] ?? null,
        ]);

        return response()->json([
            'message' => __('admin.ad_campaigns.ad_slot_updated'),
            'redirect' => route('admin.ad-slots.index'),
        ]);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(PaidAdSlot $adSlot): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $activeBookings = $adSlot->bookings()
            ->whereIn('status', ['pending', 'approved', 'active', 'paused'])
            ->exists();

        if ($activeBookings) {
            return response()->json([
                'message' => __('admin.ad_campaigns.ad_slot_has_bookings'),
            ], 422);
        }

        $adSlot->delete();

        return response()->json(['message' => __('admin.ad_campaigns.ad_slot_deleted')]);
    }

    // ─── Bookings for slot ────────────────────────────────────────────────────

    public function bookings(PaidAdSlot $adSlot): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $adSlot->load(['placementDefinition', 'country']);

        $bookings = $adSlot->bookings()
            ->with(['vendor', 'country', 'approvedByAdmin'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('admin.ad-slots.bookings', compact('adSlot', 'bookings'));
    }
}
