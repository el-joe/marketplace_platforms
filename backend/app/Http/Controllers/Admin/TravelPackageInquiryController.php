<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelPackageInquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TravelPackageInquiryController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        $query = TravelPackageInquiry::query()
            ->with(['package.agency'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($agencyId = $request->query('agency_id')) {
            $query->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId));
        }

        $inquiries = $query->paginate(40)->withQueryString();

        return view('admin.travel.inquiries.index', compact('inquiries'));
    }
}
