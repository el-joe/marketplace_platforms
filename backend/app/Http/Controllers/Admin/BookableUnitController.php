<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookableUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookableUnitController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->hasPermissionTo('travel.view'), 403);

        $units = BookableUnit::with('agency:id,name')
            ->withCount('reservations')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('agency_id'), fn ($q) => $q->where('travel_agency_id', $request->agency_id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.bookable-units.index', compact('units'));
    }

    public function show(BookableUnit $bookableUnit): View
    {
        abort_unless(auth('admin')->user()->hasPermissionTo('travel.view'), 403);

        $bookableUnit->load('agency:id,name', 'timeSlots');
        $reservations = $bookableUnit->reservations()->latest()->limit(50)->get();
        $upcomingAvailability = $bookableUnit->availability()
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit(31)
            ->get();

        return view('admin.bookable-units.show', [
            'unit' => $bookableUnit,
            'reservations' => $reservations,
            'availability' => $upcomingAvailability,
        ]);
    }

    public function approve(BookableUnit $bookableUnit): RedirectResponse
    {
        abort_unless(auth('admin')->user()->hasPermissionTo('travel.manage') || auth('admin')->user()->hasPermissionTo('travel.view'), 403);

        $bookableUnit->update([
            'status' => 'active',
            'approved_by_admin_id' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', __('admin.bookable_units_section.approved'));
    }
}
