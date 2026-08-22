<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\VendorAdmin;
use App\Models\VendorChangeRequest;
use App\Models\VendorSectionLock;
use App\Services\VendorChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VendorChangeRequestController extends Controller
{
    public function __construct(private readonly VendorChangeRequestService $changeRequests) {}

    private function vendorAdmin(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    public function index(): View
    {
        $requests = $this->vendorAdmin()->vendor->changeRequests()
            ->latest()
            ->paginate(20);

        return view('partner.change-requests.index', compact('requests'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section' => ['required', 'string', 'in:' . implode(',', VendorSectionLock::sections())],
            'requested_data' => ['required', 'array'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $admin  = $this->vendorAdmin();
        $vendor = $admin->vendor;

        try {
            $changeRequest = $this->changeRequests->submitRequest(
                vendor: $vendor,
                requestedBy: $admin,
                section: $data['section'],
                requestType: 'update',
                currentData: $vendor->only(array_keys($data['requested_data'])),
                requestedData: $data['requested_data'],
                vendorNote: $data['note'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال طلب التعديل للمراجعة من قبل الإدارة.',
            'id' => $changeRequest->id,
        ]);
    }

    public function cancel(VendorChangeRequest $changeRequest): JsonResponse
    {
        $admin = $this->vendorAdmin();

        try {
            $this->changeRequests->cancelRequest($changeRequest, $admin);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء طلب التعديل.',
        ]);
    }
}
