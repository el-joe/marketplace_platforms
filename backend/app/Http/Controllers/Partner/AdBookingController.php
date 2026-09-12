<?php

namespace App\Http\Controllers\Partner;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\PaidAdBooking;
use App\Models\PaidAdSlot;
use App\Models\Vendor;
use App\Services\Ads\AdBookingService;
use App\Services\Ads\AdCreativeService;
use App\Services\Ads\AdStatsService;
use App\Traits\HasDataTable;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdBookingController extends Controller
{
    use HasDataTable;

    public function __construct(
        private readonly AdBookingService $bookingService,
        private readonly AdCreativeService $creativeService,
        private readonly AdStatsService $statsService,
    ) {
    }

    private function vendor(): Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    private function resolve(string $id): PaidAdBooking
    {
        return PaidAdBooking::where('advertiser_type', 'vendor')
            ->where('vendor_id', $this->vendor()->id)
            ->with(['slot.placementDefinition', 'slot.pageBlock.page', 'slot.country', 'creatives.files', 'charges'])
            ->findOrFail($id);
    }

    public function index(): View
    {
        $vendorId = $this->vendor()->id;

        $counts = PaidAdBooking::where('advertiser_type', 'vendor')->where('vendor_id', $vendorId)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('partner.ad-bookings.index', compact('counts'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $vendorId = $this->vendor()->id;

        $columns = [
            ['searchable_columns' => ['paid_ad_bookings.booking_reference']],
            [],
            ['orderable_column' => 'paid_ad_bookings.booked_from'],
            ['orderable_column' => 'paid_ad_bookings.total_charged'],
            ['orderable_column' => 'paid_ad_bookings.payment_status'],
            ['orderable_column' => 'paid_ad_bookings.status'],
            [],
            [],
        ];

        $query = PaidAdBooking::where('advertiser_type', 'vendor')->where('vendor_id', $vendorId)
            ->with(['slot', 'currentCreative']);

        $query = $this->applyFilters($query, $request, [
            'status' => fn ($q, $v) => $q->where('status', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, fn (PaidAdBooking $b) => [
            'reference' => '<a href="'.route('partner.ad-bookings.show', $b->id).'" class="font-medium text-primary-600 hover:underline">'.e($b->booking_reference).'</a>',
            'slot' => e($b->slot?->name),
            'dates' => $b->booked_from?->format('d M Y').' - '.$b->booked_until?->format('d M Y'),
            'amount' => number_format($b->total_charged ?: ($b->quoted_amount + $b->tax_amount)).' '.$b->currency,
            'payment' => __('ads.payment_status.'.$b->payment_status->value),
            'status' => __('ads.booking_status.'.$b->status->value),
            'creative_status' => $b->currentCreative ? __('ads.creative_status.'.$b->currentCreative->status->value) : '-',
            'actions' => '<a href="'.route('partner.ad-bookings.show', $b->id).'" class="inline-flex items-center px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">View</a>',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'uuid'],
            'booked_from' => ['required', 'date'],
            'booked_until' => ['required', 'date', 'after_or_equal:booked_from'],
            'budget' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:wallet,payout_deduction'],
        ]);

        $vendor = $this->vendor();
        $slot = PaidAdSlot::bookable()
            ->where('country_id', $vendor->country_id)
            ->whereIn('allowed_advertisers', ['vendor', 'both'])
            ->findOrFail($data['slot_id']);

        try {
            $booking = $this->bookingService->createDraft(
                $slot,
                $vendor,
                Carbon::parse($data['booked_from']),
                Carbon::parse($data['booked_until']),
                $data['budget'] ?? null,
                $data['payment_method'],
            );
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Draft created.', 'data' => ['id' => $booking->id]], 201);
    }

    public function show(string $booking): View
    {
        $booking = $this->resolve($booking);
        $stats = $this->statsService->forBooking($booking);

        return view('partner.ad-bookings.show', compact('booking', 'stats'));
    }

    public function uploadCreative(Request $request, string $booking): JsonResponse
    {
        $booking = $this->resolve($booking);

        $isBoostOnly = $booking->slot->target_type === \App\Enums\PaidAdSlotTargetType::ListingPromotion
            && ! $booking->slot->shows_popup;

        $data = $request->validate([
            'desktop_en' => [$isBoostOnly ? 'nullable' : 'required', 'image'],
            'desktop_ar' => ['nullable', 'image'],
            'mobile_en' => [$isBoostOnly ? 'nullable' : 'required', 'image'],
            'mobile_ar' => ['nullable', 'image'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string', 'max:255'],
            'subtitle_ar' => ['nullable', 'string', 'max:255'],
            'cta_label_en' => ['nullable', 'string', 'max:60'],
            'cta_label_ar' => ['nullable', 'string', 'max:60'],
            'destination_type' => ['required', 'in:listing,classified_listing,store,brand,category'],
            'destination_reference_id' => ['nullable', 'string'],
        ]);

        $files = array_filter([
            'desktop_en' => $request->file('desktop_en'),
            'desktop_ar' => $request->file('desktop_ar'),
            'mobile_en' => $request->file('mobile_en'),
            'mobile_ar' => $request->file('mobile_ar'),
        ]);

        try {
            $this->creativeService->upload($booking, $data, $files, Auth::guard('vendor')->user());
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Creative uploaded.']);
    }

    public function submit(string $booking): JsonResponse
    {
        $booking = $this->resolve($booking);

        try {
            $this->bookingService->submit($booking);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Submitted for review.']);
    }

    public function pay(string $booking): JsonResponse
    {
        $booking = $this->resolve($booking);

        try {
            $this->bookingService->pay($booking);
        } catch (InsufficientBalanceException $e) {
            $wallet = app(\App\Services\WalletService::class)->getOrCreateWallet('vendor', $booking->vendor_id, $booking->currency);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'required' => $booking->quoted_amount + $booking->tax_amount,
                'balance' => $wallet->balance,
                'currency' => $booking->currency,
            ], 422);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Payment collected.']);
    }

    public function cancel(Request $request, string $booking): JsonResponse
    {
        $booking = $this->resolve($booking);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->bookingService->cancel($booking, 'vendor', $data['reason']);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Booking cancelled.']);
    }

    public function stats(string $booking): JsonResponse
    {
        $booking = $this->resolve($booking);

        return response()->json(['success' => true, 'data' => $this->statsService->forBooking($booking)]);
    }
}
