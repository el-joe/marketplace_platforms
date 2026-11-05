<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerContractAcceptance;
use App\Models\MarketerContractVersion;
use App\Models\MarketerSellerContract;
use App\Services\Marketer\MarketerProfileContractsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * "My contracts": the marketer's own onboarding contract, the collaboration contracts that apply to him and the ones
     * sellers signed with him. Everything is scoped to the signed-in marketer; signer IP / user agent are never shown.
     */
    public function index(Request $request, MarketerProfileContractsService $contracts): View
    {
        $marketer = $this->marketer();
        $filters = $contracts->filtersFrom($request);

        return view('marketer.contract.index', [
            'filters' => $filters,
            'onboarding' => $contracts->onboarding($marketer, $filters),
            'applicable' => $contracts->applicable($marketer, $filters, $request),
            'signed' => $contracts->signed($marketer, $filters),
            'exclusive' => $contracts->exclusive($marketer, $filters),
        ]);
    }

    public function collaboration(string $contract): View
    {
        $contract = MarketerSellerContract::where('marketer_id', $this->marketer()->id)
            ->with(['vendor:id,store_name', 'template:id,name', 'campaign:id,title'])
            ->findOrFail($contract);

        return view('marketer.contract.collaboration', compact('contract'));
    }

    public function collaborationFile(string $contract): StreamedResponse
    {
        $contract = MarketerSellerContract::where('marketer_id', $this->marketer()->id)->findOrFail($contract);
        abort_unless($contract->hasSignedFile() && Storage::disk('private')->exists($contract->signed_file_path), 404);

        return Storage::disk('private')->download($contract->signed_file_path, $contract->signed_file_name, ['Content-Type' => $contract->signed_file_mime ?? 'application/octet-stream']);
    }

    public function download(string $version): StreamedResponse
    {
        $version = MarketerContractVersion::whereHas('contract', fn ($q) => $q->where('marketer_id', $this->marketer()->id))->findOrFail($version);
        abort_if(! $version->file_url || $version->content_type !== 'pdf', 404);

        return Storage::disk('local')->download($version->file_url);
    }

    public function accept(Request $request): RedirectResponse
    {
        $marketer = $this->marketer();
        $contract = $marketer->contract()->with('activeVersion')->first();

        abort_unless($contract && $contract->activeVersion, 422, __('marketer.msg.no_contract'));

        MarketerContractAcceptance::firstOrCreate(
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

        return back()->with('success', __('marketer.msg.contract_accepted'));
    }
}
