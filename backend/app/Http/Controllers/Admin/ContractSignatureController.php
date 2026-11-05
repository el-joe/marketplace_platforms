<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Country;
use App\Models\VendorContract;
use App\Services\NavigationService;
use App\Services\Vendor\ContractFileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every signature across vendors, for either category scope.
 */
class ContractSignatureController extends Controller
{
    public function __construct(private readonly ContractFileService $files) {}

    public function index(Request $request): View
    {
        $scope = in_array($request->query('scope'), ['classified', 'product', 'platform'], true) ? $request->query('scope') : null;
        $status = in_array($request->query('status'), ['active', 'superseded', 'revoked'], true) ? $request->query('status') : null;
        $search = trim((string) $request->query('q', ''));
        $countryFilter = $this->countryFilter($request);

        $contracts = VendorContract::query()
            ->with(['vendor:id,store_name', 'contractTemplate:id,name,country_id', 'classifiedCategory:id,name_en', 'productCategory:id,name_en', 'product:id,name_en', 'country:id,name_en,name_ar,iso_code_2'])
            ->when($scope, fn (Builder $query) => $query->where('category_scope', $scope))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($countryFilter === 'legacy', fn (Builder $query) => $query->whereNull('country_id'))
            ->when($countryFilter && $countryFilter !== 'legacy', fn (Builder $query) => $query->where('country_id', $countryFilter))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('vendor', fn (Builder $vendor) => $vendor->where('store_name', 'like', "%{$search}%")))
            ->orderByDesc('signed_at')
            ->paginate(30)
            ->withQueryString();

        $this->markSeen();

        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'iso_code_2']);

        return view('admin.contracts.signatures.index', compact('contracts', 'scope', 'status', 'search', 'countryFilter', 'countries'));
    }

    /**
     * Country filter value: a country id, 'legacy' (signed before contracts were country-aware) or null (all).
     */
    private function countryFilter(Request $request): ?string
    {
        $value = $request->query('country');

        if ($value === 'legacy') {
            return 'legacy';
        }

        return is_string($value) && Country::whereKey($value)->exists() ? $value : null;
    }

    /**
     * The exact document a vendor signed (uploaded agreements only; text contracts are shown in the page).
     */
    public function download(VendorContract $contract): StreamedResponse
    {
        return $this->files->respondToSigned($contract);
    }

    /**
     * Opening the list clears the sidebar badge for this admin.
     */
    private function markSeen(): void
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return;
        }

        // Update the authenticated model itself, not only the row: the layout renders the sidebar badge later in
        // this same request from this instance, and with the old value it re-cached the stale count right after
        // it was cleared, so the badge stayed on after the page was opened.
        $admin->forceFill(['contracts_seen_at' => now()])->saveQuietly();
        Cache::forget('nav.badge.'.NavigationService::contractSignaturesBadgeKey($admin->id));
    }
}
