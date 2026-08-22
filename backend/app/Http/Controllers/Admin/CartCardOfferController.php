<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCartCardOfferRequest;
use App\Http\Requests\Admin\UpdateCartCardOfferRequest;
use App\Models\CartCardOffer;
use App\Models\Country;
use App\Services\Admin\CartCardOfferService;
use App\Traits\HasDataTable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CartCardOfferController extends Controller
{
    use HasDataTable, AuthorizesRequests;

    public function __construct(private readonly CartCardOfferService $offers)
    {
    }

    public function index(): View
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('viewAny', CartCardOffer::class);

        return view('admin.cart-card-offers.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.cart_card_offers')],
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('viewAny', CartCardOffer::class);

        $columns = $this->columnDefinitions();

        $query = CartCardOffer::query()
            ->join('countries', 'countries.id', '=', 'cart_card_offers.country_id')
            ->select([
                'cart_card_offers.id',
                'cart_card_offers.card_name_en',
                'countries.name_en as country_name',
                'cart_card_offers.cashback_type',
                'cart_card_offers.cashback_pct',
                'cart_card_offers.cashback_fixed_amount',
                'cart_card_offers.is_active',
                'cart_card_offers.valid_from',
                'cart_card_offers.valid_until',
                'cart_card_offers.sort_order',
            ]);

        $query = $this->applyFilters($query, $request, [
            'country_id' => fn ($q, $v) => $q->where('cart_card_offers.country_id', $v),
            'is_active' => fn ($q, $v) => $q->where('cart_card_offers.is_active', (bool) $v),
            'cashback_type' => fn ($q, $v) => $q->where('cart_card_offers.cashback_type', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'card_name_en' => e($row->card_name_en),
                'country_name' => e($row->country_name),
                'cashback_type' => $row->cashback_type,
                'cashback_pct' => $row->cashback_pct,
                'cashback_fixed_amount' => (int) $row->cashback_fixed_amount,
                'is_active' => (bool) $row->is_active,
                'valid_from' => $row->valid_from,
                'valid_until' => $row->valid_until,
                'sort_order' => (int) $row->sort_order,
                'edit_url' => route('admin.cart-card-offers.edit', $row->id),
                'delete_url' => route('admin.cart-card-offers.destroy', $row->id),
            ];
        });
    }

    public function create(): View
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('create', CartCardOffer::class);

        return view('admin.cart-card-offers.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.cart_card_offers'), 'url' => route('admin.cart-card-offers.index')],
                ['label' => __('admin.cart_card_offers_section.new_offer')],
            ],
            'countries' => Country::query()->orderBy('name_en')->get(['id', 'name_en']),
        ]);
    }

    public function store(StoreCartCardOfferRequest $request): JsonResponse|RedirectResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('create', CartCardOffer::class);

        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        try {
            $offer = $this->offers->create($request->validated(), $admin, $request->file('card_image'));
        } catch (\Throwable $e) {
            Log::error('CartCardOfferController@store failed', ['error' => $e->getMessage()]);

            if ($request->wantsJson()) {
                return response()->json(['message' => __('admin.cart_card_offers_section.create_failed')], 500);
            }
            return back()->withInput()->withErrors(['error' => __('admin.cart_card_offers_section.create_failed')]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.cart-card-offers.edit', $offer->id),
            ]);
        }

        return redirect()->route('admin.cart-card-offers.edit', $offer->id)
            ->with('success', __('admin.cart_card_offers_section.offer_created'));
    }

    public function edit(string $cartCardOffer): View
    {
        $model = CartCardOffer::findOrFail($cartCardOffer);

        Gate::forUser(Auth::guard('admin')->user())->authorize('update', $model);

        return view('admin.cart-card-offers.edit', [
            'offer' => $model,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.cart_card_offers'), 'url' => route('admin.cart-card-offers.index')],
                ['label' => e($model->card_name_en)],
            ],
            'countries' => Country::query()->orderBy('name_en')->get(['id', 'name_en']),
        ]);
    }

    public function update(UpdateCartCardOfferRequest $request, string $cartCardOffer): JsonResponse
    {
        $model = CartCardOffer::findOrFail($cartCardOffer);

        Gate::forUser(Auth::guard('admin')->user())->authorize('update', $model);

        try {
            $this->offers->update($model, $request->validated(), $request->file('card_image'));
        } catch (\Throwable $e) {
            Log::error('CartCardOfferController@update failed', ['cart_card_offer' => $cartCardOffer, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('admin.cart_card_offers_section.update_failed')], 500);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(string $cartCardOffer): JsonResponse
    {
        $model = CartCardOffer::findOrFail($cartCardOffer);

        Gate::forUser(Auth::guard('admin')->user())->authorize('delete', $model);

        $this->offers->delete($model);

        return response()->json(['success' => true]);
    }

    private function columnDefinitions(): array
    {
        return [
            ['title' => 'Card', 'data' => 'card_name_en', 'name' => 'card_name_en', 'orderable_column' => 'cart_card_offers.card_name_en', 'searchable_columns' => ['cart_card_offers.card_name_en']],
            ['title' => 'Country', 'data' => 'country_name', 'name' => 'country_name', 'orderable_column' => 'countries.name_en', 'searchable' => false],
            ['title' => 'Type', 'data' => 'cashback_type', 'name' => 'cashback_type', 'searchable' => false],
            ['title' => 'Cashback', 'data' => 'cashback_pct', 'name' => 'cashback_pct', 'searchable' => false],
            ['title' => 'Active', 'data' => 'is_active', 'name' => 'is_active', 'searchable' => false],
            ['title' => 'Valid From', 'data' => 'valid_from', 'name' => 'valid_from', 'orderable_column' => 'cart_card_offers.valid_from', 'searchable' => false],
            ['title' => 'Valid Until', 'data' => 'valid_until', 'name' => 'valid_until', 'orderable_column' => 'cart_card_offers.valid_until', 'searchable' => false],
            ['title' => 'Sort', 'data' => 'sort_order', 'name' => 'sort_order', 'orderable_column' => 'cart_card_offers.sort_order', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false],
        ];
    }
}
