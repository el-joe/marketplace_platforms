<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\MarketerContract;
use App\Models\MarketerContractAcceptance;
use App\Models\MarketerContractVersion;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MarketerContractController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $marketerId = (string) $request->route('marketer');
        abort_unless(Str::isUuid($marketerId), 404);

        $contract = MarketerContract::with('activeVersion')
            ->where('marketer_id', $marketerId)
            ->first();

        if (! $contract || ! $contract->activeVersion) {
            return response()->json(['contract' => null]);
        }

        $version = $contract->activeVersion;

        return response()->json([
            'contract' => [
                'version_id' => $version->id,
                'version_number' => $version->version_number,
                'title_en' => $version->title_en,
                'title_ar' => $version->title_ar,
                'content_type' => $version->content_type,
                'file_url' => $version->content_type === 'pdf'
                    ? route('customer.api.marketer-contract.download', [
                        $request->attributes->get('country')?->site_code,
                        $marketerId,
                    ])
                    : null,
                'text_content' => $version->content_type === 'text' ? $version->text_content : null,
                'is_required' => $contract->is_required,
            ],
        ]);
    }

    public function downloadActivePdf(Request $request)
    {
        $marketerId = (string) $request->route('marketer');
        abort_unless(Str::isUuid($marketerId), 404);

        $contract = MarketerContract::with('activeVersion')
            ->where('marketer_id', $marketerId)
            ->firstOrFail();

        $version = $contract->activeVersion;
        abort_if(! $version || ! $version->file_url || $version->content_type !== 'pdf', 404);

        return Storage::disk('local')->response($version->file_url);
    }

    public function accept(Request $request): JsonResponse
    {
        $marketerId = (string) $request->route('marketer');
        abort_unless(Str::isUuid($marketerId), 404);

        $validated = $request->validate([
            'version_id' => ['required', 'uuid'],
        ]);

        $version = MarketerContractVersion::query()
            ->where('id', $validated['version_id'])
            ->where('is_active', true)
            ->whereHas('contract', fn ($q) => $q->where('marketer_id', $marketerId))
            ->first();

        if (! $version) {
            return response()->json(['message' => 'Contract version is not the active version for this marketer.'], 422);
        }

        $customerId = auth('customer')->id();

        // One row per order: reuse only an acceptance not yet linked to an order.
        $existing = MarketerContractAcceptance::where('customer_id', $customerId)
            ->where('marketer_contract_version_id', $version->id)
            ->whereNull('order_id')
            ->first();

        if ($existing) {
            return response()->json([
                'acceptance_id' => $existing->id,
                'accepted_at' => $existing->accepted_at,
                'already_accepted' => true,
            ]);
        }

        $acceptance = MarketerContractAcceptance::create([
            'marketer_contract_version_id' => $version->id,
            'customer_id' => $customerId,
            'marketer_id' => $marketerId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accepted_at' => now(),
        ]);

        return response()->json([
            'acceptance_id' => $acceptance->id,
            'accepted_at' => $acceptance->accepted_at,
            'already_accepted' => false,
        ], 201);
    }
}
