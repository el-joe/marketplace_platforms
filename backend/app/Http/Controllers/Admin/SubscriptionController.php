<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorSubscriptionInvoiceStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionInvoice;
use App\Services\SubscriptionService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    use HasDataTable;

    public function __construct(private SubscriptionService $service)
    {
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PLANS — CRUD
    // ══════════════════════════════════════════════════════════════════════════

    public function plansIndex(): View
    {
        $plans = SubscriptionPlan::ordered()->get();

        return view('admin.subscriptions.plans.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Subscriptions', 'url' => route('admin.subscriptions.index')],
                ['label' => 'Plans'],
            ],
            'plans' => $plans,
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
            'max_listings' => 'nullable|integer|min:1',
            'free_shipping_included' => 'boolean',
            'commission_discount_pct' => 'required|numeric|min:0|max:100',
            'features' => 'nullable|array',
            'features.*' => 'string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $plan = SubscriptionPlan::create($data);

        return response()->json([
            'success' => true,
            'message' => "Plan \"{$plan->name_en}\" created.",
            'plan' => $plan,
        ]);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
            'max_listings' => 'nullable|integer|min:1',
            'free_shipping_included' => 'boolean',
            'commission_discount_pct' => 'required|numeric|min:0|max:100',
            'features' => 'nullable|array',
            'features.*' => 'string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $plan->update($data);

        return response()->json(['success' => true, 'message' => 'Plan updated.']);
    }

    public function togglePlanActive(SubscriptionPlan $plan): JsonResponse
    {
        $plan->update(['is_active' => !$plan->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Plan ' . ($plan->is_active ? 'activated' : 'deactivated') . '.',
            'is_active' => $plan->is_active,
        ]);
    }

    public function destroyPlan(SubscriptionPlan $plan): JsonResponse
    {
        if ($plan->subscriptions()->where('status', VendorSubscriptionStatus::Active->value)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a plan that has active subscriptions.',
            ], 422);
        }

        $plan->delete();

        return response()->json(['success' => true, 'message' => 'Plan deleted.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  VENDOR SUBSCRIPTIONS — LIST + MANAGEMENT
    // ══════════════════════════════════════════════════════════════════════════

    public function index(): View
    {
        $stats = [
            'total' => VendorSubscription::count(),
            'active' => VendorSubscription::where('status', VendorSubscriptionStatus::Active->value)->count(),
            'expired' => VendorSubscription::where('status', VendorSubscriptionStatus::Expired->value)->count(),
            'cancelled' => VendorSubscription::where('status', VendorSubscriptionStatus::Cancelled->value)->count(),
            'mrr' => VendorSubscription::with('plan')
                ->where('status', VendorSubscriptionStatus::Active->value)
                ->get()
                ->sum(fn($s) => $s->plan?->price ?? 0),
            'mrr_currency' => SubscriptionPlan::active()->value('currency') ?? '',
        ];

        $plans = SubscriptionPlan::active()->ordered()->get(['id', 'name_en', 'name_ar', 'price', 'currency']);

        return view('admin.subscriptions.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Subscriptions'],
            ],
            'stats' => $stats,
            'plans' => $plans,
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['searchable_columns' => ['vendors.store_name']],
            ['orderable_column' => 'subscription_plans.name_en'],
            ['orderable_column' => 'vendor_subscriptions.status'],
            ['orderable_column' => 'vendor_subscriptions.current_period_start'],
            ['orderable_column' => 'vendor_subscriptions.current_period_end'],
            ['orderable_column' => 'vendor_subscriptions.listings_used'],
            [], // actions
        ];

        $query = VendorSubscription::query()
            ->join('vendors', 'vendors.id', '=', 'vendor_subscriptions.vendor_id')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'vendor_subscriptions.plan_id')
            ->select('vendor_subscriptions.*', 'vendors.store_name', 'subscription_plans.name_en as plan_name_en', 'subscription_plans.max_listings');

        if ($filterStatus = $request->input('filter_status')) {
            $query->where('vendor_subscriptions.status', $filterStatus);
        }
        if ($filterPlan = $request->input('filter_plan')) {
            $query->where('vendor_subscriptions.plan_id', $filterPlan);
        }

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $statusColors = [
                VendorSubscriptionStatus::Active->value => 'success',
                VendorSubscriptionStatus::Cancelled->value => 'danger',
                VendorSubscriptionStatus::Expired->value => 'secondary',
                VendorSubscriptionStatus::PastDue->value => 'warning',
                VendorSubscriptionStatus::Trialing->value => 'primary',
            ];
            $sc = $statusColors[$row->status->value] ?? 'secondary';

            $listingsLabel = is_null($row->max_listings)
                ? $row->listings_used . ' / ∞'
                : $row->listings_used . ' / ' . $row->max_listings;

            $actions = '<div class="flex gap-1">'
                . '<a href="' . route('admin.subscriptions.show', $row->id) . '" class="btn btn-xs btn-ghost">View</a>';

            if ($row->status === VendorSubscriptionStatus::Active) {
                $actions .= '<button class="btn btn-xs btn-danger btn-cancel-sub" data-id="' . $row->id . '">Cancel</button>';
            }

            $actions .= '</div>';

            return [
                '<a href="' . route('admin.subscriptions.show', $row->id) . '" class="font-medium hover:underline">' . e($row->store_name) . '</a>',
                e($row->plan_name_en),
                '<span class="badge badge-' . $sc . '">' . $row->status->label() . '</span>',
                $row->current_period_start,
                $row->current_period_end,
                $listingsLabel,
                $actions,
            ];
        });
    }

    public function show(VendorSubscription $subscription): View
    {
        $subscription->load(['vendor', 'plan', 'invoices', 'approvedByAdmin']);

        return view('admin.subscriptions.show', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Subscriptions', 'url' => route('admin.subscriptions.index')],
                ['label' => $subscription->vendor->store_name],
            ],
            'subscription' => $subscription,
            'plans' => SubscriptionPlan::active()->ordered()->get(),
        ]);
    }

    public function subscribeVendor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $vendor = Vendor::findOrFail($data['vendor_id']);
        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $admin = Auth::guard('admin')->user();

        $subscription = $this->service->subscribe($vendor, $plan, $admin);

        return response()->json([
            'success' => true,
            'message' => "Vendor subscribed to \"{$plan->name_en}\" plan.",
        ]);
    }

    public function cancelSubscription(VendorSubscription $subscription, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->service->cancel($subscription, $data['reason'] ?? null);

        return response()->json(['success' => true, 'message' => 'Subscription cancelled.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  INVOICES
    // ══════════════════════════════════════════════════════════════════════════

    public function invoicesIndex(): View
    {
        $stats = [
            'total' => VendorSubscriptionInvoice::count(),
            'open' => VendorSubscriptionInvoice::where('status', 'open')->count(),
            'paid' => VendorSubscriptionInvoice::where('status', 'paid')->count(),
            'paid_sum' => VendorSubscriptionInvoice::where('status', 'paid')->sum('amount'),
            'open_sum' => VendorSubscriptionInvoice::where('status', 'open')->sum('amount'),
        ];

        return view('admin.subscriptions.invoices.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Subscriptions', 'url' => route('admin.subscriptions.index')],
                ['label' => 'Invoices'],
            ],
            'stats' => $stats,
        ]);
    }

    public function invoicesDatatable(Request $request): JsonResponse
    {
        $columns = [
            ['orderable_column' => 'vendor_subscription_invoices.invoice_number'],
            ['searchable_columns' => ['vendors.store_name']],
            ['orderable_column' => 'subscription_plans.name_en'],
            ['orderable_column' => 'vendor_subscription_invoices.amount'],
            ['orderable_column' => 'vendor_subscription_invoices.status'],
            ['orderable_column' => 'vendor_subscription_invoices.period_start'],
            ['orderable_column' => 'vendor_subscription_invoices.paid_at'],
            [], // actions
        ];

        $query = VendorSubscriptionInvoice::query()
            ->join('vendors', 'vendors.id', '=', 'vendor_subscription_invoices.vendor_id')
            ->join('vendor_subscriptions', 'vendor_subscriptions.id', '=', 'vendor_subscription_invoices.subscription_id')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'vendor_subscriptions.plan_id')
            ->select(
                'vendor_subscription_invoices.*',
                'vendors.store_name',
                'subscription_plans.name_en as plan_name_en'
            );

        if ($filterStatus = $request->input('filter_status')) {
            $query->where('vendor_subscription_invoices.status', $filterStatus);
        }

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $sc = match ($row->status) {
                VendorSubscriptionInvoiceStatus::Paid => 'success',
                VendorSubscriptionInvoiceStatus::Open => 'warning',
                VendorSubscriptionInvoiceStatus::Void => 'secondary',
                VendorSubscriptionInvoiceStatus::Uncollectible => 'danger',
                default => 'secondary',
            };

            $actions = '';
            if ($row->status === VendorSubscriptionInvoiceStatus::Open) {
                $actions = '<button class="btn btn-xs btn-success btn-mark-paid" data-id="' . $row->id . '">Mark Paid</button>';
            }

            return [
                '<span class="font-mono text-xs">' . e($row->invoice_number) . '</span>',
                e($row->store_name),
                e($row->plan_name_en),
                number_format($row->amount / 100, 2) . ' ' . $row->currency,
                '<span class="badge badge-' . $sc . '">' . ucfirst($row->status->value) . '</span>',
                $row->period_start,
                $row->paid_at ?? '—',
                $actions,
            ];
        });
    }

    public function markInvoicePaid(Request $request, VendorSubscriptionInvoice $invoice): JsonResponse
    {
        if ($invoice->status === VendorSubscriptionInvoiceStatus::Paid) {
            return response()->json(['success' => false, 'message' => 'Invoice already paid.'], 422);
        }

        $data = $request->validate([
            'payment_transaction_id' => 'nullable|string|max:100',
        ]);

        $this->service->markInvoicePaid($invoice, $data['payment_transaction_id'] ?? null);

        return response()->json(['success' => true, 'message' => 'Invoice marked as paid.']);
    }
}
