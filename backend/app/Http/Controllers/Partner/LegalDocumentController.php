<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedContractTemplate;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorContract;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractFileService;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings → Legal Documents: the Seller Agreement in force with the vendor's signed copy, versions still
 * inside a notice period, and the click-wrap screen a vendor must pass when a new version needs acceptance.
 */
class LegalDocumentController extends Controller
{
    public function __construct(
        private readonly SellerAgreementService $agreements,
        private readonly CategoryContractService $contracts,
        private readonly ContractFileService $files,
    ) {}

    private function vendorAdmin(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    private function vendor(): Vendor
    {
        return $this->vendorAdmin()->vendor;
    }

    public function index(): View
    {
        $vendor = $this->vendor();

        $signature = $this->agreements->activeSignature($vendor);
        $inForce = $this->agreements->inForce();
        $upcoming = $this->agreements->upcoming();
        $pending = $this->agreements->pendingFor($vendor);

        $history = VendorContract::where('vendor_id', $vendor->id)
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->with('contractTemplate:id,name')
            ->orderByDesc('signed_at')
            ->get();

        return view('partner.legal.index', [
            'signature' => $signature,
            'inForce' => $inForce,
            'upcoming' => $upcoming,
            'pending' => $pending,
            'history' => $history,
            'inForceDoc' => $inForce ? $this->contracts->present($inForce, $vendor, null) : null,
            'upcomingDoc' => $upcoming ? $this->contracts->present($upcoming, $vendor, null) : null,
        ]);
    }

    /**
     * The click-wrap screen. Without a pending version there is nothing to accept.
     */
    public function agreement(): View|RedirectResponse
    {
        $vendor = $this->vendor();
        $template = $this->agreements->pendingFor($vendor);

        if (! $template) {
            return redirect()->route('partner.legal.index');
        }

        return view('partner.legal.agreement', [
            'template' => $template,
            'doc' => $this->contracts->present($template, $vendor, null),
            'isUpdate' => $this->agreements->activeSignature($vendor) !== null,
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signature_name' => 'required|string|max:150',
            'language' => 'required|in:en,ar',
            'agreed' => 'accepted',
        ]);

        $vendor = $this->vendor();
        $template = $this->agreements->pendingFor($vendor);

        if (! $template) {
            return redirect()->route('partner.legal.index');
        }

        $contract = $this->agreements->sign(
            vendor: $vendor,
            signer: $this->vendorAdmin(),
            template: $template,
            language: $validated['language'],
            signerName: $validated['signature_name'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent() ?? '',
        );

        $this->agreements->sendSignedCopy($contract);

        return redirect(session()->pull('url.intended', route('partner.legal.index')))
            ->with('success', __('partner.legal.accepted'));
    }

    /**
     * Published template documents only; drafts are never visible to vendors.
     */
    public function templateFile(Request $request, string $template, string $language): StreamedResponse
    {
        $template = ClassifiedContractTemplate::where('is_published', true)->findOrFail($template);
        $file = ($template->files ?? [])[$language] ?? null;
        abort_unless($file, 404);

        return $this->files->respond($file, $request->boolean('download'));
    }

    /**
     * The exact document this vendor signed.
     */
    public function signedFile(VendorContract $contract): StreamedResponse
    {
        abort_unless($contract->vendor_id === $this->vendor()->id, 404);

        return $this->files->respondToSigned($contract);
    }
}
