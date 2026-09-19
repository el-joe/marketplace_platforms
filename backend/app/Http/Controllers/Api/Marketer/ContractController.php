<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerContractAcceptance;
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
