<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedContractTemplate;
use App\Models\Country;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractFileService;
use App\Services\Vendor\ContractResolver;
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
        private readonly ContractResolver $resolver,
    ) {}

    /**
     * The agreement of one country: `?country=` (iso code, site code or id), otherwise the portal country the
     * visitor is on. A country without an agreement shows a clear notice instead of another country's contract.
     */
    public function sellerAgreement(Request $request): View
    {
        $country = $this->requestedCountry($request);
        $inForce = $this->agreements->inForce($country?->id);
        $upcoming = $this->agreements->upcoming($country?->id);

        return view('portal.legal.seller-agreement', [
            'country' => $country,
            'countries' => Country::portalSelectable()->orderBy('name_en')->get(),
            'inForce' => $inForce,
            'upcoming' => $upcoming,
            'notConfigured' => ! $inForce && ! $upcoming && $this->resolver->anyPlatformAgreementPublished(),
            'inForceDoc' => $inForce ? $this->contracts->presentPublic($inForce) : null,
            'upcomingDoc' => $upcoming ? $this->contracts->presentPublic($upcoming) : null,
        ]);
    }

    private function requestedCountry(Request $request): ?Country
    {
        $requested = trim((string) $request->query('country', ''));

        if ($requested !== '') {
            $country = Country::query()->where('is_active', true)
                ->where(fn ($query) => $query->where('site_code', $requested)->orWhere('iso_code_2', strtoupper($requested))->orWhere('id', $requested))
                ->first();

            if ($country) {
                return $country;
            }
        }

        $siteCode = Country::resolveSiteCode(null);

        return $siteCode ? Country::where('site_code', $siteCode)->first() : null;
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
