<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdPaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ads\MarkOfflinePaidRequest;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Services\Ads\AdBookingService;
use App\Services\Ads\AdCreativeService;
use App\Traits\HasDataTable;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaidAdBookingController extends Controller
{
    use HasDataTable;

    public function __construct(
        private readonly AdBookingService $bookingService,
        private readonly AdCreativeService $creativeService,
    ) {
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_bookings.view'), 403);

        $stats = [
            'pending' => PaidAdBooking::where('status', PaidAdBookingStatus::PendingReview->value)->count(),
            'active' => PaidAdBooking::where('status', PaidAdBookingStatus::Active->value)->count(),
            'rejected' => PaidAdBooking::where('status', PaidAdBookingStatus::Rejected->value)->count(),
        ];

        return view('admin.paid-ad-bookings.index', compact('stats'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_bookings.view'), 403);

        $query = PaidAdBooking::query()
            ->with(['slot', 'vendor', 'country', 'creatives' => fn($q) => $q->where('is_current', true)]);

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('status', $v),
            'payment_status' => fn($q, $v) => $q->where('payment_status', $v),
            'vendor_id' => fn($q, $v) => $q->where('vendor_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('booked_from', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('booked_until', '<=', $v),
        ]);

        $columns = [
            ['searchable_columns' => ['booking_reference'], 'orderable_column' => 'booking_reference'],
            ['searchable_columns' => [], 'orderable_column' => null], // vendor
            ['searchable_columns' => [], 'orderable_column' => null], // slot
            ['searchable_columns' => [], 'orderable_column' => 'booked_from'], // dates
            ['searchable_columns' => [], 'orderable_column' => 'agreed_rate'],
            ['searchable_columns' => [], 'orderable_column' => 'status'],
            ['searchable_columns' => [], 'orderable_column' => 'payment_status'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        $statusColors = [
            'pending_review' => 'warning',
            'approved' => 'info',
            'scheduled' => 'info',
            'active' => 'success',
            'paused' => 'warning',
            'completed' => 'gray',
            'rejected' => 'danger',
            'cancelled' => 'gray',
            'expired' => 'gray',
        ];

        $paymentColors = [
            'unpaid' => 'danger',
            'paid' => 'success',
            'reserved' => 'warning',
            'refunded' => 'gray',
        ];

        $canReview = $admin->hasPermissionTo('ad_bookings.review');

        return $this->dataTableResponse($request, $query, $columns, function (PaidAdBooking $row) use ($statusColors, $paymentColors, $canReview) {
            $statusColor = $statusColors[$row->status->value] ?? 'gray';
            $statusLabel = $row->status->label();
            $statusBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$statusColor}-100 text-{$statusColor}-700\">{$statusLabel}</span>";

            $payColor = $paymentColors[$row->payment_status?->value] ?? 'gray';
            $payLabel = ucfirst($row->payment_status?->value ?? 'unpaid');
            $payBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$payColor}-100 text-{$payColor}-700\">{$payLabel}</span>";

            $showUrl = route('admin.paid-ad-bookings.show', $row->id);
            $approveUrl = route('admin.paid-ad-bookings.approve', $row->id);
            $rejectUrl = route('admin.paid-ad-bookings.reject', $row->id);

            $actions = '<div class="flex items-center gap-1">';
            $actions .= "<a href=\"{$showUrl}\" class=\"btn btn-xs btn-secondary\">View</a>";
            if ($canReview && $row->status === PaidAdBookingStatus::PendingReview) {
                $actions .= "<button type=\"button\" class=\"btn btn-xs btn-success js-approve-booking-btn\" data-url=\"{$approveUrl}\" data-ref=\"" . e($row->booking_reference) . "\">Approve</button>";
                $actions .= "<button type=\"button\" class=\"btn btn-xs btn-danger js-reject-booking-btn\" data-url=\"{$rejectUrl}\" data-ref=\"" . e($row->booking_reference) . "\">Reject</button>";
            }
            $actions .= '</div>';

            // Current creative status
            $creative = $row->creatives->first();
            $creativeStatus = $creative
                ? "<span class=\"text-xs text-gray-500\">Creative: " . $creative->status->label() . "</span>"
                : '<span class="text-xs text-gray-400">No creative</span>';

            return [
                'reference' => '<span class="font-mono text-xs">' . e($row->booking_reference) . '</span><br>' . $creativeStatus,
                'vendor' => e($row->vendor?->store_name ?? $row->marketer?->company_name ?? '—'),
                'slot' => e($row->slot?->name ?? '—'),
                'dates' => Carbon::parse($row->booked_from)->format('d M') . ' – ' . Carbon::parse($row->booked_until)->format('d M Y'),
                'rate' => number_format($row->agreed_rate) . ' <span class="text-xs text-gray-400">' . strtoupper($row->currency ?? '') . '</span>',
                'status' => $statusBadge,
                'payment_status' => $payBadge,
                'actions' => $actions,
                'DT_RowData' => ['id' => $row->id, 'status' => $row->status?->value],
            ];
        });
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(PaidAdBooking $paidAdBooking): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_bookings.view'), 403);

        $paidAdBooking->load(['slot.placementDefinition', 'vendor', 'marketer', 'country', 'approvedByAdmin', 'creatives.reviewedByAdmin']);

        $derivedProduct = null;
        if ($paidAdBooking->slot?->derivesCreativeFromProduct()) {
            $ref = $paidAdBooking->creatives->firstWhere('is_current', true)?->destination_reference_id
                ?? $paidAdBooking->creatives->last()?->destination_reference_id;
            $listing = $ref ? \App\Models\VendorListing::with('productVariant.product')->find($ref) : null;
            if ($listing?->productVariant) {
                $derivedProduct = [
                    'name' => $listing->productVariant->product?->name_en,
                    'image' => app(\App\Services\Media\ListingImageResolver::class)->primary($listing->productVariant->id),
                ];
            }
        }

        return view('admin.paid-ad-bookings.show', compact('paidAdBooking', 'derivedProduct'));
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function approve(PaidAdBooking $paidAdBooking): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_bookings.review'), 403);

        try {
            $this->bookingService->approve($paidAdBooking, $admin);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Booking approved.']);
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function reject(Request $request, PaidAdBooking $paidAdBooking): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_bookings.review'), 403);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->bookingService->reject($paidAdBooking, $admin, $request->input('rejection_reason'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Booking rejected.']);
    }

    // ─── Mark Offline Paid ──────────────────────────────────────────────────────

    public function markOfflinePaid(MarkOfflinePaidRequest $request, PaidAdBooking $paidAdBooking): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_bookings.review'), 403);

        if ($paidAdBooking->payment_method !== PaidAdPaymentMethod::Offline) {
            return response()->json(['message' => 'This booking is not settled offline.'], 422);
        }

        $path = $request->file('file')->store("paid-ad-bookings/{$paidAdBooking->id}/payment-proofs", 'public');

        try {
            $this->bookingService->markOfflinePaid($paidAdBooking, $admin, $request->input('note'), $path);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Booking marked as paid.']);
    }

    // ─── Reject Offline Payment ─────────────────────────────────────────────────

    public function rejectOfflinePayment(Request $request, PaidAdBooking $paidAdBooking): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_bookings.review'), 403);

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($paidAdBooking->payment_method !== PaidAdPaymentMethod::Offline) {
            return response()->json(['message' => 'This booking is not settled offline.'], 422);
        }

        try {
            $this->bookingService->cancel($paidAdBooking, 'admin', $request->input('reason'), $admin);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Offline payment rejected; booking cancelled.']);
    }

    // ─── Review Creative ──────────────────────────────────────────────────────

    public function reviewCreative(Request $request, PaidAdCreative $paidAdCreative): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_bookings.review'), 403);

        $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
            'rejection_code' => ['nullable', 'string', 'max:50'],
        ]);

        $action = $request->input('action');

        try {
            if ($action === 'approve') {
                $this->creativeService->approve($paidAdCreative, $admin);
            } else {
                $this->creativeService->reject(
                    $paidAdCreative,
                    $admin,
                    $request->input('rejection_reason'),
                    $request->input('rejection_code'),
                );
            }
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Creative ' . $action . 'd.']);
    }
}
