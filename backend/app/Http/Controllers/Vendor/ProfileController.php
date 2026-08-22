<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Profile\DocumentReuploadRequest;
use App\Http\Requests\Vendor\Profile\UpdatePasswordRequest;
use App\Http\Requests\Vendor\Profile\UpdateProfileRequest;
use App\Http\Resources\Vendor\SellerDocumentResource;
use App\Http\Resources\Vendor\VendorProfileResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Vendor\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    private function vendor(): Vendor
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();
        return $admin->vendor;
    }

    // GET /profile
    public function show(): JsonResponse
    {
        return ApiResponse::success(new VendorProfileResource($this->vendor()));
    }

    // PUT /profile
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();

        if (!$admin->can('settings.edit')) {
            return ApiResponse::error('You do not have permission to edit store settings.', [], 403);
        }

        $this->vendor()->update($request->validated());
        return ApiResponse::success(new VendorProfileResource($this->vendor()->fresh()), 'Profile updated.');
    }

    // PUT /profile/password
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();

        if (!Hash::check($request->current_password, $admin->password)) {
            return ApiResponse::error('Current password is incorrect.', [], 422);
        }

        $admin->update(['password' => Hash::make($request->password)]);

        return ApiResponse::success(null, 'Password updated.');
    }

    // GET /profile/documents
    public function documents(): JsonResponse
    {
        $docs = $this->vendor()->documents()->latest()->get();
        return ApiResponse::success(SellerDocumentResource::collection($docs));
    }

    // POST /profile/documents/{type}/reupload
    public function reuploadDocument(DocumentReuploadRequest $request, string $type): JsonResponse
    {
        $allowedTypes = ['business_license', 'tax_certificate', 'owner_id', 'bank_proof', 'vat_registration'];

        if (!in_array($type, $allowedTypes, true)) {
            return ApiResponse::error('Invalid document type.', [], 422);
        }

        $doc = $this->documents->upload($this->vendor(), $type, $request->file('file'));

        return ApiResponse::success(new SellerDocumentResource($doc), 'Document re-uploaded successfully.');
    }
}
