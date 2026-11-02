<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedContractTemplate;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractFileService;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public terms page: the Seller Agreement currently in force, and the next version when one is scheduled.
 */
class LegalController extends Controller
{
    public function __construct(
        private readonly SellerAgreementService $agreements,
        private readonly CategoryContractService $contracts,
        private readonly ContractFileService $files,
    ) {}

    public function sellerAgreement(): View
    {
        $inForce = $this->agreements->inForce();
        $upcoming = $this->agreements->upcoming();

        return view('portal.legal.seller-agreement', [
            'inForce' => $inForce,
            'upcoming' => $upcoming,
            'inForceDoc' => $inForce ? $this->contracts->presentPublic($inForce) : null,
            'upcomingDoc' => $upcoming ? $this->contracts->presentPublic($upcoming) : null,
        ]);
    }

    /**
     * Only published Seller Agreement documents are public.
     */
    public function sellerAgreementFile(Request $request, string $template, string $language): StreamedResponse
    {
        $template = ClassifiedContractTemplate::where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('is_published', true)
            ->findOrFail($template);

        $file = ($template->files ?? [])[$language] ?? null;
        abort_unless($file, 404);

        return $this->files->respond($file, $request->boolean('download'));
    }
}
