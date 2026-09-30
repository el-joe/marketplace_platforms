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

        $units = BookableUnit::with(['agency:id,name', 'travelPackage:id,title_en,title_ar'])
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

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $availabilityByDate = $bookableUnit->availability()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        $calendarDays = collect();
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $dateStr = $day->toDateString();
            $row = $availabilityByDate->get($dateStr);
            $calendarDays->push((object) [
                'date' => $day->copy(),
                'is_available' => $row?->is_available ?? false,
                'price_day_only' => $row?->price_day_only,
                'has_row' => $row !== null,
            ]);
        }

        return view('admin.bookable-units.show', [
            'unit' => $bookableUnit,
            'reservations' => $reservations,
            'calendarDays' => $calendarDays,
            'calendarMonth' => $monthStart->format('F Y'),
            'firstWeekday' => (int) $monthStart->dayOfWeek,
        ]);
    }

    public function approve(BookableUnit $bookableUnit): RedirectResponse
    {
        abort_unless(auth('admin')->user()->hasPermissionTo('travel.approve') || auth('admin')->user()->hasPermissionTo('travel.view'), 403);

        $bookableUnit->update([
            'status' => 'active',
            'approved_by_admin_id' => auth('admin')->id(),
            'approved_at' => now(),
            'rejected_by_admin_id' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        return back()->with('success', __('admin.bookable_units_section.approved'));
    }

    public function reject(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        abort_unless(auth('admin')->user()->hasPermissionTo('travel.reject') || auth('admin')->user()->hasPermissionTo('travel.view'), 403);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $bookableUnit->update([
            'status' => 'rejected',
            'rejected_by_admin_id' => auth('admin')->id(),
            'rejected_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return back()->with('success', __('admin.bookable_units_section.rejected'));
    }

    /**
     * POST /admin/travel/bookable-units/{unit}/status
     * Lets an admin set any status (draft → active → paused → archived).
     * Rejection still goes through the dedicated reject() action because it
     * requires a reason; this route refuses the 'rejected' value.
     */
    public function changeStatus(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        abort_unless(
            auth('admin')->user()->hasPermissionTo('travel.approve') ||
            auth('admin')->user()->hasPermissionTo('travel.view'),
            403
        );

        $data = $request->validate([
            'status' => ['required', 'in:draft,active,paused,archived'],
        ]);

        $update = ['status' => $data['status']];

        if ($data['status'] === 'active') {
            $update['approved_by_admin_id'] = auth('admin')->id();
            $update['approved_at'] = now();
            $update['rejected_by_admin_id'] = null;
            $update['rejected_at'] = null;
            $update['rejection_reason'] = null;
        }

        $bookableUnit->update($update);

        return back()->with('success', __('admin.bookable_units_section.status_changed'));
    }
}
