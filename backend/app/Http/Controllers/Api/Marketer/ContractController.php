<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerContractAcceptance;
use App\Services\Marketer\MarketerProfileContractsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** enhancement.md P-16 task 2: API parity for Marketer\ContractController (web). */
class ContractController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    public function show(): JsonResponse
    {
        $marketer = $this->marketer();
        $contract = $marketer->contract()->with('activeVersion')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'contract' => $contract,
                'accepted' => $marketer->hasAcceptedContract(),
            ],
        ]);
    }

    /**
     * Collaboration contracts for the signed-in marketer: the ones that apply to him and the ones sellers signed with him.
     * Same filters as the web page (contract_status, contract_category, contract_country, contract_from, contract_to).
     */
    public function collaboration(Request $request, MarketerProfileContractsService $contracts): JsonResponse
    {
        $marketer = $this->marketer();
        $filters = $contracts->filtersFrom($request);

        return response()->json([
            'success' => true,
            'data' => [
                'applicable' => $contracts->applicable($marketer, $filters, $request)->through(fn ($t) => [
                    'name' => $t->name,
                    'version' => $t->version,
                    'country' => $t->country?->iso_code_2,
                    'classified_category' => $t->category ? ['name_ar' => $t->category->name_ar, 'name_en' => $t->category->name_en] : null,
                    'effective_at' => $t->effective_at?->toIso8601String(),
                ]),
                'signed' => $contracts->signed($marketer, $filters)->through(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->template?->name,
                    'version' => $c->template_version,
                    'seller' => $c->vendor?->store_name,
                    'status' => $c->status,
                    'signed_at' => $c->signed_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function accept(Request $request): JsonResponse
    {
        $marketer = $this->marketer();
        $contract = $marketer->contract()->with('activeVersion')->first();

        if (! $contract || ! $contract->activeVersion) {
            return response()->json(['success' => false, 'message' => 'No contract to accept.'], 422);
        }

        $acceptance = MarketerContractAcceptance::firstOrCreate(
            [
                'marketer_id' => $marketer->id,
                'customer_id' => null,
                'marketer_contract_version_id' => $contract->activeVersion->id,
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'accepted_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'data' => $acceptance]);
    }
}
