<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorChangeRequest;
use App\Models\VendorSectionLock;
use App\Services\VendorChangeRequestService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorChangeRequestController extends Controller
{
    use HasDataTable;

    private const MASKED_FIELDS = ['iban', 'account_number', 'account_number_encrypted'];

    public function __construct(private readonly VendorChangeRequestService $changeRequestService)
    {
    }

    public function index(): View
    {
        return view('admin.vendor-change-requests.index', [
            'sections' => VendorSectionLock::sections(),
            'vendors' => Vendor::orderBy('store_name')->get(['id', 'store_name']),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = VendorChangeRequest::query()
            ->leftJoin('vendors', 'vendors.id', '=', 'vendor_change_requests.vendor_id')
            ->leftJoin('vendor_admins', 'vendor_admins.id', '=', 'vendor_change_requests.requested_by_vendor_admin_id')
            ->select('vendor_change_requests.*', 'vendors.store_name as vendor_store_name', 'vendor_admins.name as requested_by_name');

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('vendor_change_requests.status', $v),
            'section' => fn($q, $v) => $q->where('vendor_change_requests.section', $v),
            'vendor_id' => fn($q, $v) => $q->where('vendor_change_requests.vendor_id', $v),
        ]);

        $columns = [
            0 => ['searchable_columns' => ['vendors.store_name']],
            1 => ['orderable_column' => 'vendor_change_requests.section'],
            2 => ['orderable_column' => 'vendor_change_requests.request_type'],
            3 => ['searchable_columns' => ['vendor_admins.name']],
            4 => ['orderable_column' => 'vendor_change_requests.created_at'],
            5 => ['orderable_column' => 'vendor_change_requests.status'],
            6 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($r) {
            $showUrl = route('admin.vendor-change-requests.show', $r->id);

            return [
                'DT_RowId' => 'vcr-' . $r->id,
                'vendor' => '<a href="' . route('admin.vendors.show', $r->vendor_id) . '" class="text-sm font-medium text-primary-600 hover:underline">' . e($r->vendor_store_name ?? '—') . '</a>',
                'section' => '<span class="text-sm text-gray-700">' . e(__('admin.vendors.section_' . $r->section)) . '</span>',
                'type' => '<span class="text-xs text-gray-500">' . e(ucfirst($r->request_type)) . '</span>',
                'requested_by' => '<span class="text-sm text-gray-700">' . e($r->requested_by_name ?? '—') . '</span>',
                'requested_at' => '<span class="text-xs text-gray-500 whitespace-nowrap">' . \Carbon\Carbon::parse($r->created_at)->format('M d, Y H:i') . '</span>',
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $this->statusBadgeClass($r->status) . '">' . e(__('admin.vendor_change_requests.' . $r->status)) . '</span>',
                'actions' => '<a href="' . $showUrl . '" class="btn btn-xs btn-secondary">' . e(__('admin.vendor_change_requests.view')) . '</a>',
            ];
        });
    }

    public function show(string $id): View
    {
        $changeRequest = VendorChangeRequest::query()
            ->with(['vendor', 'requestedBy', 'reviewedByAdmin'])
            ->findOrFail($id);

        $currentData = $changeRequest->current_data ?? [];
        if ($changeRequest->section === VendorSectionLock::SECTION_BANK_ACCOUNTS) {
            $currentData = $this->maskBankFields($currentData);
        }

        return view('admin.vendor-change-requests.show', [
            'changeRequest' => $changeRequest,
            'currentData' => $currentData,
        ]);
    }

    public function approve(Request $request, VendorChangeRequest $changeRequest): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
        ]);

        $this->changeRequestService->approveRequest($changeRequest, auth('admin')->user(), $data['admin_note'] ?? null);

        return response()->json(['success' => true, 'message' => __('admin.vendor_change_requests.approved_success')]);
    }

    public function reject(Request $request, VendorChangeRequest $changeRequest): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string'],
        ]);

        $this->changeRequestService->rejectRequest($changeRequest, auth('admin')->user(), $data['admin_note']);

        return response()->json(['success' => true, 'message' => __('admin.vendor_change_requests.rejected_success')]);
    }

    private function maskBankFields(array $data): array
    {
        foreach (self::MASKED_FIELDS as $field) {
            if (!empty($data[$field]) && is_string($data[$field])) {
                $value = $data[$field];
                $data[$field] = str_repeat('•', max(0, strlen($value) - 4)) . substr($value, -4);
            }
        }

        return $data;
    }

    private function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'pending' => 'bg-yellow-100 text-yellow-700',
            'approved' => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700',
            'cancelled' => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-500',
        };
    }
}
