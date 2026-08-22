<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerStatus;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Notification;
use App\Models\WalletTransaction;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
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

        $countries = Country::orderBy('name_en')->get(['id', 'name_en']);

        return view('admin.customers.index', compact('stats', 'countries'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    private function buildCustomersQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Customer::query()->with('country');

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('customers.name', 'like', "%{$v}%")
                    ->orWhere('customers.email', 'like', "%{$v}%")
                    ->orWhere('customers.phone', 'like', "%{$v}%");
            }),
            'status' => fn($q, $v) => $q->where('status', $v),
            'country_id' => fn($q, $v) => $q->where('country_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('created_at', '<=', $v),
            'min_orders' => fn($q, $v) => $q->where('total_orders', '>=', (int) $v),
            'verified_only' => fn($q, $v) => $v ? $q->whereNotNull('email_verified_at') : $q,
        ]);
    }

    private function exportCustomers(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $customers = $this->buildCustomersQuery($request)->latest('created_at')->get();

        $headers = ['Name', 'Email', 'Phone', 'Country', 'Orders', 'Wallet Balance', 'Joined'];

        $rows = $customers->map(function (Customer $customer) {
            // Wallets may span multiple currencies — never sum across them.
            // Show each currency's balance separately, e.g. "120.00 EGP, 40.00 USD".
            $walletBalance = $customer->wallets()
                ->get()
                ->groupBy('currency')
                ->map(fn($wallets, $currency) => number_format($wallets->sum('balance') / 100, 2) . ' ' . $currency)
                ->implode(', ') ?: '—';

            return [
                $customer->name,
                $customer->email,
                $customer->phone ?? '—',
                $customer->country?->name_en ?? '—',
                $customer->total_orders,
                $walletBalance,
                $customer->created_at->format('d M Y'),
            ];
        });

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('customers', $headers, $rows),
            'csv' => $this->exportCsv('customers', $headers, $rows),
            'word' => $this->exportWord('customers', 'Customers', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.view'), 403);

        $query = $this->buildCustomersQuery($request);

        $columns = [
            ['searchable_columns' => ['customers.name'], 'orderable_column' => 'name'],
            ['searchable_columns' => ['customers.email'], 'orderable_column' => 'email'],
            ['searchable_columns' => ['customers.phone'], 'orderable_column' => 'phone'],
            ['searchable_columns' => [], 'orderable_column' => null],  // country
            ['searchable_columns' => [], 'orderable_column' => 'status'],
            ['searchable_columns' => [], 'orderable_column' => 'total_orders'],
            ['searchable_columns' => [], 'orderable_column' => 'total_spent'],
            ['searchable_columns' => [], 'orderable_column' => 'loyalty_points'],
            ['searchable_columns' => [], 'orderable_column' => 'created_at'],
            ['searchable_columns' => [], 'orderable_column' => null],  // actions
        ];

        $statusColors = [
            'active' => 'success',
            'suspended' => 'warning',
            'banned' => 'danger',
        ];

        return $this->dataTableResponse($request, $query, $columns, function (Customer $row) use ($statusColors) {
            $color = $statusColors[$row->status->value] ?? 'gray';
            $canEdit = auth('admin')->user()->hasPermissionTo('customers.edit');
            $canSuspend = auth('admin')->user()->hasPermissionTo('customers.suspend');

            return [
                'name' => e($row->name),
                'email' => e($row->email),
                'phone' => e($row->phone ?? '—'),
                'country' => $row->country
                    ? ($row->country->flag_emoji ? $row->country->flag_emoji . ' ' : '') . e($row->country->name_en)
                    : '—',
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-' . $color . '-100 text-' . $color . '-700">'
                    . e($row->status->label()) . '</span>',
                'total_orders' => $row->total_orders,
                'total_spent' => number_format((float) $row->total_spent, 2),
                'loyalty_points' => number_format((float) $row->loyalty_points, 2),
                'created_at' => $row->created_at->format('d M Y'),
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
        $actions .= '<a href="' . $viewUrl . '" class="btn btn-xs btn-secondary">View</a>';

        if ($canSuspend) {
            if ($customer->status === CustomerStatus::Active) {
                $actions .= '<button type="button" class="btn btn-xs btn-warning js-suspend-btn"'
                    . ' data-url="' . $suspendUrl . '" data-name="' . e($customer->name) . '">Suspend</button>';
                $actions .= '<button type="button" class="btn btn-xs btn-danger js-ban-btn"'
                    . ' data-url="' . $banUrl . '" data-name="' . e($customer->name) . '">Ban</button>';
            } elseif ($customer->status === CustomerStatus::Suspended) {
                $actions .= '<button type="button" class="btn btn-xs btn-success js-reactivate-btn"'
                    . ' data-url="' . $reactivateUrl . '" data-name="' . e($customer->name) . '">Reactivate</button>';
                $actions .= '<button type="button" class="btn btn-xs btn-danger js-ban-btn"'
                    . ' data-url="' . $banUrl . '" data-name="' . e($customer->name) . '">Ban</button>';
            } elseif ($customer->status === CustomerStatus::Banned) {
                $actions .= '<button type="button" class="btn btn-xs btn-success js-reactivate-btn"'
                    . ' data-url="' . $reactivateUrl . '" data-name="' . e($customer->name) . '">Reactivate</button>';
            }
        }

        $actions .= '</div>';
        return $actions;
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Customer $customer): \Illuminate\View\View
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

        $customerWallet = $customer->customerWallet;

        $walletStats = [
            'balance' => $customerWallet->balance ?? 0,
            'currency_code' => $customerWallet->currency_code ?? null,
            'total_credited' => WalletTransaction::forCustomer($customer->id)->credits()->sum('amount'),
            'total_debited' => WalletTransaction::forCustomer($customer->id)->debits()->sum('amount'),
            'transaction_count' => WalletTransaction::forCustomer($customer->id)->count(),
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
            'referralStats',   // add this
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
        ]);

        $customer->update($validated);

        return response()->json(['message' => 'Customer updated.']);
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
                'message' => 'Your account has been suspended. Reason: ' . $request->input('reason'),
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
                'total' => number_format((float) $row->total, 2) . ' ' . strtoupper($row->currency ?? ''),
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-' . $color . '-100 text-' . $color . '-700">'
                    . ucwords(str_replace('_', ' ', $row->status)) . '</span>',
                'items' => '—',
                'placed_at' => $row->placed_at ? \Carbon\Carbon::parse($row->placed_at)->format('d M Y') : '—',
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
            'expiry_deduction' => 'gray',
        ];

        return $this->dataTableResponse($request, $query, $columns, function (WalletTransaction $row) use ($typeColors) {
            $color = $typeColors[$row->type] ?? 'gray';

            return [
                'created_at' => $row->created_at->format('d M Y, H:i'),
                'type' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-' . $color . '-100 text-' . $color . '-700">'
                    . e(ucwords(str_replace('_', ' ', $row->type))) . '</span>',
                'direction' => $row->direction === 'credit'
                    ? '<span class="text-success-600">&uarr;</span>'
                    : '<span class="text-danger-600">&darr;</span>',
                'amount' => number_format($row->amount) . ' ' . strtoupper($row->currency_code ?? ''),
                'balance_after' => number_format($row->balance_after) . ' ' . strtoupper($row->currency_code ?? ''),
                'reference' => $row->reference_type
                    ? e(class_basename($row->reference_type)) . ' #' . e((string) $row->reference_id)
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
                'active'    => 'success',
                'suspended' => 'warning',
                'banned'    => 'danger',
            ];

            return [
                '<a href="' . route('admin.customers.show', $row->id) . '" class="text-primary-600 hover:underline font-medium">' . e($row->name) . '</a>',
                e($row->email),
                number_format($row->total_orders),
                number_format((float) $row->loyalty_points, 2),
                $row->created_at?->format('d M Y'),
            ];
        });
    }

    // ─── Adjust Wallet (Manual Credit/Debit) ──────────────────────────────────

    public function adjustWallet(Request $request, Customer $customer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('wallet.manual_adjust'), 403);

        $validated = $request->validate([
            'direction' => 'required|in:credit,debit',
            'amount' => 'required|integer|min:1',
            'note' => 'required|string|max:1000',
        ]);

        $direction = $validated['direction'];
        $amount = (int) $validated['amount'];
        $note = $validated['note'];

        try {
            $newBalance = DB::transaction(function () use ($customer, $direction, $amount, $note, $admin) {
                $wallet = CustomerWallet::query()
                    ->where('customer_id', $customer->id)
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    $wallet = CustomerWallet::create([
                        'customer_id' => $customer->id,
                        'balance' => 0,
                        'currency_code' => 'EGP',
                    ]);
                }

                if ($direction === 'debit') {
                    $wallet->debit($amount);
                } else {
                    $wallet->credit($amount);
                }

                WalletTransaction::create([
                    'customer_id' => $customer->id,
                    'type' => $direction === 'debit' ? 'admin_debit' : 'admin_credit',
                    'direction' => $direction,
                    'amount' => $amount,
                    'balance_after' => $wallet->balance,
                    'currency_code' => $wallet->currency_code,
                    'note' => $note,
                ]);

                Activity::create([
                    'log_name' => 'customers',
                    'description' => 'Wallet manually ' . ($direction === 'debit' ? 'debited' : 'credited') . ' by admin',
                    'subject_type' => Customer::class,
                    'subject_id' => $customer->id,
                    'causer_type' => get_class($admin),
                    'causer_id' => $admin->id,
                    'event' => 'wallet_' . $direction,
                    'properties' => json_encode([
                        'amount' => $amount,
                        'direction' => $direction,
                        'note' => $note,
                        'new_balance' => $wallet->balance,
                    ]),
                    'ip_address' => $request->ip(),
                ]);

                return $wallet->balance;
            });
        } catch (InsufficientWalletBalanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'new_balance' => $newBalance]);
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
            'orders' => $orders->map(fn($o) => [
                'order_number' => $o->order_number,
                'status' => $o->status->value,
                'total' => (float) $o->total,
                'currency' => $o->currency,
                'payment_method' => $o->payment_method,
                'payment_status' => $o->payment_status->value,
                'placed_at' => $o->placed_at ? \Carbon\Carbon::parse($o->placed_at)->toIso8601String() : null,
            ])->values(),
            'reviews' => $reviews->map(fn($r) => [
                'product' => $r->product?->name ?? $r->product_id,
                'rating' => $r->rating,
                'title' => $r->title,
                'status' => $r->status->value,
                'created_at' => $r->created_at->toIso8601String(),
            ])->values(),
        ];

        $filename = 'customer_export_' . $customer->id . '_' . now()->format('Ymd_His') . '.json';

        return response(json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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

    public function regenerateQrCode(Customer $customer, \App\Services\CustomerQrCodeService $service): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('customers.edit'), 403);

        $qrUrl = $service->generate($customer);

        return response()->json(['qr_url' => $qrUrl]);
    }
}
