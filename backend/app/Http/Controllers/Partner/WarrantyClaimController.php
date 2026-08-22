<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\WarrantyClaim;
use App\Models\WarrantyClaimMessage;
use App\Notifications\Admin\WarrantyClaimVendorRepliedNotification;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class WarrantyClaimController extends Controller
{
    use HasDataTable;
    use HasExport;

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportClaims($request);
        }

        $claims = $this->buildClaimsQuery($request)
            ->with(['customer:id,name', 'product:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('partner.warranty-claims.index', compact('claims'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildClaimsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = WarrantyClaim::where('vendor_id', $this->vendorId())
            ->where('listing_type', WarrantyClaim::LISTING_TYPE_VENDOR);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('claim_number', 'like', "%{$search}%");
        }

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('status', $v),
            'date_from' => fn($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('created_at', '<=', $v),
        ]);

        return $query;
    }

    private function exportClaims(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $claims = $this->buildClaimsQuery($request)
            ->with('orderItem.subOrder:id,sub_order_number')
            ->latest()
            ->get();

        $headers = ['Claim #', 'Order #', 'Status', 'Date'];

        $rows = $claims->map(fn($row) => [
            $row->claim_number,
            $row->orderItem?->subOrder?->sub_order_number ?? '—',
            is_object($row->status) ? $row->status->value ?? (string) $row->status : $row->status,
            $row->created_at->format('Y-m-d H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('warranty-claims', $headers, $rows),
            'csv' => $this->exportCsv('warranty-claims', $headers, $rows),
            'word' => $this->exportWord('warranty-claims', 'Warranty Claims', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }

    public function show(WarrantyClaim $claim): View
    {
        abort_unless(
            $claim->vendor_id === $this->vendorId() && $claim->listing_type === WarrantyClaim::LISTING_TYPE_VENDOR,
            403
        );

        $claim->load([
            'messages' => fn ($q) => $q->where('is_internal_note', false)->oldest('created_at'),
            'customer:id,name',
            'product:id,name',
        ]);

        return view('partner.warranty-claims.show', compact('claim'));
    }

    public function respond(Request $request, WarrantyClaim $claim): RedirectResponse
    {
        abort_unless(
            $claim->vendor_id === $this->vendorId() && $claim->listing_type === WarrantyClaim::LISTING_TYPE_VENDOR,
            403
        );

        abort_unless(
            in_array($claim->status, [WarrantyClaim::STATUS_SUBMITTED, WarrantyClaim::STATUS_UNDER_REVIEW], true),
            403
        );

        $data = $request->validate([
            'vendor_response' => 'required|string|min:10|max:2000',
        ]);

        $vendorAdmin = Auth::guard('vendor')->user();

        $claim->update(['vendor_response' => $data['vendor_response']]);

        WarrantyClaimMessage::create([
            'warranty_claim_id' => $claim->id,
            'sender_user_id' => $vendorAdmin->id,
            'sender_role' => WarrantyClaimMessage::SENDER_ROLE_VENDOR,
            'message' => $data['vendor_response'],
            'is_internal_note' => false,
            'created_at' => now(),
        ]);

        Notification::send(
            Admin::permission('warranty_claims.manage')->get(),
            new WarrantyClaimVendorRepliedNotification($claim)
        );

        return back()->with('success', __('partner.warranty_claims.response_sent'));
    }
}
