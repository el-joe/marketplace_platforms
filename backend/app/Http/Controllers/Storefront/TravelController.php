<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TravelController extends Controller
{
    // ── Index — search ────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = TravelPackage::query()
            ->where('status', TravelPackageStatus::Active)
            ->with(['agency', 'media'])
            ->when($request->input('country'), fn($q, $v) => $q->where('destination_country', $v))
            ->when($request->input('city'), fn($q, $v) => $q->where('destination_city', 'like', "%{$v}%"))
            ->when($request->input('departure_from'), fn($q, $v) => $q->whereDate('departure_date', '>=', $v))
            ->when($request->input('departure_to'), fn($q, $v) => $q->whereDate('departure_date', '<=', $v))
            ->when($request->input('max_price'), fn($q, $v) => $q->where('price', '<=', (int) $v * 100))
            ->when($request->input('min_days'), fn($q, $v) => $q->where('duration_days', '>=', (int) $v));

        $packages = $query->orderBy('departure_date')->paginate(16)->withQueryString();

        $countries = TravelPackage::where('status', TravelPackageStatus::Active)
            ->distinct()
            ->orderBy('destination_country')
            ->pluck('destination_country');

        return view('storefront.travel.index', compact('packages', 'countries'));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(TravelPackage $package): View
    {
        if ($package->status !== TravelPackageStatus::Active) {
            abort(404);
        }

        $package->load(['agency', 'media', 'inclusions']);

        return view('storefront.travel.show', compact('package'));
    }

    // ── Book form ─────────────────────────────────────────────────────────────

    public function bookForm(TravelPackage $package): View
    {
        if ($package->status !== TravelPackageStatus::Active) {
            abort(404);
        }

        $seats = $package->seatsRemaining();
        if ($seats !== null && $seats === 0) {
            return redirect()->route('travel.show', $package)
                ->withErrors(['seats' => __('common.exceptions.travel.sold_out')]);
        }

        return view('storefront.travel.book', compact('package'));
    }

    // ── Book ──────────────────────────────────────────────────────────────────

    public function book(Request $request, TravelPackage $package): RedirectResponse
    {
        if ($package->status !== TravelPackageStatus::Active) {
            abort(404);
        }

        $request->validate([
            'travelers_count'        => ['required', 'integer', 'min:1', 'max:20'],
            'passport_file'          => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'contract_signature_data' => ['required', 'string'],
        ]);

        $count    = (int) $request->input('travelers_count');

        $passportPath = $request->file('passport_file')
            ->store("travel-bookings/passports", 'public');

        $booking = DB::transaction(function () use ($package, $request, $count, $passportPath) {
            $pkg = TravelPackage::lockForUpdate()->findOrFail($package->id);

            if ($pkg->available_seats !== null
                && ($pkg->seats_booked + $count) > $pkg->available_seats
            ) {
                throw ValidationException::withMessages([
                    'travelers_count' => 'عدد المقاعد المطلوب غير متاح. الرجاء تقليل عدد المسافرين.',
                ]);
            }

            $booking = TravelBooking::create([
                'travel_package_id'       => $pkg->id,
                'customer_id'             => Auth::guard('web')->id(),
                'travelers_count'         => $count,
                'total_price'       => $pkg->price * $count,
                'passport_file_path'      => $passportPath,
                'contract_signed_at'      => now(),
                'contract_signature_data' => $request->input('contract_signature_data'),
                'status'                  => TravelBookingStatus::Confirmed,
            ]);

            $pkg->increment('seats_booked', $count);

            if ($pkg->available_seats !== null
                && $pkg->seats_booked >= $pkg->available_seats
            ) {
                $pkg->update(['status' => TravelPackageStatus::SoldOut]);
            }

            return $booking;
        });

        return redirect()->route('travel.booking.confirmed', $booking)
            ->with('success', __('common.exceptions.travel.booking_confirmed', ['reference' => $booking->booking_number]));
    }

    // ── Booking confirmation ───────────────────────────────────────────────────

    public function bookingConfirmed(TravelBooking $booking): View
    {
        if ($booking->customer_id !== Auth::guard('web')->id()) {
            abort(403);
        }

        $booking->load('package.agency');

        return view('storefront.travel.confirmed', compact('booking'));
    }
}
