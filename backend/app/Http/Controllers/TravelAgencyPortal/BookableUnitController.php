<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\BookableUnitReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\BookableUnit;
use App\Models\BookableUnitAvailability;
use App\Models\BookableUnitPhoto;
use App\Models\BookableUnitReservation;
use App\Models\BookableUnitTimeSlot;
use App\Models\TravelPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
            ->with('travelPackage:id,title_en,title_ar')
            ->withCount('reservations')
            ->latest()
            ->paginate(20);

        return view('travel-agency.bookable-units.index', compact('units'));
    }

    // ── Create / Store ───────────────────────────────────────────────────────

    public function create(): View
    {
        $packages = TravelPackage::where('travel_agency_id', $this->agencyId())
            ->orderBy('title_en')->get(['id', 'title_en', 'title_ar']);
        $selectedPackageId = request()->query('package_id');

        return view('travel-agency.bookable-units.create', compact('packages', 'selectedPackageId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:chalet,hotel_room,other'],
            'capacity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'travel_package_id' => ['nullable', 'uuid', 'exists:travel_packages,id'],
        ]);

        if ($data['travel_package_id'] ?? null) {
            $packageBelongsToAgency = TravelPackage::where('id', $data['travel_package_id'])
                ->where('travel_agency_id', $this->agencyId())
                ->exists();
            abort_unless($packageBelongsToAgency, 403);
        }

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

        $packages = TravelPackage::where('travel_agency_id', $this->agencyId())
            ->orderBy('title_en')
            ->get(['id', 'title_en', 'title_ar']);

        $bookableUnit->load('photos');

        return view('travel-agency.bookable-units.show', [
            'unit' => $bookableUnit,
            'month' => $start,
            'availability' => $availability,
            'timeSlots' => $timeSlots,
            'packages' => $packages,
        ]);
    }

    // ── Link to package ──────────────────────────────────────────────────────

    public function linkPackage(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        $this->authorise($bookableUnit);

        $data = $request->validate([
            'travel_package_id' => ['nullable', 'uuid', 'exists:travel_packages,id'],
        ]);

        // Ensure the selected package belongs to the same agency
        if ($data['travel_package_id'] ?? null) {
            $packageBelongsToAgency = TravelPackage::where('id', $data['travel_package_id'])
                ->where('travel_agency_id', $this->agencyId())
                ->exists();
            abort_unless($packageBelongsToAgency, 403);
        }

        $bookableUnit->update(['travel_package_id' => $data['travel_package_id'] ?? null]);

        return back()->with('success', __('travel.bookable_units.package_linked'));
    }

    // ── Edit / Update ────────────────────────────────────────────────────────

    public function edit(BookableUnit $bookableUnit): View
    {
        $this->authorise($bookableUnit);

        $bookableUnit->load('photos');

        $packages = TravelPackage::where('travel_agency_id', $this->agencyId())
            ->orderBy('title_en')->get(['id', 'title_en', 'title_ar']);

        return view('travel-agency.bookable-units.edit', ['unit' => $bookableUnit, 'packages' => $packages]);
    }

    public function update(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        $this->authorise($bookableUnit);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:chalet,hotel_room,other'],
            'capacity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'travel_package_id' => ['nullable', 'uuid', 'exists:travel_packages,id'],
        ]);

        if ($data['travel_package_id'] ?? null) {
            $packageBelongsToAgency = TravelPackage::where('id', $data['travel_package_id'])
                ->where('travel_agency_id', $this->agencyId())
                ->exists();
            abort_unless($packageBelongsToAgency, 403);
        }

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
     * Blocks closing a date range that overlaps an active (pending/confirmed)
     * reservation — silently closing a day a customer already booked would
     * strand that reservation with no unit for the day.
     */
    private function guardAgainstClosingBookedDates(BookableUnit $bookableUnit, Carbon $rangeStart, Carbon $rangeEnd): void
    {
        $conflicting = BookableUnitReservation::where('bookable_unit_id', $bookableUnit->id)
            ->whereIn('status', [BookableUnitReservationStatus::Pending, BookableUnitReservationStatus::Confirmed])
            ->whereDate('date_from', '<=', $rangeEnd->toDateString())
            ->whereDate('date_to', '>=', $rangeStart->toDateString())
            ->pluck('reservation_number');

        if ($conflicting->isNotEmpty()) {
            throw ValidationException::withMessages([
                'is_available' => __('travel.bookable_units.availability_conflict', [
                    'date' => $rangeStart->equalTo($rangeEnd) ? $rangeStart->toDateString() : $rangeStart->toDateString().' → '.$rangeEnd->toDateString(),
                    'reservations' => $conflicting->implode(', '),
                ]),
            ]);
        }
    }

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

        $isAvailable = $request->boolean('is_available');

        if (! $isAvailable) {
            $this->guardAgainstClosingBookedDates($bookableUnit, Carbon::parse($data['date']), Carbon::parse($data['date']));
        }

        BookableUnitAvailability::updateOrCreate(
            ['bookable_unit_id' => $bookableUnit->id, 'date' => $data['date']],
            [
                'is_available' => $isAvailable,
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

        $isAvailable = $request->boolean('is_available');

        if (! $isAvailable) {
            $this->guardAgainstClosingBookedDates($bookableUnit, Carbon::parse($data['date_from']), Carbon::parse($data['date_to']));
        }

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

    // ── Photos ────────────────────────────────────────────────────────────────

    public function storePhotos(Request $request, BookableUnit $bookableUnit): RedirectResponse
    {
        $this->authorise($bookableUnit);

        $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $position = $bookableUnit->photos()->max('position') ?? 0;
        $isFirst = $bookableUnit->photos()->count() === 0;

        foreach ($request->file('photos') as $i => $file) {
            $path = $file->store("bookable-unit-photos/{$bookableUnit->id}", 'public');
            $position++;
            $bookableUnit->photos()->create([
                'file_path' => $path,
                'position' => $position,
                'is_primary' => $isFirst && $i === 0,
            ]);
        }

        return back()->with('success', __('travel.bookable_units.photos_saved'));
    }

    public function destroyPhoto(BookableUnit $bookableUnit, BookableUnitPhoto $photo): JsonResponse
    {
        $this->authorise($bookableUnit);
        abort_if($photo->bookable_unit_id !== $bookableUnit->id, 404);

        Storage::disk('public')->delete($photo->file_path);
        $wasPrimary = $photo->is_primary;
        $photo->delete();

        if ($wasPrimary) {
            $bookableUnit->photos()->orderBy('position')->first()?->update(['is_primary' => true]);
        }

        return response()->json(['message' => __('travel.bookable_units.photo_deleted')]);
    }

    public function setPrimaryPhoto(BookableUnit $bookableUnit, BookableUnitPhoto $photo): JsonResponse
    {
        $this->authorise($bookableUnit);
        abort_if($photo->bookable_unit_id !== $bookableUnit->id, 404);

        $bookableUnit->photos()->update(['is_primary' => false]);
        $photo->update(['is_primary' => true]);

        return response()->json(['message' => __('travel.bookable_units.photo_set_primary')]);
    }
}
