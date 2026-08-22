<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Vendor;
use App\Enums\ClassifiedListingStatus;
use App\Notifications\Admin\NewClassifiedListingPendingReview;
use App\Services\Shared\ClassifiedListingService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class ClassifiedListingController extends Controller
{
    use HasDataTable;
    use HasExport;

    private function vendor(): Vendor
    {
        $vendorAdmin = Auth::guard('vendor')->user();
        abort_unless($vendorAdmin, 403);

        return $vendorAdmin->vendor;
    }

    private function ownedListing(string $id): ClassifiedListing
    {
        return ClassifiedListing::forVendors()
            ->where('seller_id', $this->vendor()->id)
            ->findOrFail($id);
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportClassifieds($request);
        }

        $countries = Country::where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar', 'currency_code']);
        return view('partner.classifieds.index', compact('countries'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared query builder
    // ─────────────────────────────────────────────────────────────────────────

    private function buildClassifiedsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = ClassifiedListing::forVendors()
            ->where('seller_id', $this->vendor()->id)
            ->with(['images', 'classifiedCategory']);

        if ($request->filled('search_term') || $request->filled('search')) {
            $term = $request->input('search_term', $request->input('search'));
            $query->where(fn ($q) => $q->where('title_ar', 'like', "%{$term}%")
                                       ->orWhere('title_en', 'like', "%{$term}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('classified_listings.created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('classified_listings.created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function exportClassifieds(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $items = $this->buildClassifiedsQuery($request)->orderByDesc('created_at')->get();

        $headers = ['Listing #', 'Title', 'Status', 'Date'];

        $rows = $items->map(fn($row) => [
            $row->listing_number,
            $row->title_ar ?: $row->title_en,
            $row->status->value,
            $row->created_at->format('Y-m-d H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('classifieds', $headers, $rows),
            'csv' => $this->exportCsv('classifieds', $headers, $rows),
            'word' => $this->exportWord('classifieds', 'Classified Listings', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }

    public function show(string $id): View
    {
        // 404 on mismatch to avoid existence leak
        ClassifiedListing::forVendors()
            ->where('seller_id', $this->vendor()->id)
            ->findOrFail($id);

        return view('partner.classifieds.show', ['listingId' => $id]);
    }

    // ── DataTable ─────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['searchable_columns' => ['classified_listings.title_ar', 'classified_listings.title_en']],
            ['orderable_column'   => 'classified_listings.status'],
            ['orderable_column'   => 'classified_listings.price'],
            ['orderable_column'   => 'classified_listings.views_count'],
            ['orderable_column'   => 'classified_listings.created_at'],
            [],
            [],
        ];

        $query = $this->buildClassifiedsQuery($request);

        return $this->dataTableResponse($request, $query, $columns, fn (ClassifiedListing $l) => [
            'id'               => $l->id,
            'listing_number'   => $l->listing_number,
            'title_ar'         => $l->title_ar,
            'title_en'         => $l->title_en,
            'status'           => $l->status->value,
            'price'      => $l->price,
            'currency'         => $l->currency,
            'price_negotiable' => $l->price_negotiable,
            'listing_purpose'  => $l->listing_purpose,
            'views_count'      => $l->views_count,
            'created_at'       => $l->created_at?->toISOString(),
            'expires_at'       => $l->expires_at?->toISOString(),
            'primary_image'    => $l->primary_image_url,
            'category_name'    => $l->classifiedCategory?->name_ar ?? $l->classifiedCategory?->name_en,
        ]);
    }

    // ── Categories ────────────────────────────────────────────────────────────

    public function categories(): JsonResponse
    {
        $categories = ClassifiedCategory::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $categories]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $vendor = $this->vendor();

        $data = $request->validate([
            'classified_category_id'  => 'required|uuid|exists:classified_categories,id',
            'country_id'              => 'required|exists:countries,id',
            'city_id'                 => 'nullable|exists:cities,id',
            'listing_purpose'         => 'required|in:sale,rent',
            'title_ar'                => 'required|string|max:255',
            'title_en'                => 'required|string|max:255',
            'description_ar'          => 'nullable|string|max:5000',
            'description_en'          => 'nullable|string|max:5000',
            'price'                   => 'required|numeric|min:0',
            'currency'                => 'required|string|size:3',
            'price_negotiable'        => 'boolean',
            'attributes'              => 'nullable|array',
            'latitude'                => 'nullable|numeric|between:-90,90',
            'longitude'               => 'nullable|numeric|between:-180,180',
            'images'                  => 'required|array|min:1|max:10',
            'images.*'                => 'image|max:10240',
            'sketch_file'             => 'nullable|file|max:10240',
            'attachments'             => 'nullable|array',
            'attachments.*'           => 'file|max:10240',
            'vendor_listing_reference'    => 'nullable|uuid|exists:vendor_listings,id',
            'marketer_promotion_enabled'  => 'boolean',
        ]);

        // Derive currency from the selected country server-side, regardless of what was submitted
        $country = Country::find($data['country_id']);
        $data['currency'] = $country?->currency_code ?? $data['currency'];

        // Convert price → price for the service
        $data['price'] = (int) $data['price'];
        // Include uploaded files in the data array for the shared service
        $data['images']      = $request->file('images', []);
        $data['sketch_file'] = $request->file('sketch_file');
        $data['attachments'] = $request->file('attachments', []);

        $listing = app(ClassifiedListingService::class)->create($vendor, $data);

        if ($listing->status === ClassifiedListingStatus::PendingReview) {
            Notification::send(
                Admin::where('status', 'active')->get(),
                new NewClassifiedListingPendingReview($listing),
            );
        }

        return response()->json([
            'success'  => true,
            'message'  => $listing->status === ClassifiedListingStatus::PendingContract
                ? 'يرجى قبول العقد لإكمال نشر الإعلان.'
                : 'تم إرسال إعلانك للمراجعة بنجاح.',
            'redirect' => route('partner.classifieds.show', $listing->id),
        ], 201);
    }

    // ── Show Data ─────────────────────────────────────────────────────────────

    public function showData(string $id): JsonResponse
    {
        $listing = ClassifiedListing::forVendors()
            ->where('seller_id', $this->vendor()->id)
            ->with(['classifiedCategory', 'images', 'attachments', 'inquiries.customer'])
            ->findOrFail($id);

        return response()->json(['data' => $listing]);
    }

    // ── Status Transitions ────────────────────────────────────────────────────

    public function pause(string $id): JsonResponse
    {
        $listing = $this->ownedListing($id);

        if ($listing->status !== ClassifiedListingStatus::Active) {
            return response()->json(['success' => false, 'message' => 'الإعلان غير نشط حالياً.'], 422);
        }

        app(ClassifiedListingService::class)->pause($listing);

        return response()->json(['success' => true, 'message' => 'تم إيقاف الإعلان مؤقتاً.']);
    }

    public function resume(string $id): JsonResponse
    {
        $listing = $this->ownedListing($id);

        if ($listing->status !== ClassifiedListingStatus::Paused) {
            return response()->json(['success' => false, 'message' => 'الإعلان ليس متوقفاً.'], 422);
        }

        if ($listing->expires_at && $listing->expires_at->isPast()) {
            return response()->json(['success' => false, 'message' => 'انتهت صلاحية هذا الإعلان.'], 422);
        }

        app(ClassifiedListingService::class)->resume($listing);

        return response()->json(['success' => true, 'message' => 'تم استئناف الإعلان.']);
    }

    public function markSold(string $id): JsonResponse
    {
        $listing = $this->ownedListing($id);

        if (! in_array($listing->status, [ClassifiedListingStatus::Active, ClassifiedListingStatus::Paused], true)) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تحديد هذا الإعلان كمباع.'], 422);
        }

        app(ClassifiedListingService::class)->markSold($listing);

        return response()->json(['success' => true, 'message' => 'تم تحديد الإعلان كمباع/مؤجر.']);
    }

    // ── Inquiries ─────────────────────────────────────────────────────────────

    public function inquiriesIndex(string $id): JsonResponse
    {
        $listing   = $this->ownedListing($id);
        $inquiries = $listing->inquiries()->with('customer')->latest()->get();

        return response()->json(['data' => $inquiries]);
    }

    public function updateInquiryStatus(Request $request, string $id, string $inquiryId): JsonResponse
    {
        $listing = $this->ownedListing($id);
        $request->validate(['status' => 'required|in:new,contacted,closed']);

        $inquiry = $listing->inquiries()->findOrFail($inquiryId);
        $inquiry->update(['status' => $request->status]);

        return response()->json(['success' => true, 'message' => 'تم تحديث حالة الاستفسار.']);
    }

    // ── Contract ──────────────────────────────────────────────────────────────

    public function contractShow(string $id): JsonResponse
    {
        $listing = $this->ownedListing($id);

        if ($listing->status !== ClassifiedListingStatus::PendingContract) {
            return response()->json(['success' => false, 'message' => 'لا يوجد عقد بانتظار الموافقة.'], 422);
        }

        $template = $listing->classifiedCategory?->contractTemplate;

        return response()->json([
            'data' => [
                'text'    => $template?->content ?? '',
                'version' => $template?->version ?? null,
            ],
        ]);
    }

    public function contractAccept(Request $request, string $id): JsonResponse
    {
        $listing = $this->ownedListing($id);
        $request->validate([
            'signature_name' => 'required|string|max:255',
            'agreed'         => 'accepted',
        ]);

        if ($listing->status !== ClassifiedListingStatus::PendingContract) {
            return response()->json(['success' => false, 'message' => 'لا يوجد عقد بانتظار الموافقة.'], 422);
        }

        $signatureData = json_encode([
            'name' => $request->signature_name,
            'ip'   => $request->ip(),
            'at'   => now()->toISOString(),
        ]);

        app(ClassifiedListingService::class)->acceptContract($listing, $signatureData);

        $listing->refresh();
        if ($listing->status === ClassifiedListingStatus::PendingReview) {
            Notification::send(
                Admin::where('status', 'active')->get(),
                new NewClassifiedListingPendingReview($listing),
            );
        }

        return response()->json(['success' => true, 'message' => 'تم قبول العقد، إعلانك الآن قيد المراجعة.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function listingCell(ClassifiedListing $listing): string
    {
        $primaryImage = $listing->images->where('is_primary', true)->first()
            ?? $listing->images->first();

        $thumb = $primaryImage
            ? '<img src="' . asset('storage/' . $primaryImage->file_path) . '" class="w-10 h-10 rounded object-cover mr-2" alt="">'
            : '<div class="w-10 h-10 rounded bg-gray-200 mr-2 shrink-0"></div>';

        $title  = e($listing->title_ar ?: $listing->title_en);
        $number = e($listing->listing_number);

        return '<div class="flex items-center">'
            . $thumb
            . '<div><div class="font-medium text-sm">' . $title . '</div>'
            . '<div class="text-xs text-gray-400">' . $number . '</div></div></div>';
    }

    private function statusBadge(string $status): string
    {
        $classes = match ($status) {
            'active'           => 'bg-green-100 text-green-700',
            'paused'           => 'bg-yellow-100 text-yellow-700',
            'pending_review'   => 'bg-blue-100 text-blue-700',
            'pending_contract' => 'bg-indigo-100 text-indigo-700',
            'draft'            => 'bg-gray-100 text-gray-500',
            'sold'             => 'bg-purple-100 text-purple-700',
            'expired'          => 'bg-orange-100 text-orange-600',
            'rejected'         => 'bg-red-100 text-red-600',
            default            => 'bg-gray-100 text-gray-600',
        };

        $label = match ($status) {
            'active'           => 'نشط',
            'paused'           => 'موقوف',
            'pending_review'   => 'قيد المراجعة',
            'pending_contract' => 'بانتظار العقد',
            'draft'            => 'مسودة',
            'sold'             => 'تم البيع/التأجير',
            'expired'          => 'منتهي الصلاحية',
            'rejected'         => 'مرفوض',
            default            => $status,
        };

        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $classes . '">'
            . $label . '</span>';
    }
}
