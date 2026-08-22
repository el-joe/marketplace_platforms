<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\AdminListingStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\WishlistItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\AdminListing;
use App\Models\Country;
use App\Models\VendorListing;
use App\Models\WishlistGroup;
use App\Models\WishlistItem;
use App\Services\ListingModeResolver;
use App\Services\WishlistService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlistService)
    {
    }

    public function indexGroups(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $groups = WishlistGroup::where('customer_id', $customer->id)
            ->withCount('items')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (WishlistGroup $g) => $this->groupShape($g));

        return ApiResponse::success($groups);
    }

    public function createGroup(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $count = WishlistGroup::where('customer_id', $customer->id)->count();
        if ($count >= 20) {
            return ApiResponse::error(__('customer_api.wishlist.max_groups_reached'), [], 422);
        }

        $maxSort = WishlistGroup::where('customer_id', $customer->id)->max('sort_order') ?? 0;

        $group = WishlistGroup::create([
            'customer_id' => $customer->id,
            'name' => trim($data['name']),
            'is_default' => false,
            'is_public' => $data['is_public'] ?? false,
            'sort_order' => $maxSort + 1,
        ]);

        return ApiResponse::success($this->groupShape($group->loadCount('items')), __('customer_api.wishlist.group_created'), 201);
    }

    public function updateGroup(Request $request, $countryId, string $groupId): JsonResponse
    {
        $customer = auth('customer')->user();

        try {
            $group = $this->wishlistService->resolveGroup($groupId, $customer);
        } catch (ModelNotFoundException) {
            return ApiResponse::error(__('customer_api.wishlist.group_not_found'), [], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            if ($data['name'] === '') {
                return ApiResponse::error(__('customer_api.wishlist.name_empty'), [], 422);
            }
        }

        $group->update($data);

        return ApiResponse::success($this->groupShape($group->loadCount('items')), __('customer_api.wishlist.group_updated'));
    }

    public function deleteGroup(Request $request, $countryId, string $groupId): JsonResponse
    {
        $customer = auth('customer')->user();

        try {
            $group = $this->wishlistService->resolveGroup($groupId, $customer);
        } catch (ModelNotFoundException) {
            return ApiResponse::error(__('customer_api.wishlist.group_not_found'), [], 404);
        }

        if ($group->is_default) {
            return ApiResponse::error(__('customer_api.wishlist.cannot_delete_default'), [], 422);
        }

        $this->wishlistService->deleteGroupWithMigration($group, $customer);

        return ApiResponse::success(null, __('customer_api.wishlist.group_deleted'));
    }

    public function showGroup(Request $request, $countryId , string $groupId): JsonResponse
    {
        $customer = auth('customer')->user();

        try {
            $group = $this->wishlistService->resolveGroup($groupId, $customer);
        } catch (ModelNotFoundException) {
            return ApiResponse::error(__('customer_api.wishlist.group_not_found'), [], 404);
        }

        $country = $this->resolveCountry($request, $customer);
        if (!$country) {
            return ApiResponse::error(__('customer_api.wishlist.country_not_found'), [], 404);
        }

        $paginator = WishlistItem::where('wishlist_group_id', $group->id)
            ->with([
                'vendorListing.productVariant.product.category',
                'vendorListing.productVariant.product.images',
                'adminListing.productVariant.product.category',
                'adminListing.productVariant.product.images',
            ])
            ->orderByDesc('added_at')
            ->paginate(15);

        $items = $paginator->getCollection()
            ->map(fn (WishlistItem $item) => (new WishlistItemResource($item, $country))->toArray($request))
            ->values()
            ->all();

        return ApiResponse::success([
            'group' => $this->groupShape($group->loadCount('items')),
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $data = $request->validate([
            'listing_id' => ['required', 'uuid'],
            'product_variant_id' => ['required', 'uuid'],
            'group_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $isAdminListing = ListingModeResolver::isNawyNow($request);

        if ($isAdminListing) {
            $listing = AdminListing::where('id', $data['listing_id'])
                ->where('status', AdminListingStatus::Active->value)
                ->first();
        } else {
            $listing = VendorListing::where('id', $data['listing_id'])
                ->where('status', VendorListingStatus::Active->value)
                ->first();
        }

        if (!$listing) {
            return ApiResponse::error(__('customer_api.wishlist.listing_not_found'), [], 404);
        }

        if (isset($data['group_id'])) {
            try {
                $this->wishlistService->resolveGroup($data['group_id'], $customer);
            } catch (ModelNotFoundException) {
                return ApiResponse::error(__('customer_api.wishlist.group_not_found'), [], 404);
            }
        }

        $result = $this->wishlistService->addItem(
            customer: $customer,
            listingId: $data['listing_id'],
            isAdminListing: $isAdminListing,
            productVariantId: $data['product_variant_id'],
            groupId: $data['group_id'] ?? null,
        );

        $group = $result['group']->loadCount('items');
        $status = $result['already_existed'] ? 200 : 201;

        return ApiResponse::success([
            'item' => [
                'id' => $result['item']->id,
                'added_at' => $result['item']->added_at,
                'listing_type' => $isAdminListing ? 'admin_listing' : 'vendor_listing',
            ],
            'group' => $this->groupShape($group),
        ], $result['already_existed'] ? __('customer_api.wishlist.already_in_wishlist') : __('customer_api.wishlist.added_to_wishlist'), $status);
    }

    public function removeItem(Request $request, $countryId, string $itemId): JsonResponse
    {
        $customer = auth('customer')->user();

        $item = WishlistItem::where('id', $itemId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$item) {
            return ApiResponse::error(__('customer_api.wishlist.item_not_found'), [], 404);
        }

        $item->delete();

        return response()->json(null, 204);
    }

    public function moveItems(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1', 'max:50'],
            'item_ids.*' => ['uuid'],
            'target_group_id' => ['required', 'uuid'],
        ]);

        try {
            $moved = $this->wishlistService->moveItems(
                customer: $customer,
                itemIds: $data['item_ids'],
                targetGroupId: $data['target_group_id'],
            );
        } catch (ModelNotFoundException) {
            return ApiResponse::error(__('customer_api.wishlist.target_group_not_found'), [], 404);
        }

        $targetGroup = $this->wishlistService->resolveGroup($data['target_group_id'], $customer)
            ->loadCount('items');

        return ApiResponse::success([
            'moved_count' => $moved,
            'target_group' => $this->groupShape($targetGroup),
        ], __('customer_api.wishlist.items_moved', ['count' => $moved]));
    }

    public function checkListing(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $data = $request->validate([
            'listing_id' => ['required', 'uuid'],
        ]);

        $isAdminListing = ListingModeResolver::isNawyNow($request);

        $groupIds = $this->wishlistService->listingInGroups(
            customer: $customer,
            listingId: $data['listing_id'],
            isAdminListing: $isAdminListing,
        );

        $groups = [];
        if (!empty($groupIds)) {
            $groups = WishlistGroup::whereIn('id', $groupIds)
                ->where('customer_id', $customer->id)
                ->get(['id', 'name', 'is_default'])
                ->toArray();
        }

        return ApiResponse::success([
            'in_wishlist' => !empty($groupIds),
            'groups' => $groups,
        ]);
    }

    private function groupShape(WishlistGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'is_default' => $group->is_default,
            'is_public' => $group->is_public,
            'sort_order' => $group->sort_order,
            'items_count' => $group->items_count ?? 0,
            'created_at' => $group->created_at,
        ];
    }

    private function resolveCountry(Request $request, $customer): ?Country
    {
        $country = $request->attributes->get('country');

        if ($country instanceof Country) {
            return $country;
        }

        return $customer?->country;
    }
}
