<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\SellerDocumentResource;
use App\Http\Resources\Vendor\VendorProfileResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    private function vendor(): Vendor { return Auth::guard('vendor_api')->user()->vendor; }

    public function show(): JsonResponse
    {
        return ApiResponse::success(new VendorProfileResource($this->vendor()));
    }

    public function documents(): JsonResponse
    {
        $docs = $this->vendor()->documents()->latest()->get();
        return ApiResponse::success(SellerDocumentResource::collection($docs));
    }
}
