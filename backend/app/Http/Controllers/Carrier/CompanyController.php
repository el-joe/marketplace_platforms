<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carrier\Company\UpdateCompanyRequest;
use App\Http\Requests\Carrier\Company\UpdateServedAreasRequest;
use App\Http\Resources\Carrier\ServedAreaResource;
use App\Http\Resources\Carrier\ShippingCompanyResource;
use App\Http\Responses\ApiResponse;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use App\Services\Carrier\CompanyService;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companyService) {}

    // ── GET /company ──────────────────────────────────────────────────────────

    public function show(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $company    = $supervisor->company()->with('country')->firstOrFail();

        return ApiResponse::success(new ShippingCompanyResource($company));
    }

    // ── PUT /company ──────────────────────────────────────────────────────────
    // // VERIFY: The documented permissions array has no explicit "edit_company"
    // permission. The web portal also applies no permission gate here.
    // Currently any active supervisor at an active company may update contact
    // details. If a future "company admin" concept is introduced, add a
    // carrier.permission middleware here.

    public function update(UpdateCompanyRequest $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $company    = $supervisor->company;

        $updated = $this->companyService->updateContact(
            $company,
            $request->validated(),
            $request->file('logo')
        );

        $updated->load('country');

        return ApiResponse::success(new ShippingCompanyResource($updated), __('carrier.api.company_updated'));
    }

    // ── GET /company/served-areas ─────────────────────────────────────────────

    public function servedAreas(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $areas      = $this->companyService->resolveServedAreas($supervisor->company);

        return ApiResponse::success(new ServedAreaResource($areas));
    }

    // ── PUT /company/served-areas ─────────────────────────────────────────────
    // // VERIFY: No explicit permission in the schema covers service-area edits.
    // These changes affect fallback routing and SLA commitments platform-wide.
    // Strongly consider requiring admin approval rather than direct self-service
    // before this endpoint is exposed in production.

    public function updateServedAreas(UpdateServedAreasRequest $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $updated = $this->companyService->updateServedAreas(
            $supervisor->company,
            $request->input('country_ids', []),
            $request->input('city_ids', [])
        );

        $areas = $this->companyService->resolveServedAreas($updated);

        return ApiResponse::success(new ServedAreaResource($areas), __('carrier.api.served_areas_updated'));
    }
}
