<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\BookableUnit;
use App\Models\BookableUnitAvailability;
use App\Models\BookableUnitTimeSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookableUnitController extends Controller
{
    use ResolvesTravelAgency;

    private function authorise(BookableUnit $unit): void
    {
        if ($unit->travel_agency_id !== $this->agencyId()) {
            abort(403);
        }
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $units = BookableUnit::where('travel_agency_id', $this->agencyId())
            ->withCount('reservations')
            ->latest()
            ->paginate(20);

        return view('travel-agency.bookable-units.index', compact('units'));
    }

    // ── Create / Store ───────────────────────────────────────────────────────

    public function create(): View
    {
        return view('travel-agency.bookable-units.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:chalet,hotel_room,other'],
            'capacity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        $unit = BookableUnit::create([
            ...$data,
            'travel_agency_id' => $this->agencyId(),
        ]);

        return redirect()->route('travel-agency.bookable-units.show', $unit)
            ->with('success', __('travel.bookable_units.created'));
    }

    // ── Show (unit detail + calendar) ───────────────────────────────────────

    public function show(BookableUnit $bookableUnit): View
    {
        $this->authorise($bookableUnit);

        $month = request('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $availability = $bookableUnit->availability()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        $timeSlots = $bookableUnit->timeSlots()->orderBy('starts_at')->get();

        return view('travel-agency.bookable-units.show', [
            'unit' => $bookableUnit,
            'month' => $start,
            'availability' => $availability,
            'timeSlots' => $timeSlots,
        ]);
    }

    // ── Edit / Update ────────────────────────────────────────────────────────

    public function edit(BookableUnit $bookableUnit): View
    {
        $this->authorise($bookableUnit);

        return view('travel-agency.bookable-units.edit', ['unit' => $bookableUnit]);
    }

    public function update(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        $this->authorise($bookableUnit);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:chalet,hotel_room,other'],
            'capacity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        $bookableUnit->update($data);

        return redirect()->route('travel-agency.bookable-units.show', $bookableUnit)
            ->with('success', __('travel.bookable_units.updated'));
    }

    public function destroy(BookableUnit $bookableUnit): RedirectResponse
    {
        $this->authorise($bookableUnit);
        $bookableUnit->delete();

        return redirect()->route('travel-agency.bookable-units.index')
            ->with('success', __('travel.bookable_units.deleted'));
    }

    // ── Calendar: availability + pricing per date ───────────────────────────

    /**
     * Upsert a single date's availability/pricing row. This is the minimum
     * bar from the plan; bulk range-set below is the nice-to-have.
     */
    public function upsertAvailability(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        $this->authorise($bookableUnit);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'is_available' => ['nullable', 'boolean'],
            'capacity_override' => ['nullable', 'integer', 'min:1'],
            'price_day_only' => ['nullable', 'integer', 'min:0'],
            'price_with_overnight' => ['nullable', 'integer', 'min:0'],
        ]);

        BookableUnitAvailability::updateOrCreate(
            ['bookable_unit_id' => $bookableUnit->id, 'date' => $data['date']],
            [
                'is_available' => $request->boolean('is_available', true),
                'capacity_override' => $data['capacity_override'] ?? null,
                'price_day_only' => $data['price_day_only'] ?? null,
                'price_with_overnight' => $data['price_with_overnight'] ?? null,
            ]
        );

        return back()->with('success', __('travel.bookable_units.availability_saved'));
    }

    /**
     * Nice-to-have: bulk-set the same availability/pricing values across a
     * date range in one call, upserting one row per day.
     */
    public function bulkUpsertAvailability(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        $this->authorise($bookableUnit);

        $data = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'is_available' => ['nullable', 'boolean'],
            'capacity_override' => ['nullable', 'integer', 'min:1'],
            'price_day_only' => ['nullable', 'integer', 'min:0'],
            'price_with_overnight' => ['nullable', 'integer', 'min:0'],
        ]);

        $isAvailable = $request->boolean('is_available', true);

        DB::transaction(function () use ($bookableUnit, $data, $isAvailable) {
            $cursor = Carbon::parse($data['date_from']);
            $end = Carbon::parse($data['date_to']);

            while ($cursor->lte($end)) {
                BookableUnitAvailability::updateOrCreate(
                    ['bookable_unit_id' => $bookableUnit->id, 'date' => $cursor->toDateString()],
                    [
                        'is_available' => $isAvailable,
                        'capacity_override' => $data['capacity_override'] ?? null,
                        'price_day_only' => $data['price_day_only'] ?? null,
                        'price_with_overnight' => $data['price_with_overnight'] ?? null,
                    ]
                );
                $cursor->addDay();
            }
        });

        return back()->with('success', __('travel.bookable_units.availability_saved'));
    }

    // ── Time slots ────────────────────────────────────────────────────────────

    public function storeTimeSlot(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        $this->authorise($bookableUnit);

        $data = $request->validate([
            'slot_type' => ['required', 'in:morning,evening,custom'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $bookableUnit->timeSlots()->create($data);

        return back()->with('success', __('travel.bookable_units.time_slot_saved'));
    }

    public function destroyTimeSlot(BookableUnit $bookableUnit, BookableUnitTimeSlot $timeSlot): RedirectResponse
    {
        $this->authorise($bookableUnit);

        if ($timeSlot->bookable_unit_id !== $bookableUnit->id) {
            abort(403);
        }

        $timeSlot->delete();

        return back()->with('success', __('travel.bookable_units.time_slot_deleted'));
    }
}
