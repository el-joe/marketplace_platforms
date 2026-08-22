<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WishlistGroup;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistOverviewController extends Controller
{
    use HasDataTable;

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('wishlists.view'), 403);

        return view('admin.wishlist.index');
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    private function buildGroupsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = WishlistGroup::query()
            ->select('wishlist_groups.*')
            ->join('customers', 'customers.id', '=', 'wishlist_groups.customer_id')
            ->with('customer')
            ->withCount('items');

        return $this->applyFilters($query, $request, [
            'is_public' => fn($q, $v) => $q->where('wishlist_groups.is_public', (bool) (int) $v),
            'date_from' => fn($q, $v) => $q->whereDate('wishlist_groups.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('wishlist_groups.created_at', '<=', $v),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('wishlists.view'), 403);

        $query = $this->buildGroupsQuery($request)->latest('wishlist_groups.created_at');

        $columns = [
            ['searchable_columns' => ['customers.name', 'customers.email'], 'orderable_column' => null],  // customer
            ['searchable_columns' => ['wishlist_groups.name'], 'orderable_column' => 'name'],
            ['searchable_columns' => [], 'orderable_column' => 'is_default'],
            ['searchable_columns' => [], 'orderable_column' => 'is_public'],
            ['searchable_columns' => [], 'orderable_column' => 'items_count'],
            ['searchable_columns' => [], 'orderable_column' => 'created_at'],
            ['searchable_columns' => [], 'orderable_column' => null],  // actions
        ];

        return $this->dataTableResponse($request, $query, $columns, function (WishlistGroup $row) {
            return [
                'customer' => $row->customer
                    ? e($row->customer->name) . '<br><span class="text-xs text-gray-500">' . e($row->customer->email) . '</span>'
                    : '—',
                'name' => e($row->name),
                'is_default' => $row->is_default
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700">Default</span>'
                    : '—',
                'is_public' => $row->is_public
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">Public</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Private</span>',
                'items_count' => $row->items_count,
                'created_at' => $row->created_at->format('d M Y'),
                'actions' => '<a href="' . route('admin.wishlist.show', $row->id) . '" class="btn btn-xs btn-secondary">View Items</a>',
            ];
        });
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(string $groupId): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('wishlists.view'), 403);

        $group = WishlistGroup::with('customer')->findOrFail($groupId);

        $items = $group->items()
            ->with([
                'vendorListing.productVariant.product',
                'adminListing.productVariant.product',
                'productVariant.product',
                'productVariant.images',
            ])
            ->latest('added_at')
            ->paginate(20);

        return view('admin.wishlist.show', compact('group', 'items'));
    }
}
