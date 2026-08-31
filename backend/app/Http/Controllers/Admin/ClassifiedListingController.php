<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\ClassifiedListingAttachment;
use App\Services\ClassifiedListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassifiedListingController extends Controller
{
    public function __construct(private ClassifiedListingService $service) {}

    public function index(Request $request): View
    {
        $query = ClassifiedListing::with(['seller', 'classifiedCategory', 'city'])
            ->orderByRaw("FIELD(status, 'pending_review', 'pending_contract', 'draft') DESC")
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('classified_category_id', $request->category);
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('listing_number', 'like', "%{$term}%")
                  ->orWhere('title_en', 'like', "%{$term}%")
                  ->orWhere('title_ar', 'like', "%{$term}%");
            });
        }

        $listings   = $query->paginate(25)->withQueryString();
        $categories = ClassifiedCategory::where('is_active', true)->get();

        $pendingCount = ClassifiedListing::where('status', 'pending_review')->count();

        return view('admin.classified-listings.index', compact(
            'listings', 'categories', 'pendingCount'
        ));
    }

    public function show(ClassifiedListing $listing): View
    {
        $listing->load([
            'seller', 'classifiedCategory', 'city', 'country',
            'contractTemplate', 'attachments.verifiedByAdmin',
            'images', 'inquiries.customer',
        ]);

        return view('admin.classified-listings.show', compact('listing'));
    }

    public function approve(Request $request, ClassifiedListing $listing): RedirectResponse
    {
        try {
            $this->service->approve($listing, auth('admin')->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            return back()->with('error', $firstError ?? __('admin.classified_listings.approve_failed'));
        } catch (\Throwable $e) {
            return back()->with('error', __('admin.classified_listings.approve_failed'));
        }

        return back()->with('success', __('admin.classified_listings.approved_active'));
    }

    public function reject(Request $request, ClassifiedListing $listing): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $this->service->reject($listing, $request->reason);

        return back()->with('success', __('admin.classified_listings.rejected'));
    }

    public function verifyAttachment(Request $request, ClassifiedListingAttachment $attachment): JsonResponse
    {
        $action = $request->input('action', 'verify');

        $attachment->update([
            'status'                => $action === 'verify' ? 'verified' : 'rejected',
            'verified_by_admin_id'  => auth('admin')->id(),
        ]);

        return response()->json(['success' => true, 'status' => $attachment->status?->value]);
    }
}
