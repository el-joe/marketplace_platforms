<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every signature across vendors, for either category scope.
 */
class ContractSignatureController extends Controller
{
    public function index(Request $request): View
    {
        $scope = in_array($request->query('scope'), ['classified', 'product'], true) ? $request->query('scope') : null;
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

        return view('admin.contracts.signatures.index', compact('contracts', 'scope', 'status', 'search'));
    }
}
