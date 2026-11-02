<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
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

        $contracts = VendorContract::query()
            ->with(['vendor:id,store_name', 'contractTemplate:id,name', 'classifiedCategory:id,name_en', 'productCategory:id,name_en'])
            ->when($scope, fn (Builder $query) => $query->where('category_scope', $scope))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('vendor', fn (Builder $vendor) => $vendor->where('store_name', 'like', "%{$search}%")))
            ->orderByDesc('signed_at')
            ->paginate(30)
            ->withQueryString();

        $this->markSeen();

        return view('admin.contracts.signatures.index', compact('contracts', 'scope', 'status', 'search'));
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
        $adminId = auth('admin')->id();

        if (! $adminId) {
            return;
        }

        Admin::whereKey($adminId)->update(['contracts_seen_at' => now()]);
        Cache::forget('nav.badge.'.NavigationService::contractSignaturesBadgeKey($adminId));
    }
}
