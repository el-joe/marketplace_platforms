<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerStatus;
use App\Enums\Gender;
use App\Enums\WalletOwnerType;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\WalletTransaction;
use App\Services\Customer\CustomerActivityMetrics;
use App\Services\CustomerQrCodeService;
use App\Services\WalletService;
use App\Support\Money;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    use HasDataTable;
    use HasExport;

    /** wallet_transactions.source_type of a manual admin adjustment (same as Admin\WalletController). */
    private const WALLET_ADJUSTMENT_SOURCE = 'admin_adjustment';

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View|StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.view'), 403);

        if ($request->filled('export')) {
            return $this->exportCustomers($request);
        }

        $stats = [
            'total' => Customer::count(),
            'active' => Customer::where('status', CustomerStatus::Active)->count(),
            'suspended' => Customer::where('status', CustomerStatus::Suspended)->count(),
            'banned' => Customer::where('status', CustomerStatus::Banned)->count(),
            'new_this_week' => Customer::where('created_at', '>=', now()->startOfWeek())->count(),
        ];

        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'name_ar']);

        return view('admin.customers.index', compact('stats', 'countries'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    /** Gender filter value that matches customers who did not specify one. */
    private const GENDER_UNSPECIFIED = 'unspecified';

    /**
     * Filters shared by the DataTable and the export.
     *
     * "Joined between" (date_from/date_to) filters customers.created_at; "Activity between"
     * (activity_from/activity_to) only limits which orders feed the orders/spend/points columns.
     *
     * @return array{0: Builder, 1: CustomerActivityMetrics}
     */
    private function buildCustomersQuery(Request $request): array
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'activity_from' => ['nullable', 'date'],
            'activity_to' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in([...array_column(Gender::cases(), 'value'), self::GENDER_UNSPECIFIED])],
            'min_orders' => ['nullable', 'integer', 'min:0'],
        ]);

        $metrics = new CustomerActivityMetrics(
            $request->filled('activity_from') ? admin_day_start($request->input('activity_from')) : null,
            $request->filled('activity_to') ? admin_day_end($request->input('activity_to')) : null,
        );

        $query = $metrics->addMetrics(Customer::query()->select('customers.*')->with('country'));

        $query = $this->applyFilters($query, $request, [
            'search' => fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('customers.name', 'like', "%{$v}%")
                    ->orWhere('customers.email', 'like', "%{$v}%")
                    ->orWhere('customers.phone', 'like', "%{$v}%");
            }),
            'status' => fn ($q, $v) => $q->where('customers.status', $v),
            'country_id' => fn ($q, $v) => $q->where('customers.country_id', $v),
            'gender' => fn ($q, $v) => $v === self::GENDER_UNSPECIFIED
                ? $q->whereNull('customers.gender')
                : $q->where('customers.gender', $v),
            'date_from' => fn ($q, $v) => $q->where('customers.created_at', '>=', admin_day_start($v)),
            'date_to' => fn ($q, $v) => $q->where('customers.created_at', '<=', admin_day_end($v)),
            'min_orders' => fn ($q, $v) => (int) $v > 0 ? $metrics->whereOrderCountAtLeast($q, (int) $v) : $q,
            'verified_only' => fn ($q, $v) => $v ? $q->whereNotNull('customers.email_verified_at') : $q,
        ]);

        return [$query, $metrics];
    }

    /** Points column: earned on orders in the activity window, otherwise the current balance. */
    private function pointsFor(Customer $customer, CustomerActivityMetrics $metrics): float
    {
        return (float) ($metrics->hasWindow() ? $customer->activity_points_earned : $customer->loyalty_points);
    }

    private function genderLabel(Customer $customer): string
    {
        return $customer->gender?->label() ?? __('admin.customers_section.gender_unspecified');
    }

    private function exportCustomers(Request $request): StreamedResponse
    {
        [$query, $metrics] = $this->buildCustomersQuery($request);
        $customers = $query->with('wallets')->latest('customers.created_at')->get();
        $spend = $metrics->spendByCurrency($customers->pluck('id')->all());

        $headers = [
            __('admin.customers_section.name_column'),
            __('admin.customers_section.email_column'),
            __('admin.customers_section.phone_column'),
            __('admin.customers_section.country_column'),
            __('admin.customers_section.gender_column'),
            __('admin.customers_section.orders_column'),
            __('admin.customers_section.spent_column'),
            __('admin.customers_section.points_column'),
            __('admin.customers_section.wallet_balance'),
            __('admin.customers_section.joined_column'),
        ];

        $rows = $customers->map(function (Customer $customer) use ($metrics, $spend) {
            // Wallets may span multiple currencies — never sum across them.
            // Show each currency's balance separately, e.g. "120.00 EGP, 40.00 USD".
            $walletBalance = $customer->wallets
                ->groupBy('currency')
                ->map(fn ($wallets, $currency) => number_format($wallets->sum('balance'), 2).' '.$currency)
                ->implode(', ') ?: '—';

            return [
                $customer->name,
                $customer->email,
                $customer->phone ?? '—',
                $customer->country ? localized($customer->country) : '—',
                $this->genderLabel($customer),
                (int) $customer->activity_orders_count,
                CustomerActivityMetrics::formatSpend($spend[$customer->id] ?? []),
                number_format($this->pointsFor($customer, $metrics), 2),
                $walletBalance,
                admin_tz($customer->created_at)?->format('d M Y'),
            ];
        });

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('customers', $headers, $rows),
            'csv' => $this->exportCsv('customers', $headers, $rows),
            'word' => $this->exportWord('customers', __('admin.customers_section.title'), $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.view'), 403);

        [$query, $metrics] = $this->buildCustomersQuery($request);

        // Indexes match the JS columns (index 0 is the row checkbox).
        $columns = [
            ['searchable_columns' => [], 'orderable_column' => null],  // checkbox
            ['searchable_columns' => ['customers.name'], 'orderable_column' => 'customers.name'],
            ['searchable_columns' => ['customers.email'], 'orderable_column' => 'customers.email'],
            ['searchable_columns' => ['customers.phone'], 'orderable_column' => 'customers.phone'],
            ['searchable_columns' => [], 'orderable_column' => null],  // country
            ['searchable_columns' => [], 'orderable_column' => 'customers.gender'],
            ['searchable_columns' => [], 'orderable_column' => 'customers.status'],
            ['searchable_columns' => [], 'orderable_column' => 'activity_orders_count'],
            ['searchable_columns' => [], 'orderable_column' => 'activity_spend_usd'],
            ['searchable_columns' => [], 'orderable_column' => $metrics->hasWindow() ? 'activity_points_earned' : 'customers.loyalty_points'],
            ['searchable_columns' => [], 'orderable_column' => 'customers.created_at'],
            ['searchable_columns' => [], 'orderable_column' => null],  // actions
        ];

        $statusColors = [
            'active' => 'success',
            'suspended' => 'warning',
            'banned' => 'danger',
        ];

        $canEdit = $admin->hasPermissionTo('customers.edit');
        $canSuspend = $admin->hasPermissionTo('customers.suspend');
        $spend = [];

        // One grouped per-currency spend query for the displayed page only.
        $query->afterQuery(function ($customers) use ($metrics, &$spend) {
            $spend = $metrics->spendByCurrency($customers->pluck('id')->all());
        });

        return $this->dataTableResponse($request, $query, $columns, function (Customer $row) use ($statusColors, $canEdit, $canSuspend, $metrics, &$spend) {
            $color = $statusColors[$row->status->value] ?? 'gray';

            return [
                'name' => e($row->name),
                'email' => e($row->email),
                'phone' => e($row->phone ?? '—'),
                'country' => $row->country
                    ? ($row->country->flag_emoji ? $row->country->flag_emoji.' ' : '').e(localized($row->country))
                    : '—',
                'gender' => e($this->genderLabel($row)),
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-'.$color.'-100 text-'.$color.'-700">'
                    .e($row->status->label()).'</span>',
                'total_orders' => (int) $row->activity_orders_count,
                'total_spent' => e(CustomerActivityMetrics::formatSpend($spend[$row->id] ?? [])),
                'loyalty_points' => number_format($this->pointsFor($row, $metrics), 2),
                'created_at' => admin_tz($row->created_at)?->format('d M Y'),
                'actions' => $this->buildRowActions($row, $canEdit, $canSuspend),
                'DT_RowData' => [
                    'id' => $row->id,
                    'status' => $row->status->value,
                ],
            ];
        });
    }

    private function buildRowActions(Customer $customer, bool $canEdit, bool $canSuspend): string
    {
        $viewUrl = route('admin.customers.show', $customer->id);
        $suspendUrl = route('admin.customers.suspend', $customer->id);
        $banUrl = route('admin.customers.ban', $customer->id);
        $reactivateUrl = route('admin.customers.reactivate', $customer->id);

        $actions = '<div class="flex items-center gap-1">';
        $actions .= '<a href="'.$viewUrl.'" class="btn btn-xs btn-secondary">'.e(__('common.view')).'</a>';

        if ($canSuspend) {
            if ($customer->status === CustomerStatus::Active) {
                $actions .= '<button type="button" class="btn btn-xs btn-warning js-suspend-btn"'
                    .' data-url="'.$suspendUrl.'" data-name="'.e($customer->name).'">'.e(__('admin.suspend')).'</button>';
                $actions .= '<button type="button" class="btn btn-xs btn-danger js-ban-btn"'
                    .' data-url="'.$banUrl.'" data-name="'.e($customer->name).'">'.e(__('admin.ban')).'</button>';
            } elseif ($customer->status === CustomerStatus::Suspended) {
                $actions .= '<button type="button" class="btn btn-xs btn-success js-reactivate-btn"'
                    .' data-url="'.$reactivateUrl.'" data-name="'.e($customer->name).'">'.e(__('admin.reactivate')).'</button>';
                $actions .= '<button type="button" class="btn btn-xs btn-danger js-ban-btn"'
                    .' data-url="'.$banUrl.'" data-name="'.e($customer->name).'">'.e(__('admin.ban')).'</button>';
            } elseif ($customer->status === CustomerStatus::Banned) {
                $actions .= '<button type="button" class="btn btn-xs btn-success js-reactivate-btn"'
                    .' data-url="'.$reactivateUrl.'" data-name="'.e($customer->name).'">'.e(__('admin.reactivate')).'</button>';
            }
        }

        $actions .= '</div>';

        return $actions;
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Customer $customer): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.view'), 403);

        $customer->load([
            'country',
            'referredBy',
            'addresses.country',
            'referrals',   // eager-load referred customers for count in sidebar
        ]);

        $orders = $customer->orders()->latest('placed_at')->take(20)->get();
        $reviews = $customer->reviews()->with('product')->latest()->take(20)->get();
        $paymentMethods = collect(); // payment_methods table removed — customers pay per-order via gateways
        $returnRequests = $customer->returnRequests()->latest()->take(20)->get();
        $disputes = $customer->disputes()->latest()->take(20)->get();
        $tickets = $customer->supportTickets()
            ->latest()
            ->take(20)
            ->get();

        $activityLog = Activity::where('causer_type', Customer::class)
            ->where('causer_id', $customer->id)
            ->latest()
            ->take(50)
            ->get();

        $wallets = $customer->wallets()->get();

        // The spendable balance lives on the canonical Wallet (owner_type=customer) in the customer's currency;
        // the legacy customer_wallets table is no longer written to.
        $walletCurrency = $customer->resolveCurrency();
        $primaryWallet = $wallets->firstWhere('currency', $walletCurrency);

        $walletStats = [
            'balance' => $primaryWallet->balance ?? 0,
            'currency_code' => $primaryWallet->currency ?? $walletCurrency,
            'total_credited' => WalletTransaction::forCustomer($customer->id)->credits()->sum('amount'),
            'total_debited' => WalletTransaction::forCustomer($customer->id)->debits()->sum('amount'),
            'transaction_count' => WalletTransaction::forCustomer($customer->id)->count(),
        ];

        $activityMetrics = new CustomerActivityMetrics;
        $customerMetrics = $activityMetrics->addMetrics(Customer::query()->select('customers.*')->whereKey($customer->id))->first();
        $customerStats = [
            'orders' => (int) $customerMetrics?->activity_orders_count,
            'spend' => CustomerActivityMetrics::formatSpend($activityMetrics->spendByCurrency([$customer->id])[$customer->id] ?? []),
        ];

        $referralStats = [
            'total_referred' => $customer->referrals->count(),
            'total_referred_orders' => $customer->referrals->sum('total_orders'),
        ];

        $warrantyClaims = $customer->warrantyClaims()
            ->with('product')
            ->latest()
            ->take(20)
            ->get();

        $giftCards = $customer->purchasedGiftCards()
            ->latest()
            ->take(20)
            ->get();

        $notifications = $customer->notifications()
            ->latest('sent_at')
            ->take(20)
            ->get();

        $deviceTokens = $customer->deviceTokens()
            ->where('is_active', true)
            ->latest('last_used_at')
            ->get();

        return view('admin.customers.show', compact(
            'customer',
            'orders',
            'reviews',
            'paymentMethods',
            'returnRequests',
            'disputes',
            'tickets',
            'activityLog',
            'wallets',
            'walletStats',
            'warrantyClaims',
            'giftCards',
            'notifications',
            'deviceTokens',
            'referralStats',
            'customerStats',
        ));
    }

    // ─── Revoke All Devices ─────────────────────────────────────────────────────

    public function revokeAllDevices(Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.edit'), 403);

        $count = $customer->deviceTokens()->where('is_active', true)->update(['is_active' => false]);

        Activity::create([
            'log_name' => 'customers',
            'description' => 'All devices logged out',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'causer_type' => get_class($admin),
            'causer_id' => $admin->id,
            'event' => 'devices_revoked',
            'properties' => json_encode(['revoked_count' => $count]),
            'ip_address' => request()->ip(),
        ]);

        return response()->json(['message' => 'All devices logged out.', 'revoked_count' => $count]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.edit'), 403);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'loyalty_points' => 'sometimes|numeric|min:0',
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],
        ]);

        $customer->update($validated);

        return response()->json(['message' => __('admin.customers_section.customer_updated')]);
    }

    // ─── Suspend ──────────────────────────────────────────────────────────────

    public function suspend(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.suspend'), 403);

        $request->validate(['reason' => 'required|string|max:1000']);

        $customer->update(['status' => CustomerStatus::Suspended]);

        Notification::create([
            'notifiable_type' => Customer::class,
            'notifiable_id' => $customer->id,
            'type' => 'account_suspended',
            'channel' => 'database',
            'data' => json_encode([
                'title' => 'Account Suspended',
                'message' => 'Your account has been suspended. Reason: '.$request->input('reason'),
            ]),
            'sent_at' => now(),
        ]);

        Activity::create([
            'log_name' => 'customers',
            'description' => 'Customer suspended',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'causer_type' => get_class($admin),
            'causer_id' => $admin->id,
            'event' => 'suspended',
            'properties' => json_encode(['reason' => $request->input('reason')]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Customer suspended.']);
    }

    // ─── Ban ──────────────────────────────────────────────────────────────────

    public function ban(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.suspend'), 403);

        $request->validate(['reason' => 'required|string|max:1000']);

        $customer->update(['status' => CustomerStatus::Banned]);

        // Revoke Sanctum tokens if the model has the HasApiTokens trait
        if (method_exists($customer, 'tokens')) {
            $customer->tokens()->delete();
        }

        Activity::create([
            'log_name' => 'customers',
            'description' => 'Customer banned',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'causer_type' => get_class($admin),
            'causer_id' => $admin->id,
            'event' => 'banned',
            'properties' => json_encode(['reason' => $request->input('reason')]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Customer banned.']);
    }

    // ─── Reactivate ───────────────────────────────────────────────────────────

    public function reactivate(Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.suspend'), 403);

        $customer->update(['status' => CustomerStatus::Active]);

        Activity::create([
            'log_name' => 'customers',
            'description' => 'Customer reactivated',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'causer_type' => get_class($admin),
            'causer_id' => $admin->id,
            'event' => 'reactivated',
            'properties' => json_encode([]),
            'ip_address' => request()->ip(),
        ]);

        return response()->json(['message' => 'Customer reactivated.']);
    }

    // ─── Adjust Loyalty Points ────────────────────────────────────────────────

    public function adjustLoyaltyPoints(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.edit'), 403);

        $request->validate([
            'adjustment' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:500',
        ]);

        $adjustment = (int) $request->input('adjustment');

        if ($adjustment > 0) {
            $customer->increment('loyalty_points', $adjustment);
        } else {
            $customer->decrement('loyalty_points', abs($adjustment));
        }

        $customer->refresh();

        Activity::create([
            'log_name' => 'customers',
            'description' => 'Loyalty points adjusted',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'causer_type' => get_class($admin),
            'causer_id' => $admin->id,
            'event' => 'loyalty_adjusted',
            'properties' => json_encode([
                'adjustment' => $adjustment,
                'reason' => $request->input('reason'),
                'new_balance' => (float) $customer->loyalty_points,
            ]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Loyalty points adjusted.',
            'new_balance' => number_format((float) $customer->loyalty_points, 2),
        ]);
    }

    // ─── Orders DataTable ─────────────────────────────────────────────────────

    public function orders(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.view'), 403);

        $query = $customer->orders()->getQuery();

        $columns = [
            ['searchable_columns' => ['order_number'], 'orderable_column' => 'order_number'],
            ['searchable_columns' => [], 'orderable_column' => 'total'],
            ['searchable_columns' => [], 'orderable_column' => 'status'],
            ['searchable_columns' => [], 'orderable_column' => null],  // items
            ['searchable_columns' => [], 'orderable_column' => 'placed_at'],
        ];

        $statusColors = [
            'pending' => 'gray',
            'confirmed' => 'primary',
            'processing' => 'primary',
            'shipped' => 'primary',
            'out_for_delivery' => 'warning',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            'return_requested' => 'warning',
            'returned' => 'danger',
            'refunded' => 'danger',
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($row) use ($statusColors) {
            $color = $statusColors[$row->status] ?? 'gray';

            return [
                'order_number' => e($row->order_number),
                'total' => number_format((float) $row->total, 2).' '.strtoupper($row->currency ?? ''),
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-'.$color.'-100 text-'.$color.'-700">'
                    .ucwords(str_replace('_', ' ', $row->status)).'</span>',
                'items' => '—',
                'placed_at' => $row->placed_at ? admin_tz(Carbon::parse($row->placed_at))->format('d M Y') : '—',
            ];
        });
    }

    // ─── Wallet Transactions DataTable ────────────────────────────────────────

    public function walletTransactions(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.view'), 403);

        $query = WalletTransaction::query()->forCustomer($customer->id);

        $columns = [
            ['searchable_columns' => [], 'orderable_column' => 'created_at'],
            ['searchable_columns' => ['type'], 'orderable_column' => 'type'],
            ['searchable_columns' => [], 'orderable_column' => 'direction'],
            ['searchable_columns' => [], 'orderable_column' => 'amount'],
            ['searchable_columns' => [], 'orderable_column' => 'balance_after'],
            ['searchable_columns' => ['reference_type', 'reference_id'], 'orderable_column' => null],
            ['searchable_columns' => ['note'], 'orderable_column' => null],
        ];

        $typeColors = [
            'gift_card_redeem' => 'warning',
            'order_payment' => 'danger',
            'order_refund' => 'success',
            'admin_credit' => 'primary',
            'admin_debit' => 'warning',
            'credit' => 'success',
            'debit' => 'danger',
            'expiry_deduction' => 'gray',
        ];

        return $this->dataTableResponse($request, $query, $columns, function (WalletTransaction $row) use ($typeColors) {
            $color = $typeColors[$row->type] ?? 'gray';

            return [
                'created_at' => admin_tz($row->created_at)?->format('d M Y, H:i'),
                'type' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-'.$color.'-100 text-'.$color.'-700">'
                    .e(ucwords(str_replace('_', ' ', $row->type))).'</span>',
                'direction' => $row->direction === 'credit'
                    ? '<span class="text-success-600">&uarr;</span>'
                    : '<span class="text-danger-600">&darr;</span>',
                'amount' => Money::format($row->amount).' '.strtoupper($row->currency_code ?? ''),
                'balance_after' => Money::format($row->balance_after).' '.strtoupper($row->currency_code ?? ''),
                'reference' => $row->reference_type
                    ? e(class_basename($row->reference_type)).' #'.e((string) $row->reference_id)
                    : '—',
                'note' => e($row->note ?? '—'),
            ];
        });
    }

    // ─── Referrals datatable ────────────────────────────────────────────────────

    public function referralsDatatable(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.view'), 403);

        $columns = [
            ['orderable_column' => 'name'],
            ['orderable_column' => 'email'],
            ['orderable_column' => 'total_orders'],
            ['orderable_column' => 'loyalty_points'],
            ['orderable_column' => 'created_at'],
        ];

        $query = Customer::where('referred_by', $customer->id)
            ->select(['id', 'name', 'email', 'status', 'total_orders', 'loyalty_points', 'created_at']);

        return $this->dataTableResponse($request, $query, $columns, function (Customer $row) {
            $statusColors = [
                'active' => 'success',
                'suspended' => 'warning',
                'banned' => 'danger',
            ];

            return [
                '<a href="'.route('admin.customers.show', $row->id).'" class="text-primary-600 hover:underline font-medium">'.e($row->name).'</a>',
                e($row->email),
                number_format($row->total_orders),
                number_format((float) $row->loyalty_points, 2),
                admin_tz($row->created_at)?->format('d M Y'),
            ];
        });
    }

    // ─── Adjust Wallet (Manual Credit/Debit) ──────────────────────────────────

    /**
     * Manual credit/debit of the customer's canonical (spendable) wallet. The balance change, the
     * ledger row and the audit entry are written in one transaction, so a failure leaves nothing behind.
     */
    public function adjustWallet(Request $request, Customer $customer, WalletService $walletService): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('wallet.manual_adjust'), 403);

        $validated = $request->validate([
            'direction' => 'required|in:credit,debit',
            'amount' => 'required|numeric|decimal:0,2|min:1',
            'note' => 'required|string|max:255',
        ]);

        $direction = $validated['direction'];
        $amount = Money::number($validated['amount']);
        $note = $validated['note'];

        $currency = $customer->resolveCurrency();
        if (! $currency) {
            return response()->json(['success' => false, 'message' => __('admin.customers_section.wallet_no_currency')], 422);
        }

        $transaction = DB::transaction(function () use ($walletService, $customer, $currency, $direction, $amount, $note, $admin, $request) {
            $wallet = $walletService->getOrCreateWallet(WalletOwnerType::Customer->value, $customer->id, $currency);

            if ($wallet->is_frozen) {
                throw ValidationException::withMessages([
                    'amount' => __('common.exceptions.wallet.frozen', ['reason' => $wallet->frozen_reason ?? '—']),
                ]);
            }

            $attributes = [
                'customer_id' => $customer->id,
                'direction' => $direction,
                'currency_code' => $currency,
                'note' => $note,
            ];

            try {
                $transaction = $direction === 'debit'
                    ? $walletService->debit($wallet, $amount, self::WALLET_ADJUSTMENT_SOURCE, null, $note, $admin->id, $attributes)
                    : $walletService->credit($wallet, $amount, self::WALLET_ADJUSTMENT_SOURCE, null, $note, $admin->id, $attributes);
            } catch (InsufficientBalanceException) {
                throw ValidationException::withMessages([
                    'amount' => __('common.exceptions.wallet.insufficient_balance'),
                ]);
            }

            Activity::create([
                'log_name' => 'customers',
                'description' => 'Wallet manually '.($direction === 'debit' ? 'debited' : 'credited').' by admin',
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'causer_type' => get_class($admin),
                'causer_id' => $admin->id,
                'event' => 'wallet_'.$direction,
                'properties' => json_encode([
                    'wallet_id' => $wallet->id,
                    'wallet_transaction_id' => $transaction->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'direction' => $direction,
                    'note' => $note,
                    'new_balance' => $transaction->balance_after,
                ]),
                'ip_address' => $request->ip(),
            ]);

            return $transaction;
        });

        return response()->json([
            'success' => true,
            'message' => __('admin.customers_section.wallet_adjusted'),
            'new_balance' => $transaction->balance_after,
            'currency_code' => $currency,
        ]);
    }

    // ─── Export Data (GDPR) ───────────────────────────────────────────────────

    public function exportData(Customer $customer): Response
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.view'), 403);

        $orders = $customer->orders()->latest('placed_at')->take(100)->get();
        $reviews = $customer->reviews()->with('product')->latest()->take(100)->get();

        $export = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
                'phone' => $customer->phone,
                'phone_verified_at' => $customer->phone_verified_at?->toIso8601String(),
                'country_id' => $customer->country_id,
                'status' => $customer->status->value,
                'date_of_birth' => $customer->date_of_birth,
                'last_login_at' => $customer->last_login_at?->toIso8601String(),
                'last_login_ip' => $customer->last_login_ip,
                'total_orders' => $customer->total_orders,
                'total_spent' => (float) $customer->total_spent,
                'loyalty_points' => (float) $customer->loyalty_points,
                'referral_code' => $customer->referral_code,
                'created_at' => $customer->created_at->toIso8601String(),
            ],
            'orders' => $orders->map(fn ($o) => [
                'order_number' => $o->order_number,
                'status' => $o->status->value,
                'total' => (float) $o->total,
                'currency' => $o->currency,
                'payment_method' => $o->payment_method,
                'payment_status' => $o->payment_status->value,
                'placed_at' => $o->placed_at ? Carbon::parse($o->placed_at)->toIso8601String() : null,
            ])->values(),
            'reviews' => $reviews->map(fn ($r) => [
                'product' => $r->product?->name ?? $r->product_id,
                'rating' => $r->rating,
                'title' => $r->title,
                'status' => $r->status->value,
                'created_at' => $r->created_at->toIso8601String(),
            ])->values(),
        ];

        $filename = 'customer_export_'.$customer->id.'_'.now()->format('Ymd_His').'.json';

        return response(json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Send Notification ────────────────────────────────────────────────────

    public function sendNotification(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.edit'), 403);

        $request->validate([
            'channel' => 'required|in:database,email,sms',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        Notification::create([
            'notifiable_type' => Customer::class,
            'notifiable_id' => $customer->id,
            'type' => 'admin_message',
            'channel' => $request->input('channel'),
            'data' => json_encode([
                'title' => $request->input('title'),
                'message' => $request->input('message'),
            ]),
            'sent_at' => now(),
        ]);

        return response()->json(['message' => 'Notification sent.']);
    }

    // ─── QR Code ──────────────────────────────────────────────────────────────

    public function regenerateQrCode(Customer $customer, CustomerQrCodeService $service): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.edit'), 403);

        $qrUrl = $service->generate($customer);

        return response()->json(['qr_url' => $qrUrl]);
    }
}
