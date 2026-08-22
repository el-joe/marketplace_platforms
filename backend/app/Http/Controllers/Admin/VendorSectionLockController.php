<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorSectionLock;
use App\Services\VendorSectionLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorSectionLockController extends Controller
{
    public function __construct(private readonly VendorSectionLockService $lockService)
    {
    }

    public function lock(Request $request, Vendor $vendor): JsonResponse
    {
        $data = $request->validate([
            'section' => ['required', 'in:' . implode(',', VendorSectionLock::sections())],
            'locked_reason' => ['required', 'string', 'max:255'],
        ]);

        $this->lockService->lockSection(
            $vendor->id,
            $data['section'],
            $data['locked_reason'],
            auth('admin')->user()
        );

        return response()->json(['success' => true, 'message' => 'Section locked']);
    }

    public function unlock(Request $request, Vendor $vendor): JsonResponse
    {
        $data = $request->validate([
            'section' => ['required', 'in:' . implode(',', VendorSectionLock::sections())],
        ]);

        $this->lockService->unlockSection($vendor->id, $data['section'], auth('admin')->user());

        return response()->json(['success' => true, 'message' => 'Section unlocked']);
    }
}
