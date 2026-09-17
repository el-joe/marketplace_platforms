<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerContractAcceptance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * enhancement.md P-16: the marketer's own acceptance of their onboarding
 * contract with the platform. Distinct from
 * Api\Customer\MarketerContractController, which is a CUSTOMER accepting a
 * marketer's public influencer contract before buying from them.
 */
class ContractController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function show(): View
    {
        $marketer = $this->marketer();
        $contract = $marketer->contract()->with('activeVersion')->first();

        return view('marketer.contract.show', [
            'contract' => $contract,
            'accepted' => $marketer->hasAcceptedContract(),
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $marketer = $this->marketer();
        $contract = $marketer->contract()->with('activeVersion')->first();

        abort_unless($contract && $contract->activeVersion, 422, 'No contract to accept.');

        MarketerContractAcceptance::firstOrCreate(
            [
                'marketer_id' => $marketer->id,
                'marketer_contract_version_id' => $contract->activeVersion->id,
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'accepted_at' => now(),
            ]
        );

        return back()->with('success', 'Contract accepted.');
    }
}
