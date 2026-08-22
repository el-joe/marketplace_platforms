<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Notifications\Vendor\AdSlotBookingApproved;
use App\Notifications\Vendor\AdSlotBookingRejected;
use Illuminate\Support\Facades\Notification;
use App\Traits\HasDataTable;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaidAdBookingController extends Controller
{
    use HasDataTable;

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $stats = [
            'pending' => PaidAdBooking::where('status', 'pending')->count(),
            'active' => PaidAdBooking::where('status', 'active')->count(),
            'rejected' => PaidAdBooking::where('status', 'rejected')->count(),
        ];

        return view('admin.paid-ad-bookings.index', compact('stats'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

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
            'pending' => 'warning',
            'active' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'gray',
            'ended' => 'gray',
        ];

        $paymentColors = [
            'unpaid' => 'danger',
            'paid' => 'success',
            'invoiced' => 'warning',
            'refunded' => 'gray',
        ];

        $canEdit = $admin->hasPermissionTo('ad_campaigns.edit');

        return $this->dataTableResponse($request, $query, $columns, function (PaidAdBooking $row) use ($statusColors, $paymentColors, $canEdit) {
            $statusColor = $statusColors[$row->status->value] ?? 'gray';
            $statusLabel = $row->status->label();
            $statusBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$statusColor}-100 text-{$statusColor}-700\">{$statusLabel}</span>";

            $payColor = $paymentColors[$row->payment_status] ?? 'gray';
            $payLabel = ucfirst($row->payment_status ?? 'unpaid');
            $payBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$payColor}-100 text-{$payColor}-700\">{$payLabel}</span>";

            $showUrl = route('admin.paid-ad-bookings.show', $row->id);
            $approveUrl = route('admin.paid-ad-bookings.approve', $row->id);
            $rejectUrl = route('admin.paid-ad-bookings.reject', $row->id);

            $actions = '<div class="flex items-center gap-1">';
            $actions .= "<a href=\"{$showUrl}\" class=\"btn btn-xs btn-secondary\">View</a>";
            if ($canEdit && $row->status?->value === 'pending') {
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
                'vendor' => e($row->vendor?->store_name ?? '—'),
                'slot' => e($row->slot?->name ?? '—'),
                'dates' => Carbon::parse($row->booked_from)->format('d M') . ' – ' . Carbon::parse($row->booked_until)->format('d M Y'),
                'rate' => '$' . number_format($row->agreed_rate / 100, 2) . ' <span class="text-xs text-gray-400">' . strtoupper($row->currency ?? 'USD') . '</span>',
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
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $paidAdBooking->load(['slot.placementDefinition', 'vendor', 'country', 'approvedByAdmin', 'creatives.reviewedByAdmin']);

        return view('admin.paid-ad-bookings.show', compact('paidAdBooking'));
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function approve(PaidAdBooking $paidAdBooking): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        if ($paidAdBooking->status?->value !== 'pending') {
            return response()->json(['message' => 'Booking is not pending approval.'], 422);
        }

        $paidAdBooking->load('adSlot');
        $paidAdBooking->update([
            'status' => 'active',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        Notification::send($paidAdBooking->vendor->vendorAdmins, new AdSlotBookingApproved($paidAdBooking));

        return response()->json(['message' => 'Booking approved.']);
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function reject(Request $request, PaidAdBooking $paidAdBooking): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $paidAdBooking->load('adSlot');
        $paidAdBooking->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        Notification::send($paidAdBooking->vendor->vendorAdmins, new AdSlotBookingRejected($paidAdBooking, $request->input('rejection_reason')));

        return response()->json(['message' => 'Booking rejected.']);
    }

    // ─── Review Creative ──────────────────────────────────────────────────────

    public function reviewCreative(Request $request, PaidAdCreative $paidAdCreative): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
            'rejection_code' => ['nullable', 'string', 'max:50'],
        ]);

        $action = $request->input('action');

        if ($action === 'approve') {
            $paidAdCreative->update([
                'status' => 'approved',
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'rejection_reason' => null,
                'rejection_code' => null,
            ]);
        } else {
            $paidAdCreative->update([
                'status' => 'rejected',
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->input('rejection_reason'),
                'rejection_code' => $request->input('rejection_code'),
            ]);
        }

        return response()->json(['message' => 'Creative ' . $action . 'd.']);
    }
}
