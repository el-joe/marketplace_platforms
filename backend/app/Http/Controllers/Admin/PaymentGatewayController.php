<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CountryPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentGatewayController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────

    public function index()
    {
        $countries = Country::where('is_active', true)
            ->with([
                'countryPaymentGateways' => fn($q) => $q
                    ->with('gateway')
                    ->orderBy('sort_order'),
            ])
            ->orderBy('name_en')
            ->get();

        $gateways = PaymentGateway::orderBy('sort_order')->get();

        return view('admin.payment-gateways.index', compact('countries', 'gateways'));
    }

    // ── Store (add gateway to a country) ──────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id'      => ['required', 'uuid', 'exists:countries,id'],
            'gateway_id'      => ['required', 'uuid', 'exists:payment_gateways,id',
                                  Rule::unique('country_payment_gateways')->where('country_id', $request->country_id)],
            'display_name_en' => ['required', 'string', 'max:100'],
            'display_name_ar' => ['nullable', 'string', 'max:100'],
            'is_active'       => ['boolean'],
            'environment'     => ['nullable', 'in:sandbox,production'],
            'fee_pct'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed'       => ['nullable', 'integer', 'min:0'],
            'min_order'       => ['nullable', 'integer', 'min:0'],
            'max_order'       => ['nullable', 'integer', 'min:0', 'gte:min_order'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
            'credentials'     => ['nullable', 'array'],
            'credentials.*'   => ['nullable', 'string'],
            'webhook_secret'  => ['nullable', 'string'],
        ]);

        $cpg = CountryPaymentGateway::create(
            array_diff_key($data, ['credentials' => 1, 'webhook_secret' => 1])
        );

        if (!empty(array_filter($data['credentials'] ?? []))) {
            $cpg->setCredentials(array_filter($data['credentials']));
        }
        if (!empty($data['webhook_secret'])) {
            $cpg->setWebhookSecret($data['webhook_secret']);
        }

        Cache::forget("payment_gateways:{$cpg->country_id}");

        return response()->json([
            'success' => true,
            'message' => 'Gateway added to country.',
            'data'    => $cpg->fresh()->load('gateway')->append(['is_configured']),
        ], 201);
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(Request $request, CountryPaymentGateway $countryGateway): JsonResponse
    {
        $data = $request->validate([
            'display_name_en' => ['sometimes', 'required', 'string', 'max:100'],
            'display_name_ar' => ['nullable', 'string', 'max:100'],
            'is_active'       => ['sometimes', 'boolean'],
            'environment'     => ['nullable', 'in:sandbox,production'],
            'fee_pct'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed'       => ['nullable', 'integer', 'min:0'],
            'min_order'       => ['nullable', 'integer', 'min:0'],
            'max_order'       => ['nullable', 'integer', 'min:0'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
            'credentials'     => ['nullable', 'array'],
            'credentials.*'   => ['nullable', 'string'],
            'webhook_secret'  => ['nullable', 'string'],
        ]);

        $countryGateway->update(
            array_diff_key($data, ['credentials' => 1, 'webhook_secret' => 1])
        );

        if (!empty(array_filter($data['credentials'] ?? []))) {
            $countryGateway->setCredentials(array_filter($data['credentials']));
        }
        if (!empty($data['webhook_secret'])) {
            $countryGateway->setWebhookSecret($data['webhook_secret']);
        }

        Cache::forget("payment_gateways:{$countryGateway->country_id}");

        return response()->json([
            'success' => true,
            'message' => 'Gateway updated.',
            'data'    => $countryGateway->fresh()->load('gateway')->append(['is_configured']),
        ]);
    }

    // ── Toggle active ─────────────────────────────────────────────────────

    public function toggleActive(CountryPaymentGateway $countryGateway): JsonResponse
    {
        $countryGateway->update(['is_active' => !$countryGateway->is_active]);
        Cache::forget("payment_gateways:{$countryGateway->country_id}");

        return response()->json([
            'success'   => true,
            'is_active' => $countryGateway->is_active,
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────

    public function destroy(CountryPaymentGateway $countryGateway): JsonResponse
    {
        $code = $countryGateway->gateway?->code;
        if ($code && PaymentTransaction::where('gateway', $code)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete — transactions exist for this gateway. Deactivate instead.',
            ], 422);
        }

        $countryId = $countryGateway->country_id;
        $countryGateway->delete();
        Cache::forget("payment_gateways:{$countryId}");

        return response()->json(['success' => true, 'message' => 'Gateway removed.']);
    }

    // ── Sort order ────────────────────────────────────────────────────────

    public function updateSortOrder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'exists:country_payment_gateways,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ])['items'];

        foreach ($items as $item) {
            CountryPaymentGateway::where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    // ── Test connection ───────────────────────────────────────────────────

    public function testConnection(CountryPaymentGateway $countryGateway): JsonResponse
    {
        try {
            $result = app(PaymentService::class)->testGatewayConnection($countryGateway);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
        ]);
    }

    // ── Gateway image ─────────────────────────────────────────────────────

    public function uploadImage(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:1024', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        if ($gateway->image) {
            Storage::disk('public')->delete($gateway->image);
        }

        $path = $request->file('image')->store('payment-gateways', 'public');

        $gateway->update(['image' => $path]);

        return response()->json([
            'success'   => true,
            'image_url' => Storage::url($path),
            'message'   => 'Gateway image updated.',
        ]);
    }

    public function deleteImage(PaymentGateway $gateway): JsonResponse
    {
        if ($gateway->image) {
            Storage::disk('public')->delete($gateway->image);
            $gateway->update(['image' => null]);
        }

        return response()->json(['success' => true, 'message' => 'Image removed.']);
    }

    // ── Webhook logs ──────────────────────────────────────────────────────

    public function webhookLogs(CountryPaymentGateway $countryGateway): JsonResponse
    {
        $logs = $countryGateway->webhookLogs()
            ->latest('created_at')
            ->paginate(50);

        return response()->json($logs);
    }
}
