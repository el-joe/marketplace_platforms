<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectProductRequestRequest;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Services\Catalog\ProductRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin review queue for vendor/marketer requests to add new catalog products.
 * Approval goes through the product create form (?product_request={id}).
 */
class ProductRequestController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly ProductRequestService $productRequestService) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', ProductRequestStatus::Pending->value);
        $requesterType = (string) $request->query('requester_type', '');
        $search = trim((string) $request->query('q', ''));

        $productRequests = ProductRequest::query()
            ->with(['vendor:id,store_name', 'marketer:id,name', 'category:id,name_en,name_ar', 'reviewer:id,name'])
            ->when(
                ProductRequestStatus::tryFrom($status),
                fn (Builder $query, ProductRequestStatus $statusEnum) => $query->where('status', $statusEnum->value),
            )
            ->when(
                in_array($requesterType, [ProductRequest::REQUESTER_VENDOR, ProductRequest::REQUESTER_MARKETER], true),
                fn (Builder $query) => $query->where('requester_type', $requesterType),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('request_number', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('name_ar', 'like', "%{$search}%")
                        ->orWhere('gtin', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('date_from'), fn (Builder $query) => $query->where('created_at', '>=', admin_day_start((string) $request->query('date_from'))))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->where('created_at', '<=', admin_day_end((string) $request->query('date_to'))))
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.product-requests.index', [
            'productRequests' => $productRequests,
            'currentStatus' => ProductRequestStatus::tryFrom($status) ? $status : 'all',
            'pendingCount' => ProductRequest::query()->pending()->count(),
            'statuses' => ProductRequestStatus::cases(),
        ]);
    }

    public function show(string $id): View
    {
        $productRequest = ProductRequest::query()
            ->with(['vendor', 'marketer', 'requestedBy', 'country', 'category', 'brand', 'reviewer', 'product'])
            ->findOrFail($id);

        $existingGtinProduct = null;
        if ($productRequest->gtin && $productRequest->isPending()) {
            $existingGtinProduct = Product::query()
                ->where('gtin', $productRequest->gtin)
                ->first(['id', 'name_en', 'name_ar', 'gtin']);
        }

        return view('admin.product-requests.show', [
            'productRequest' => $productRequest,
            'existingGtinProduct' => $existingGtinProduct,
        ]);
    }

    public function approve(string $id): RedirectResponse
    {
        $productRequest = ProductRequest::query()->findOrFail($id);

        if (! $productRequest->isPending()) {
            return redirect()
                ->route('admin.product-requests.show', $productRequest->id)
                ->with('error', __('product_requests.messages.not_pending'));
        }

        return redirect()->route('admin.products.create', ['product_request' => $productRequest->id]);
    }

    public function reject(RejectProductRequestRequest $request, string $id): RedirectResponse
    {
        $productRequest = ProductRequest::query()->findOrFail($id);

        try {
            $this->productRequestService->reject($productRequest, auth('admin')->user(), $request->validated('rejection_reason'));
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.product-requests.show', $productRequest->id)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()
            ->route('admin.product-requests.show', $productRequest->id)
            ->with('success', __('admin.product_requests.rejected_success', ['number' => $productRequest->request_number]));
    }
}
