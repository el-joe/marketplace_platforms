<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorCommissionDiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVendorAcquisitionCommissionRequest;
use App\Http\Requests\Admin\UpdateVendorAcquisitionCommissionRequest;
use App\Http\Requests\Admin\UpdateVendorRequest;
use App\Models\Vendor;
use App\Models\VendorAcquisitionCommission;
use App\Services\ActivityLoggerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Vendor-scoped in-page writes for the /commissions tabs.
 *
 * The legacy vendor screens authorise these writes only with `vendors.view` and do not restrict
 * assigned-only admins to their own vendors. Here every action requires `vendors.edit` (route middleware)
 * and, for assigned-only admins, a vendor from their own list (404 otherwise, so a foreign vendor's
 * existence is not revealed). Validation and the acquisition write path are the existing ones.
 */
class CommissionSettingsController extends Controller
{
    public function __construct(private readonly ActivityLoggerService $activityLogger) {}

    public function updateVendorCommission(Request $request, Vendor $vendor): JsonResponse
    {
        $this->assertVendorInScope($request, $vendor);

        $section = $request->validate(['section' => ['required', 'in:flat_rate,discount']])['section'];
        $rules = UpdateVendorRequest::commissionRules();

        $fields = $section === 'flat_rate'
            ? ['commission_rate']
            : ['commission_discount_type', 'commission_discount_flat', 'commission_discount_percentage', 'commission_discount_notes'];

        $data = Validator::make($request->all(), Arr::only($rules, $fields))->validate();

        // The discount columns are NOT NULL with defaults, so a blank field means "default", not NULL.
        if ($section === 'discount') {
            $data['commission_discount_type'] ??= VendorCommissionDiscountType::None->value;
            $data['commission_discount_flat'] = (int) ($data['commission_discount_flat'] ?? 0);
            $data['commission_discount_percentage'] = (float) ($data['commission_discount_percentage'] ?? 0);
        }

        $before = $vendor->only($fields);
        $vendor->update($data);

        $this->activityLogger->log(
            description: 'vendor_commission_updated',
            subject: $vendor,
            causer: auth('admin')->user(),
            properties: ['section' => $section, 'before' => $before, 'after' => $vendor->fresh()->only($fields)],
            logName: 'vendor',
            event: 'vendor_commission_updated',
        );

        return response()->json(['message' => __('commissions.saved')]);
    }

    public function storeAcquisition(Request $request, Vendor $vendor): JsonResponse
    {
        $this->assertVendorInScope($request, $vendor);

        return app(VendorAcquisitionController::class)->store($this->validated(StoreVendorAcquisitionCommissionRequest::class, $request), $vendor);
    }

    public function updateAcquisition(Request $request, Vendor $vendor, VendorAcquisitionCommission $commission): JsonResponse
    {
        $this->assertVendorInScope($request, $vendor);

        return app(VendorAcquisitionController::class)->update($this->validated(UpdateVendorAcquisitionCommissionRequest::class, $request), $vendor, $commission);
    }

    public function revokeAcquisition(Request $request, Vendor $vendor, VendorAcquisitionCommission $commission): JsonResponse
    {
        $this->assertVendorInScope($request, $vendor);

        return app(VendorAcquisitionController::class)->revoke($request, $vendor, $commission);
    }

    private function assertVendorInScope(Request $request, Vendor $vendor): void
    {
        if ($request->attributes->get('is_scoped_admin') === true) {
            abort_unless(in_array($vendor->id, $request->attributes->get('scoped_vendor_ids') ?? [], true), 404);
        }
    }

    /**
     * Run an existing FormRequest's rules against this request so the delegated controller action receives
     * the exact validated request type it expects.
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $formRequest
     * @return T
     */
    private function validated(string $formRequest, Request $request): FormRequest
    {
        $form = $formRequest::createFrom($request);
        $form->setContainer(app())->setRedirector(app(Redirector::class));
        $form->validateResolved();

        return $form;
    }
}
