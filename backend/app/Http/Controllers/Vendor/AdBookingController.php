<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\PaidAdBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Ads\CancelBookingRequest;
use App\Http\Requests\Vendor\Ads\CreateBookingRequest;
use App\Http\Requests\Vendor\Ads\UploadCreativeRequest;
use App\Http\Resources\Vendor\Ads\AdBookingResource;
use App\Http\Responses\ApiResponse;
use App\Models\PaidAdSlot;
use App\Models\Vendor;
use App\Services\Ads\AdBillingService;
use App\Services\Ads\AdBookingService;
use App\Services\Ads\AdCreativeService;
use App\Services\Ads\AdStatsService;
use App\Exceptions\InsufficientBalanceException;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdBookingController extends Controller
{
    public function __construct(
        private readonly AdBookingService $bookingService,
        private readonly AdCreativeService $creativeService,
        private readonly AdBillingService $billingService,
        private readonly AdStatsService $statsService,
    ) {
    }

    private function vendor(): Vendor
    {
        return auth('vendor')->user()->vendor;
    }

    private function resolve(string $id)
    {
        return \App\Models\PaidAdBooking::where('advertiser_type', 'vendor')
            ->where('vendor_id', $this->vendor()->id)
            ->with(['slot.placementDefinition', 'slot.pageBlock.page', 'slot.country', 'creatives.files'])
            ->find($id);
    }

    public function index(Request $request): JsonResponse
    {
        $query = \App\Models\PaidAdBooking::where('advertiser_type', 'vendor')
            ->where('vendor_id', $this->vendor()->id)
            ->with(['slot.placementDefinition', 'slot.pageBlock.page', 'slot.country', 'creatives.files'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $bookings = $query->paginate(20);

        return ApiResponse::paginated($bookings, AdBookingResource::class);
    }

    public function show(string $id): JsonResponse
    {
        $booking = $this->resolve($id);
        if (! $booking) {
            return ApiResponse::error('Not found.', [], 404);
        }

        return ApiResponse::success(new AdBookingResource($booking));
    }

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $vendor = $this->vendor();
        $slot = PaidAdSlot::bookable()
            ->where('country_id', $vendor->country_id)
            ->whereIn('allowed_advertisers', ['vendor', 'both'])
            ->find($request->validated('slot_id'));

        if (! $slot) {
            return ApiResponse::error('Not found.', [], 404);
        }

        try {
            $booking = $this->bookingService->createDraft(
                $slot,
                $vendor,
                Carbon::parse($request->validated('booked_from')),
                Carbon::parse($request->validated('booked_until')),
                $request->validated('budget'),
                $request->validated('payment_method'),
            );
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(new AdBookingResource($booking->load(['slot.placementDefinition', 'slot.pageBlock.page', 'slot.country'])), 'Success', 201);
    }

    public function uploadCreative(UploadCreativeRequest $request, string $id): JsonResponse
    {
        $booking = $this->resolve($id);
        if (! $booking) {
            return ApiResponse::error('Not found.', [], 404);
        }

        $files = array_filter([
            'desktop_en' => $request->file('desktop_en'),
            'desktop_ar' => $request->file('desktop_ar'),
            'mobile_en' => $request->file('mobile_en'),
            'mobile_ar' => $request->file('mobile_ar'),
        ]);

        try {
            $creative = $this->creativeService->upload($booking, $request->validated(), $files, auth('vendor')->user());
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(new \App\Http\Resources\Vendor\Ads\CreativeResource($creative), 'Success', 201);
    }

    public function submit(string $id): JsonResponse
    {
        $booking = $this->resolve($id);
        if (! $booking) {
            return ApiResponse::error('Not found.', [], 404);
        }

        try {
            $this->bookingService->submit($booking);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(new AdBookingResource($booking->fresh(['slot.placementDefinition', 'slot.pageBlock.page', 'slot.country', 'creatives.files'])));
    }

    public function pay(string $id): JsonResponse
    {
        $booking = $this->resolve($id);
        if (! $booking) {
            return ApiResponse::error('Not found.', [], 404);
        }

        try {
            $this->bookingService->pay($booking);
        } catch (InsufficientBalanceException $e) {
            $wallet = app(\App\Services\WalletService::class)->getOrCreateWallet('vendor', $booking->vendor_id, $booking->currency);

            return ApiResponse::error($e->getMessage(), [
                'required' => $booking->quoted_amount + $booking->tax_amount,
                'balance' => $wallet->balance,
                'currency' => $booking->currency,
            ]);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(new AdBookingResource($booking->fresh(['slot.placementDefinition', 'slot.pageBlock.page', 'slot.country', 'creatives.files'])));
    }

    public function cancel(CancelBookingRequest $request, string $id): JsonResponse
    {
        $booking = $this->resolve($id);
        if (! $booking) {
            return ApiResponse::error('Not found.', [], 404);
        }

        try {
            $this->bookingService->cancel($booking, 'vendor', $request->validated('reason'));
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(new AdBookingResource($booking->fresh(['slot.placementDefinition', 'slot.pageBlock.page', 'slot.country', 'creatives.files'])));
    }

    public function stats(string $id): JsonResponse
    {
        $booking = $this->resolve($id);
        if (! $booking) {
            return ApiResponse::error('Not found.', [], 404);
        }

        return ApiResponse::success($this->statsService->forBooking($booking));
    }
}
