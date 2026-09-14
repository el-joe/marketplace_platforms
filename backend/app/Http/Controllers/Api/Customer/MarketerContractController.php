<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\MarketerContract;
use App\Models\MarketerContractAcceptance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MarketerContractController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $marketerId = $request->route('marketer');

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
        $marketerId = $request->route('marketer');

        $contract = MarketerContract::with('activeVersion')
            ->where('marketer_id', $marketerId)
            ->firstOrFail();

        $version = $contract->activeVersion;
        abort_if(! $version || ! $version->file_url || $version->content_type !== 'pdf', 404);

        return Storage::disk('local')->response($version->file_url);
    }

    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version_id' => ['required', 'uuid', 'exists:marketer_contract_versions,id'],
        ]);

        $customerId = auth('customer')->id();

        $existing = MarketerContractAcceptance::where('customer_id', $customerId)
            ->where('marketer_contract_version_id', $validated['version_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'acceptance_id' => $existing->id,
                'accepted_at' => $existing->accepted_at,
                'already_accepted' => true,
            ]);
        }

        $acceptance = MarketerContractAcceptance::create([
            'marketer_contract_version_id' => $validated['version_id'],
            'customer_id' => $customerId,
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
