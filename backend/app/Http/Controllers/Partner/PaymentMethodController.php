<?php

namespace App\Http\Controllers\Partner;

use App\Enums\FulfillmentModel;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\VendorListing;
use App\Models\VendorPaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * FBM (vendor-owned shipping) — client feature request section 4.
 *
 * Lets an FBM vendor choose which platform payment gateways their own
 * customers can pay with. Gated to FBM only: a vendor who isn't shipping
 * their own orders doesn't own the payment relationship, so the platform's
 * default gateway set applies to them instead.
 */
class PaymentMethodController extends Controller
{
    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    private function hasFbmListings(string $vendorId): bool
    {
        return VendorListing::where('vendor_id', $vendorId)
            ->where('fulfillment_model', FulfillmentModel::Fbm->value)
            ->whereNotIn('status', ['archived'])
            ->exists();
    }

    public function index(): View
    {
        $vendorId = $this->vendorId();
        $isFbmVendor = $this->hasFbmListings($vendorId);

        $gateways = PaymentGateway::active()
            ->orderBy('sort_order')
            ->get();

        $enabledGatewayIds = VendorPaymentMethod::where('vendor_id', $vendorId)
            ->where('is_enabled', true)
            ->pluck('payment_gateway_id')
            ->all();

        return view('partner.payment-methods.index', compact('gateways', 'enabledGatewayIds', 'isFbmVendor'));
    }

    public function update(Request $request): JsonResponse
    {
        $vendorId = $this->vendorId();

        abort_unless($this->hasFbmListings($vendorId), 403, __('partner.payment_methods.messages.fbm_only'));

        $validated = $request->validate([
            'payment_gateway_id' => ['required', 'uuid', 'exists:payment_gateways,id'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        VendorPaymentMethod::updateOrCreate(
            ['vendor_id' => $vendorId, 'payment_gateway_id' => $validated['payment_gateway_id']],
            ['is_enabled' => $validated['is_enabled']],
        );

        return response()->json(['message' => __('partner.payment_methods.messages.updated')]);
    }
}
