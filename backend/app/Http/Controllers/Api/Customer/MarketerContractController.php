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
    public function show(string $marketerId): JsonResponse
    {
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
                    ? Storage::temporaryUrl($version->file_url, now()->addMinutes(30))
                    : null,
                'text_content' => $version->content_type === 'text' ? $version->text_content : null,
                'is_required' => $contract->is_required,
            ],
        ]);
    }

    public function accept(Request $request, string $marketerId): JsonResponse
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
