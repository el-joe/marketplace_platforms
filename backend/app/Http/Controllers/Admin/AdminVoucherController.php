<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\VoucherService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminVoucherController extends Controller
{
    use HasExport, HasDataTable;

    public function __construct(private readonly VoucherService $voucherService)
    {
    }

    public function index(): View
    {
        // Per-currency stats — NEVER sum across currencies.
        $stats = Voucher::selectRaw(
            'currency_code,
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 AND valid_until >= NOW() THEN 1 ELSE 0 END) as active_count,
            SUM(times_redeemed) as total_redeemed,
            SUM(amount * times_redeemed) as total_credited'
        )->groupBy('currency_code')->get();

        return view('admin.vouchers.index', [
            'stats' => $stats,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.vouchers_section.title')],
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['searchable_columns' => ['vouchers.code'], 'orderable_column' => 'vouchers.code'],
            ['searchable_columns' => ['vouchers.name'], 'orderable_column' => 'vouchers.name'],
            ['orderable_column' => 'vouchers.amount'],
            ['orderable_column' => 'vouchers.currency_code'],
            [],
            ['orderable_column' => 'vouchers.valid_from'],
            ['orderable_column' => 'vouchers.times_redeemed'],
            ['orderable_column' => 'vouchers.is_active'],
            [],
        ];

        $query = Voucher::query()
            ->leftJoin('countries', 'countries.id', '=', 'vouchers.country_id')
            ->select([
                'vouchers.*',
                'countries.name_en as country_name',
            ]);

        $query = $this->applyFilters($query, $request, [
            'is_active' => fn($q, $v) => $q->where('vouchers.is_active', (bool) $v),
            'currency_code' => fn($q, $v) => $q->where('vouchers.currency_code', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function (Voucher $row) {
            return [
                'code' => $row->code,
                'name' => $row->name,
                'amount' => number_format($row->amount, 2),
                'currency_code' => $row->currency_code,
                'country_name' => $row->country_name,
                'valid_from' => optional($row->valid_from)->toIso8601String(),
                'valid_until' => optional($row->valid_until)->toIso8601String(),
                'times_redeemed' => $row->times_redeemed,
                'usage_limit_total' => $row->usage_limit_total,
                'is_active' => $row->is_active,
                'id' => $row->id,
                'show_url' => route('admin.vouchers.show', $row->id),
                'edit_url' => route('admin.vouchers.edit', $row->id),
                'toggle_url' => route('admin.vouchers.toggle', $row->id),
                'delete_url' => route('admin.vouchers.destroy', $row->id),
            ];
        });
    }

    public function create(): View
    {
        $countries = Country::select('id', 'name_en', 'currency_code')->orderBy('name_en')->get();

        return view('admin.vouchers.create', [
            'countries' => $countries,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.vouchers_section.title'), 'url' => route('admin.vouchers.index')],
                ['label' => __('admin.vouchers_section.new')],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->voucherRules());

        $voucher = Voucher::create([
            ...$validated,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return redirect()
            ->route('admin.vouchers.show', $voucher->id)
            ->with('success', __('admin.vouchers_section.created'));
    }

    public function bulkGenerate(Request $request): JsonResponse
    {
        $rules = $this->voucherRules();
        unset($rules['code']);
        $rules['quantity'] = 'required|integer|min:1|max:50000';
        $rules['code_prefix'] = 'nullable|string|max:10|regex:/^[A-Z0-9\-]*$/i';

        $validated = $request->validate($rules);

        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $result = $this->voucherService->bulkGenerate($validated, $admin);

        return response()->json([
            'success' => true,
            'count' => $result['count'],
            'sample_codes' => $result['sample_codes'],
            'message' => __('admin.vouchers_section.generated_success', ['count' => $result['count']]),
        ]);
    }

    public function show(string $voucher): View
    {
        $voucher = Voucher::findOrFail($voucher);
        $voucher->load(['country', 'createdByAdmin']);

        $redemptionStats = [
            'total_redeemed' => $voucher->times_redeemed,
            'unique_customers' => VoucherRedemption::where('voucher_id', $voucher->id)->distinct('customer_id')->count(),
            'total_credited' => $voucher->amount * $voucher->times_redeemed,
            'currency_code' => $voucher->currency_code,
        ];

        return view('admin.vouchers.show', [
            'voucher' => $voucher,
            'redemptionStats' => $redemptionStats,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.vouchers_section.title'), 'url' => route('admin.vouchers.index')],
                ['label' => e($voucher->code)],
            ],
        ]);
    }

    public function redemptionsDatatable(Request $request, string $voucher): JsonResponse
    {
        $voucher = Voucher::findOrFail($voucher);

        $columns = [
            [],
            [],
            ['orderable_column' => 'amount'],
            ['orderable_column' => 'currency_code'],
            ['orderable_column' => 'wallet_balance_after'],
            ['orderable_column' => 'redeemed_at'],
        ];

        $query = VoucherRedemption::where('voucher_id', $voucher->id)->with('customer');

        return $this->dataTableResponse($request, $query, $columns, function (VoucherRedemption $redemption) {
            return [
                'customer_name' => $redemption->customer?->name,
                'customer_email' => $redemption->customer?->email,
                'amount' => number_format($redemption->amount, 2),
                'currency_code' => $redemption->currency_code,
                'wallet_balance_after' => number_format($redemption->wallet_balance_after, 2),
                'redeemed_at' => optional($redemption->redeemed_at)->toIso8601String(),
            ];
        });
    }

    public function exportRedemptions(string $voucher): StreamedResponse
    {
        $voucher = Voucher::findOrFail($voucher);

        $redemptions = VoucherRedemption::where('voucher_id', $voucher->id)
            ->with('customer')
            ->get();

        $headers = ['customer_name', 'customer_email', 'amount', 'currency_code', 'wallet_balance_after', 'redeemed_at'];

        $rows = $redemptions->map(fn ($redemption) => [
            $redemption->customer?->name,
            $redemption->customer?->email,
            $redemption->amount,
            $redemption->currency_code,
            $redemption->wallet_balance_after,
            $redemption->redeemed_at,
        ]);

        return $this->exportCsv("voucher_redemptions_{$voucher->code}", $headers, $rows);
    }

    public function edit(string $voucher): View
    {
        $voucher = Voucher::findOrFail($voucher);
        $countries = Country::select('id', 'name_en', 'currency_code')->orderBy('name_en')->get();

        return view('admin.vouchers.edit', [
            'voucher' => $voucher,
            'countries' => $countries,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.vouchers_section.title'), 'url' => route('admin.vouchers.index')],
                ['label' => e($voucher->code)],
            ],
        ]);
    }

    public function update(Request $request, string $voucher): RedirectResponse
    {
        $voucher = Voucher::findOrFail($voucher);

        $rules = $this->voucherRules();
        $rules['code'] = 'required|string|max:50|unique:vouchers,code,'.$voucher->id;

        $validated = $request->validate($rules);

        $voucher->update($validated);

        return redirect()
            ->route('admin.vouchers.show', $voucher->id)
            ->with('success', __('admin.vouchers_section.updated'));
    }

    public function toggle(string $voucher): JsonResponse
    {
        $voucher = Voucher::findOrFail($voucher);

        $voucher->is_active = ! $voucher->is_active;
        $voucher->save();

        return response()->json(['success' => true, 'is_active' => $voucher->is_active]);
    }

    public function destroy(string $voucher): RedirectResponse
    {
        $voucher = Voucher::findOrFail($voucher);

        if (VoucherRedemption::where('voucher_id', $voucher->id)->exists()) {
            return back()->with('error', __('admin.vouchers_section.cannot_delete_redeemed'));
        }

        $voucher->delete();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', __('admin.vouchers_section.deleted'));
    }

    private function voucherRules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:vouchers,code',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'amount' => 'required|integer|min:1',
            'currency_code' => 'required|in:SAR,AED,EGP,KWD,OMR,QAR,BHD,JOD',
            'country_id' => 'nullable|uuid',
            'customer_eligibility' => 'required|in:all,new_customers,specific_users',
            'eligible_customer_ids' => 'nullable|array',
            'eligible_customer_ids.*' => 'uuid',
            'usage_limit_total' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'required|integer|min:1',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'is_active' => 'boolean',
        ];
    }
}
