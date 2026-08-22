<?php

namespace App\Http\Controllers\Partner;

use App\Enums\ReturnRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Notifications\Customer\ReturnApprovedNotification;
use App\Notifications\Customer\ReturnRejectedNotification;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ReturnController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function index(Request $request)
    {
        if ($request->filled('export')) {
            return $this->exportReturns($request);
        }

        $vendor = Auth::guard('vendor')->user();

        $returns = $this->buildReturnsQuery($request)
            ->with(['order:id,order_number', 'subOrder:id,sub_order_number', 'customer:id,first_name,last_name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('partner.returns.index', compact('vendor', 'returns'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildReturnsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $vendor = Auth::guard('vendor')->user();

        $query = ReturnRequest::where('vendor_id', $vendor->vendor_id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('return_number', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    private function exportReturns(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $returns = $this->buildReturnsQuery($request)
            ->with(['order:id,order_number'])
            ->latest()
            ->get();

        $headers = ['Return #', 'Order #', 'Reason', 'Status', 'Date'];

        $rows = $returns->map(fn($row) => [
            $row->return_number,
            $row->order?->order_number,
            $row->reason,
            $row->status instanceof \BackedEnum ? $row->status->value : $row->status,
            $row->created_at->format('Y-m-d H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('returns', $headers, $rows),
            'csv' => $this->exportCsv('returns', $headers, $rows),
            'word' => $this->exportWord('returns', 'Returns', $rows),
            default => abort(400, __('partner.returns.messages.invalid_export_format')),
        };
    }

    public function show(string $returnNumber)
    {
        $vendor = Auth::guard('vendor')->user();

        $return = ReturnRequest::where('return_number', $returnNumber)
            ->where('vendor_id', $vendor->vendor_id)
            ->with([
                'order:id,order_number',
                'subOrder:id,sub_order_number',
                'customer:id,first_name,last_name',
                'items.orderItem:id,product_snapshot',
                'messages' => fn ($q) => $q->where('is_internal_note', false)->oldest()->with('attachments'),
            ])
            ->firstOrFail();

        return view('partner.returns.show', compact('vendor', 'return'));
    }

    public function approve(Request $request, string $returnNumber): RedirectResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $returnRequest = ReturnRequest::where('return_number', $returnNumber)
            ->where('vendor_id', $vendor->vendor_id)
            ->firstOrFail();

        if ($returnRequest->status !== ReturnRequestStatus::Requested) {
            return back()->withErrors(['status' => __('partner.returns.messages.cannot_be_reviewed')]);
        }

        DB::transaction(function () use ($returnRequest) {
            $returnRequest->update(['status' => ReturnRequestStatus::Approved]);
            Notification::send($returnRequest->customer, new ReturnApprovedNotification($returnRequest));
        });

        return back()->with('success', __('partner.returns.messages.approved'));
    }

    public function reject(Request $request, string $returnNumber): RedirectResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $returnRequest = ReturnRequest::where('return_number', $returnNumber)
            ->where('vendor_id', $vendor->vendor_id)
            ->firstOrFail();

        $request->validate(['rejection_reason' => 'required|string|max:500']);

        if ($returnRequest->status !== ReturnRequestStatus::Requested) {
            return back()->withErrors(['status' => __('partner.returns.messages.cannot_be_reviewed')]);
        }

        DB::transaction(function () use ($returnRequest, $request) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::Rejected,
                'rejection_reason' => $request->rejection_reason,
            ]);
            Notification::send($returnRequest->customer, new ReturnRejectedNotification($returnRequest));
        });

        return back()->with('success', __('partner.returns.messages.rejected'));
    }
}
