<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminListing;
use Illuminate\View\View;

class AdminListingReviewController extends Controller
{
    public function index(string $listingId): View
    {
        $listing = AdminListing::findOrFail($listingId);

        $reviews = $listing->reviews()
            ->with('customer')
            ->latest()
            ->paginate(10);

        return view('admin.admin-listings.partials.reviews-table', compact('reviews'));
    }
}
