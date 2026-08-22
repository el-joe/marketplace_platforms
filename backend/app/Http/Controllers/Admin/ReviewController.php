<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Enums\ReviewVendorReplyStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Review;
use App\Models\ReviewVendorReply;
use App\Services\ReviewModerationService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(private readonly ReviewModerationService $moderationService)
    {
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.view'), 403);

        if ($request->filled('export')) {
            return $this->exportReviews($request);
        }

        $stats = [
            'pending' => Review::where('status', ReviewStatus::Pending)->count(),
            'auto_flagged' => Review::where('status', ReviewStatus::AutoFlagged)->count(),
            'published_today' => Review::where('status', ReviewStatus::Published)
                ->whereDate('updated_at', today())
                ->count(),
            'avg_rating' => (float) Review::where('status', ReviewStatus::Published)->avg('rating'),
        ];

        $aiFlaggedCount = $stats['auto_flagged']
            + Review::where('status', ReviewStatus::Flagged)->count();

        $countries = Country::orderBy('name_en')
            ->where('is_launched', true)
            ->get(['id', 'name_en', 'flag_emoji']);

        return view('admin.reviews.index', compact('stats', 'aiFlaggedCount', 'countries'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    /**
     * Shared base query for the datatable and export, with request-driven filters applied.
     * Search matches the joined product's name (no dedicated search column on `reviews`).
     */
    private function buildReviewsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Review::query()
            ->with(['product.primaryImage', 'customer', 'country', 'vendorListing', 'adminListing'])
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->leftJoin('customers', 'customers.id', '=', 'reviews.customer_id')
            ->select('reviews.*');

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('reviews.status', $v),
            'country_id' => fn($q, $v) => $q->where('reviews.country_id', $v),
            'verified_only' => fn($q, $v) => $v ? $q->where('reviews.is_verified_purchase', 1) : $q,
            'ai_flagged' => fn($q, $v) => $v ? $q->whereIn('reviews.status', [ReviewStatus::Flagged, ReviewStatus::AutoFlagged])->orWhereNotNull('reviews.ai_flag_reason') : $q,
            'listing_type' => fn($q, $v) => match ($v) {
                'admin' => $q->whereNotNull('reviews.admin_listing_id'),
                'vendor' => $q->whereNotNull('reviews.vendor_listing_id'),
                default => $q,
            },
            'date_from' => fn($q, $v) => $q->whereDate('reviews.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('reviews.created_at', '<=', $v),
            // Scalar single-rating filter (1-5). The pre-existing multi-select checkbox filter
            // below sends `rating` as an array and is handled separately since applyFilters()
            // ignores array values.
            'rating' => fn($q, $v) => $q->where('reviews.rating', (int) $v),
            'search' => fn($q, $v) => $q->where('products.name_en', 'like', "%{$v}%"),
        ]);

        // Multi-select rating filter
        if ($request->filled('rating') && is_array($request->input('rating'))) {
            $query->whereIn('reviews.rating', array_map('intval', $request->input('rating')));
        }

        return $query;
    }

    private function exportReviews(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $reviews = $this->buildReviewsQuery($request)
            ->with('customer')
            ->orderByDesc('reviews.created_at')
            ->get();

        $headers = ['Product', 'Customer', 'Rating', 'Status', 'Date'];

        $rows = $reviews->map(fn(Review $review) => [
            $review->product?->name_en,
            $review->customer?->name ?? 'Guest',
            (int) $review->rating,
            $review->status?->value,
            optional($review->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('reviews', $headers, $rows),
            'csv' => $this->exportCsv('reviews', $headers, $rows),
            'word' => $this->exportWord('reviews', 'Reviews', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.view'), 403);

        $query = $this->buildReviewsQuery($request);

        $columns = [
            ['searchable_columns' => [], 'orderable_column' => null],                      // 0: checkbox
            ['searchable_columns' => ['products.name_en'], 'orderable_column' => 'products.name_en'], // 1: product
            ['searchable_columns' => ['customers.name'], 'orderable_column' => 'customers.name'],     // 2: customer
            ['searchable_columns' => [], 'orderable_column' => 'reviews.rating'],           // 3: rating
            ['searchable_columns' => [], 'orderable_column' => 'reviews.status'],           // 4: status
            ['searchable_columns' => [], 'orderable_column' => null],                      // 5: ai_flag
            ['searchable_columns' => [], 'orderable_column' => null],                      // 6: verified
            ['searchable_columns' => [], 'orderable_column' => 'reviews.helpful_count'],    // 7: helpful
            ['searchable_columns' => [], 'orderable_column' => 'reviews.not_helpful_count'],// 8: not_helpful
            ['searchable_columns' => [], 'orderable_column' => 'reviews.created_at'],       // 9: created_at
            ['searchable_columns' => [], 'orderable_column' => null],                      // 10: actions
        ];

        $canModerate = $admin->hasPermissionTo('reviews.edit');
        $canDelete = $admin->hasPermissionTo('reviews.delete');

        return $this->dataTableResponse($request, $query, $columns, function (Review $review) use ($canModerate, $canDelete) {
            // Product cell
            $product = $review->product;
            $productName = $product ? e($product->name_en ?? 'Unknown') : '—';
            $productUrl = $product ? route('admin.products.show', $product->id) : '#';
            $imgUrl = $product?->primaryImage->first()?->url ?? null;
            $imgTag = $imgUrl
                ? "<img src=\"{$imgUrl}\" class=\"w-8 h-8 object-cover rounded flex-shrink-0\" alt=\"\">"
                : "<span class=\"w-8 h-8 bg-gray-100 rounded flex items-center justify-center text-gray-300 flex-shrink-0\">?</span>";
            $productCell = "<div class=\"flex items-center gap-2\">{$imgTag}<a href=\"{$productUrl}\" class=\"text-primary-600 hover:underline text-sm font-medium\" target=\"_blank\">{$productName}</a></div>";

            // Customer cell (masked name)
            $rawName = $review->customer?->name ?? 'Guest';
            $customerCell = e($this->maskName($rawName));

            // Rating stars
            $rating = (int) $review->rating;
            $stars = '';
            for ($i = 1; $i <= 5; $i++) {
                $color = $i <= $rating ? 'text-yellow-400' : 'text-gray-200';
                $stars .= "<span class=\"{$color}\">★</span>";
            }
            $ratingCell = "<span class=\"text-base font-mono\">{$stars}</span> <span class=\"text-xs text-gray-500 ml-1\">{$rating}</span>";

            // Status badge
            $statusColors = [
                'pending' => 'warning',
                'published' => 'success',
                'rejected' => 'danger',
                'flagged' => 'orange',
                'auto_flagged' => 'orange',
            ];
            $sc = $statusColors[$review->status->value] ?? 'gray';
            $label = e($review->status->label());
            $statusBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$sc}-100 text-{$sc}-700\">{$label}</span>";

            // AI flag
            $aiFlag = $review->ai_flag_reason
                ? "<span class=\"inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800\" title=\"" . e($review->ai_flag_reason) . "\">⚠ AI</span>"
                : '—';

            // Verified purchase
            $verified = $review->is_verified_purchase
                ? "<span class=\"inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700\">✓ Verified</span>"
                : "<span class=\"text-gray-400 text-xs\">—</span>";

            // Actions
            $showUrl = route('admin.reviews.show', $review->id);
            $approveUrl = route('admin.reviews.approve', $review->id);
            $rejectUrl = route('admin.reviews.reject', $review->id);
            $deleteUrl = route('admin.reviews.delete', $review->id);

            $actions = "<div class=\"flex items-center gap-1\">"
                . "<a href=\"{$showUrl}\" class=\"btn btn-xs btn-secondary\">View</a>";
            if ($canModerate && $review->status !== ReviewStatus::Published) {
                $actions .= "<button type=\"button\" class=\"btn btn-xs btn-success js-approve-btn\" data-url=\"{$approveUrl}\" data-id=\"{$review->id}\">Approve</button>";
            }
            if ($canModerate && $review->status !== ReviewStatus::Rejected) {
                $actions .= "<button type=\"button\" class=\"btn btn-xs btn-danger js-reject-btn\" data-url=\"{$rejectUrl}\" data-id=\"{$review->id}\">Reject</button>";
            }
            if ($canDelete) {
                $actions .= "<button type=\"button\" class=\"btn btn-xs btn-ghost js-delete-btn\" data-url=\"{$deleteUrl}\" data-id=\"{$review->id}\" title=\"Delete\">🗑</button>";
            }
            $actions .= "</div>";

            return [
                'checkbox' => "<input type=\"checkbox\" class=\"row-checkbox form-checkbox\" value=\"{$review->id}\">",
                'product' => $productCell,
                'customer' => $customerCell,
                'rating' => $ratingCell,
                'status' => $statusBadge,
                'ai_flag' => $aiFlag,
                'verified' => $verified,
                'helpful' => (int) $review->helpful_count,
                'not_helpful' => (int) $review->not_helpful_count,
                'created_at' => $review->created_at->format('d M Y'),
                'actions' => $actions,
                'DT_RowId' => 'row_' . $review->id,
                'DT_RowData' => ['id' => $review->id, 'status' => $review->status->value],
            ];
        });
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Review $review): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.view'), 403);

        $review->load([
            'product.primaryImage',
            'customer',
            'country',
            'orderItem',
            'vendorListing',
            'adminListing',
            'vendorReply.vendor',
            'moderatedByAdmin',
            'files',
        ]);

        return view('admin.reviews.show', compact('review'));
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function approve(Review $review): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.edit'), 403);

        if ($review->status === ReviewStatus::Published) {
            return response()->json(['message' => 'Review is already published.'], 422);
        }

        $this->moderationService->approve($review, $admin);

        return response()->json(['message' => 'Review approved and published.', 'status' => 'published']);
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function reject(Request $request, Review $review): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.edit'), 403);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($review->status === ReviewStatus::Rejected) {
            return response()->json(['message' => 'Review is already rejected.'], 422);
        }

        // Store optional reason in ai_flag_reason as a rejection note (repurposed for internal notes)
        if ($request->filled('reason')) {
            $review->update(['ai_flag_reason' => '[Rejection note] ' . $request->input('reason')]);
        }

        $this->moderationService->reject($review, $admin);

        return response()->json(['message' => 'Review rejected.', 'status' => 'rejected']);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function delete(Review $review): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.delete'), 403);

        $review->forceDelete();

        return response()->json(['message' => 'Review permanently deleted.']);
    }

    // ─── Bulk Action ──────────────────────────────────────────────────────────

    public function bulkAction(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.edit'), 403);

        $request->validate([
            'action' => ['required', 'string', 'in:approve,reject,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['string', 'uuid'],
        ]);

        $action = $request->input('action');
        $ids = $request->input('ids');
        $reviews = Review::whereIn('id', $ids)->get();

        $count = 0;
        foreach ($reviews as $review) {
            match ($action) {
                'approve' => $this->moderationService->approve($review, $admin),
                'reject' => $this->moderationService->reject($review, $admin),
                'delete' => $review->forceDelete(),
            };
            $count++;
        }

        return response()->json(['message' => "{$count} review(s) {$action}d successfully."]);
    }

    // ─── Vendor Reply: Hide ───────────────────────────────────────────────────

    public function hideVendorReply(ReviewVendorReply $reply): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.edit'), 403);

        $reply->update(['status' => ReviewVendorReplyStatus::Hidden]);

        return response()->json(['message' => 'Vendor reply hidden.', 'status' => ReviewVendorReplyStatus::Hidden->value]);
    }

    // ─── Vendor Reply: Show ───────────────────────────────────────────────────

    public function showVendorReply(ReviewVendorReply $reply): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('reviews.edit'), 403);

        $reply->update(['status' => ReviewVendorReplyStatus::Published]);

        return response()->json(['message' => 'Vendor reply published.', 'status' => ReviewVendorReplyStatus::Published->value]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function maskName(string $name): string
    {
        $parts = explode(' ', trim($name));

        return implode(' ', array_map(function (string $part) {
            $len = mb_strlen($part);
            if ($len <= 2)
                return $part;
            $visible = max(1, (int) ceil($len / 3));
            return mb_substr($part, 0, $visible) . str_repeat('*', $len - $visible);
        }, $parts));
    }
}
