<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketerListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\MarketerListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketerListingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $listings = MarketerListing::query()
            ->with(['marketer', 'country', 'productVariant.product', 'travelPackage', 'classifiedListing', 'approvedBy'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->country_id, fn ($q) => $q->where('country_id', $request->country_id))
            ->when($request->search, fn ($q) => $q->whereHas('marketer', fn ($mq) => $mq->where('name', 'like', "%{$request->search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $statuses = MarketerListingStatus::cases();
        $countries = Country::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $pendingCount = MarketerListing::where('status', MarketerListingStatus::PendingReview)->count();

        return view('admin.marketer-listings.index', compact('listings', 'statuses', 'countries', 'pendingCount'));
    }

    public function show(MarketerListing $listing): View
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $listing->load([
            'marketer',
            'country',
            'productVariant.product',
            'travelPackage',
            'classifiedListing',
            'approvedBy',
            'invitation.campaign.vendor',
        ]);

        return view('admin.marketer-listings.show', compact('listing'));
    }

    public function approve(MarketerListing $listing): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);
        abort_unless($listing->status === MarketerListingStatus::PendingReview, 422, 'الإدراج ليس في حالة مراجعة معلّقة.');

        $listing->update([
            'status' => MarketerListingStatus::Active,
            'approved_by_admin_id' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        return redirect()
            ->route('admin.marketer-listings.show', $listing)
            ->with('success', 'تمت الموافقة على الإدراج وتفعيله.');
    }

    public function reject(Request $request, MarketerListing $listing): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $listing->update([
            'status' => MarketerListingStatus::Rejected,
            'rejection_reason' => $request->rejection_reason,
            'approved_by_admin_id' => null,
            'approved_at' => null,
        ]);

        return redirect()
            ->route('admin.marketer-listings.show', $listing)
            ->with('success', 'تم رفض الإدراج.');
    }
}
