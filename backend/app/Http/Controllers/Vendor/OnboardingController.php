<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Onboarding\BankAccountRequest;
use App\Http\Requests\Vendor\Onboarding\BusinessDetailsRequest;
use App\Http\Requests\Vendor\Onboarding\DocumentUploadRequest;
use App\Http\Requests\Vendor\Onboarding\ShippingSettingsRequest;
use App\Http\Requests\Vendor\Onboarding\StoreIdentityRequest;
use App\Http\Resources\Vendor\SellerDocumentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Services\Vendor\DocumentService;
use App\Services\Vendor\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly DocumentService   $documents,
    ) {}

    private function vendor(): Vendor
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();
        return $admin->vendor;
    }

    // GET /onboarding/status
    public function status(): JsonResponse
    {
        return ApiResponse::success($this->onboarding->getStatus($this->vendor()));
    }

    // PUT /onboarding/store-identity
    public function storeIdentity(StoreIdentityRequest $request): JsonResponse
    {
        $this->onboarding->saveStoreIdentity($this->vendor(), $request->validated());
        return ApiResponse::success($this->onboarding->getStatus($this->vendor()), 'Store identity saved.');
    }

    // PUT /onboarding/business-details
    public function businessDetails(BusinessDetailsRequest $request): JsonResponse
    {
        $this->onboarding->saveBusinessDetails($this->vendor(), $request->validated());
        return ApiResponse::success($this->onboarding->getStatus($this->vendor()), 'Business details saved.');
    }

    // POST /onboarding/documents
    public function uploadDocument(DocumentUploadRequest $request): JsonResponse
    {
        $doc = $this->documents->upload(
            $this->vendor(),
            $request->document_type,
            $request->file('file')
        );

        return ApiResponse::success(new SellerDocumentResource($doc), 'Document uploaded.', 201);
    }

    // POST /onboarding/bank-account
    public function bankAccount(BankAccountRequest $request): JsonResponse
    {
        $vendor   = $this->vendor();
        $validated = $request->validated();

        // Encrypt account_number via Laravel's encrypted cast by assigning
        // through the model (account_number_encrypted column).
        $account = VendorBankAccount::create([
            'vendor_id'               => $vendor->id,
            'account_holder_name'     => $validated['account_holder_name'],
            'bank_name'               => $validated['bank_name'],
            'iban'                    => $validated['iban'] ?? null,
            'account_number_encrypted'=> $validated['account_number'] ?? null,
            'swift_code'              => $validated['swift_code'] ?? null,
            'currency'                => strtoupper($validated['currency']),
            'is_primary'              => !$vendor->bankAccounts()->exists(),
            'verification_status'     => 'pending',
        ]);

        return ApiResponse::success([
            'id'              => $account->id,
            'bank_name'       => $account->bank_name,
            'currency'        => $account->currency,
            'is_primary'      => $account->is_primary,
            'status'          => $account->verification_status?->value,
        ], 'Bank account saved.', 201);
    }

    // PUT /onboarding/shipping-settings
    public function shippingSettings(ShippingSettingsRequest $request): JsonResponse
    {
        $this->onboarding->saveShippingSettings($this->vendor(), $request->validated());
        return ApiResponse::success($this->onboarding->getStatus($this->vendor()), 'Shipping settings saved.');
    }

    // POST /onboarding/complete
    public function complete(): JsonResponse
    {
        try {
            $this->onboarding->markComplete($this->vendor());
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(null, 'Onboarding completed. Your store is under review.');
    }
}
