<?php

namespace App\Http\Controllers\Partner;

use App\Enums\ShippingCompanyStatus;
use App\Http\Controllers\Controller;
use App\Models\ShippingCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * FBM (vendor-owned shipping) — client feature request section 4.
 *
 * Lets a vendor see every shipping company available to them (public
 * companies, read-only) and manage their own private ones (e.g. an
 * in-house delivery staff only that vendor uses). Public companies stay
 * admin-managed via App\Http\Controllers\Admin\ShippingCompanyController.
 */
class ShippingCompanyController extends Controller
{
    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    public function index(): View
    {
        $vendorId = $this->vendorId();

        $companies = ShippingCompany::visibleTo($vendorId)
            ->orderByRaw('owner_vendor_id is null')
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name', 'contact_email', 'contact_phone', 'status', 'owner_vendor_id']);

        return view('partner.shipping-companies.index', compact('companies'));
    }

    public function store(Request $request): JsonResponse
    {
        $vendorId = $this->vendorId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255', 'unique:shipping_companies,contact_email'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        // New vendor-created shipping companies always default to private
        // (owner_vendor_id = this vendor) — vendors cannot create public ones.
        $company = ShippingCompany::create([
            'name' => $data['name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'status' => ShippingCompanyStatus::Active,
            'owner_vendor_id' => $vendorId,
        ]);

        return response()->json([
            'message' => __('partner.shipping_companies.messages.created'),
            'data' => ['id' => $company->id],
        ]);
    }

    public function update(Request $request, ShippingCompany $shippingCompany): JsonResponse
    {
        abort_unless($shippingCompany->owner_vendor_id === $this->vendorId(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255', 'unique:shipping_companies,contact_email,'.$shippingCompany->id],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $shippingCompany->update($data);

        return response()->json(['message' => __('partner.shipping_companies.messages.updated')]);
    }

    public function destroy(ShippingCompany $shippingCompany): JsonResponse
    {
        abort_unless($shippingCompany->owner_vendor_id === $this->vendorId(), 403);

        if ($shippingCompany->agents()->exists() || $shippingCompany->carriers()->exists()) {
            return response()->json([
                'message' => __('partner.shipping_companies.messages.in_use'),
            ], 422);
        }

        $shippingCompany->delete();

        return response()->json(['message' => __('partner.shipping_companies.messages.deleted')]);
    }
}
