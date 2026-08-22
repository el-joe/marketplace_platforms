<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustGiftCardBalanceRequest;
use App\Http\Requests\Admin\StoreBatchRequest;
use App\Http\Requests\Admin\UpdateBatchRequest;
use App\Jobs\SendGiftCardDeliveryJob;
use App\Models\GiftCard;
use App\Models\GiftCardBatch;
use App\Models\GiftCardPurchase;
use App\Models\GiftCardTransaction;
use App\Services\GiftCardService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminGiftCardController extends Controller
{
    use HasDataTable;

    public function __construct(private readonly GiftCardService $giftCardService)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Card index (all cards, across batches)
    // ─────────────────────────────────────────────────────────────────────────

    public function cardIndex(): View
    {
        // NEVER sum across currencies — gift card balances are currency-scoped.
        $stats = GiftCard::selectRaw(
            "currency_code, COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'redeemed' THEN 1 ELSE 0 END) as redeemed_count,
            SUM(CASE WHEN purchased_by_customer_id IS NOT NULL THEN 1 ELSE 0 END) as purchased_count,
            SUM(amount) as total_value"
        )->groupBy('currency_code')->get();

        // Revenue is scoped to purchases (amount actually paid) — also never summed across currencies.
        $revenueByCurrency = GiftCardPurchase::selectRaw('currency_code, SUM(amount_paid) as total_revenue')
            ->groupBy('currency_code')
            ->pluck('total_revenue', 'currency_code');

        foreach ($stats as $stat) {
            $stat->total_revenue = (int) ($revenueByCurrency[$stat->currency_code] ?? 0);
        }

        $recentBatches = GiftCardBatch::with('createdByAdmin:id,name')->latest()->take(5)->get();

        return view('admin.gift-cards.index', [
            'stats' => $stats,
            'recentBatches' => $recentBatches,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.gift_cards_section.title')],
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = $this->cardColumnDefinitions();

        $query = GiftCard::query()
            ->select([
                'gift_cards.id',
                'gift_cards.code',
                'gift_cards.amount',
                'gift_cards.remaining_balance',
                'gift_cards.currency_code',
                'gift_cards.status',
                'gift_cards.gift_card_batch_id',
                'gift_cards.redeemed_by_customer_id',
                'gift_cards.expires_at',
                'gift_cards.created_at',
            ])
            ->with('redeemedBy:id,name');

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('gift_cards.status', $v),
            'currency_code' => fn($q, $v) => $q->where('gift_cards.currency_code', $v),
            'batch_id' => fn($q, $v) => $q->where('gift_cards.gift_card_batch_id', $v),
            'search' => fn($q, $v) => $q->where('gift_cards.code', 'like', "%{$v}%"),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'code' => e($row->code),
                'code_masked' => e($row->masked_code),
                'amount' => (int) $row->amount,
                'remaining_balance' => (int) $row->remaining_balance,
                'currency_code' => $row->currency_code,
                'status' => $row->status,
                'redeemed_by' => $row->redeemedBy?->name,
                'expires_at' => $row->expires_at,
                'created_at' => $row->created_at,
                'show_url' => route('admin.gift-cards.show', $row->id),
                'batch_url' => $row->gift_card_batch_id
                    ? route('admin.gift-cards.batches.show', $row->gift_card_batch_id)
                    : null,
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch index
    // ─────────────────────────────────────────────────────────────────────────

    public function batchIndex(): View
    {
        $batches = GiftCardBatch::query()
            ->withCount([
                'giftCards as active_count' => fn($q) => $q->where('status', 'active'),
                'giftCards as redeemed_count' => fn($q) => $q->where('status', 'redeemed'),
            ])
            ->with('createdByAdmin:id,name')
            ->latest('created_at')
            ->paginate(25);

        $stats = [
            'total_batches' => GiftCardBatch::count(),
            'total_issued' => GiftCard::count(),
            'total_redeemed' => GiftCard::where('status', 'redeemed')->count(),
            'total_active' => GiftCard::where('status', 'active')->count(),
        ];

        return view('admin.gift-cards.batches.index', [
            'batches' => $batches,
            'stats' => $stats,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.gift_cards_section.batches_title')],
            ],
        ]);
    }

    public function batchDatatable(GiftCardBatch $batch, Request $request): JsonResponse
    {
        $columns = $this->batchCardColumnDefinitions();

        $query = GiftCard::query()
            ->select([
                'gift_cards.id',
                'gift_cards.code',
                'gift_cards.amount',
                'gift_cards.currency_code',
                'gift_cards.status',
                'gift_cards.redeemed_by_customer_id',
                'gift_cards.redeemed_at',
                'gift_cards.expires_at',
                'gift_cards.created_at',
            ])
            ->with('redeemedBy:id,name')
            ->where('gift_card_batch_id', $batch->id);

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('gift_cards.status', $v),
            'search' => fn($q, $v) => $q->where('gift_cards.code', 'like', "%{$v}%"),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'code' => e($row->masked_code),
                'amount' => (int) $row->amount,
                'currency_code' => $row->currency_code,
                'status' => $row->status,
                'redeemed_by' => $row->redeemedBy?->name,
                'redeemed_at' => $row->redeemed_at,
                'expires_at' => $row->expires_at,
                'show_url' => route('admin.gift-cards.show', $row->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch create / store
    // ─────────────────────────────────────────────────────────────────────────

    public function batchCreate(): View
    {
        return view('admin.gift-cards.batches.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.gift_cards_section.batches_title'), 'url' => route('admin.gift-cards.batches.index')],
                ['label' => __('admin.gift_cards_section.new_batch')],
            ],
        ]);
    }

    public function batchStore(StoreBatchRequest $request): RedirectResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $result = $this->giftCardService->generateBatch($request->validated(), $admin);
        $batch = $result['batch'];
        $pins = $result['plain_pins'];

        // Flash plain PINs as CSV for one-time download — never stored anywhere.
        $csvLines = ['code,pin'];
        $cards = $batch->giftCards()->orderBy('created_at')->get();
        foreach ($cards as $i => $card) {
            $csvLines[] = $card->code.','.$pins[$i];
        }
        session()->flash('pins_csv', implode("\n", $csvLines));
        session()->flash('pins_filename', 'gift_cards_'.$batch->id.'.csv');

        return redirect()
            ->route('admin.gift-cards.batches.show', $batch->id)
            ->with('success', __('admin.gift_cards_section.batch_created'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch show
    // ─────────────────────────────────────────────────────────────────────────

    public function batchShow(GiftCardBatch $batch): View
    {
        $batch->loadCount([
            'giftCards as active_count' => fn($q) => $q->where('status', 'active'),
            'giftCards as inactive_count' => fn($q) => $q->where('status', 'inactive'),
            'giftCards as redeemed_count' => fn($q) => $q->where('status', 'redeemed'),
            'giftCards as expired_count' => fn($q) => $q->where('status', 'expired'),
        ]);
        $batch->load('createdByAdmin:id,name');

        $purchaseStats = [
            'total_purchased' => GiftCardPurchase::where('gift_card_batch_id', $batch->id)->count(),
            'total_revenue' => GiftCardPurchase::where('gift_card_batch_id', $batch->id)->sum('amount_paid'),
            'pending_delivery' => GiftCardPurchase::where('gift_card_batch_id', $batch->id)
                ->where('delivery_status', 'pending')->count(),
        ];

        return view('admin.gift-cards.batches.show', [
            'batch' => $batch,
            'purchaseStats' => $purchaseStats,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.gift_cards_section.batches_title'), 'url' => route('admin.gift-cards.batches.index')],
                ['label' => e($batch->name)],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch edit / update
    // ─────────────────────────────────────────────────────────────────────────

    public function batchEdit(GiftCardBatch $batch): View
    {
        return view('admin.gift-cards.batches.edit', [
            'batch' => $batch,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.gift_cards_section.batches_title'), 'url' => route('admin.gift-cards.batches.index')],
                ['label' => e($batch->name), 'url' => route('admin.gift-cards.batches.show', $batch->id)],
                ['label' => __('admin.gift_cards_section.edit_batch')],
            ],
        ]);
    }

    public function batchUpdate(UpdateBatchRequest $request, GiftCardBatch $batch): RedirectResponse
    {
        $batch->update($request->validated());

        return redirect()
            ->route('admin.gift-cards.batches.show', $batch->id)
            ->with('success', __('admin.gift_cards_section.batch_updated'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Purchases (storefront) — datatable + resend delivery
    // ─────────────────────────────────────────────────────────────────────────

    public function purchasesDatatable(GiftCardBatch $batch, Request $request): JsonResponse
    {
        $columns = $this->purchaseColumnDefinitions();

        $query = GiftCardPurchase::query()
            ->select([
                'gift_card_purchases.id',
                'gift_card_purchases.amount_paid',
                'gift_card_purchases.currency_code',
                'gift_card_purchases.is_gift',
                'gift_card_purchases.recipient_email',
                'gift_card_purchases.delivery_status',
                'gift_card_purchases.delivered_at',
                'gift_card_purchases.order_id',
                'gift_card_purchases.buyer_customer_id',
                'gift_card_purchases.created_at',
            ])
            ->with(['buyer:id,name,email', 'order:id,order_number'])
            ->where('gift_card_batch_id', $batch->id);

        $query = $this->applyFilters($query, $request, [
            'delivery_status' => fn($q, $v) => $q->where('gift_card_purchases.delivery_status', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'buyer_name' => $row->buyer?->name,
                'buyer_email' => $row->buyer?->email,
                'amount_paid' => (int) $row->amount_paid,
                'currency_code' => $row->currency_code,
                'is_gift' => $row->is_gift,
                'recipient_email' => $row->recipient_email,
                'delivery_status' => $row->delivery_status,
                'delivered_at' => $row->delivered_at,
                'order_number' => $row->order?->order_number,
                'resend_url' => route('admin.gift-cards.purchases.resend', $row->id),
            ];
        });
    }

    public function resendDelivery(GiftCardPurchase $purchase): JsonResponse
    {
        SendGiftCardDeliveryJob::dispatch($purchase->id);

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch PIN download (one-time, session-flashed CSV only)
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadPins(GiftCardBatch $batch): \Symfony\Component\HttpFoundation\Response
    {
        $csv = session('pins_csv');
        $filename = session('pins_filename', 'gift_cards_'.$batch->id.'.csv');

        abort_unless($csv !== null, 404);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch activate
    // ─────────────────────────────────────────────────────────────────────────

    public function activateBatch(GiftCardBatch $batch): JsonResponse
    {
        $activated = GiftCard::where('gift_card_batch_id', $batch->id)
            ->where('status', 'inactive')
            ->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'activated_count' => $activated,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Card show / activate / adjust / block
    // ─────────────────────────────────────────────────────────────────────────

    public function showCard(GiftCard $card): View
    {
        // CONFIRM: pin_hash is in $hidden on the model — never passes to the view.
        $card->load([
            'batch',
            'redeemedBy',
            'issuedTo',
            'transactions.performedByAdmin',
            'transactions.performedByCustomer',
            'transactions.order:id,order_number',
        ]);

        return view('admin.gift-cards.cards.show', [
            'card' => $card,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.gift_cards_section.title'), 'url' => route('admin.gift-cards.index')],
                ['label' => $card->masked_code],
            ],
        ]);
    }

    public function activateCard(GiftCard $card): JsonResponse
    {
        $card->update(['status' => 'active']);

        return response()->json(['success' => true]);
    }

    public function adjustBalance(AdjustGiftCardBalanceRequest $request, GiftCard $card): RedirectResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $this->giftCardService->adminAdjust($card, $request->validated('delta'), $request->validated('notes'), $admin);

        return back()->with('success', __('admin.gift_cards_section.balance_adjusted'));
    }

    public function blockCard(GiftCard $card): RedirectResponse
    {
        $card->update(['status' => 'inactive']);

        GiftCardTransaction::create([
            'gift_card_id' => $card->id,
            'amount' => 0,
            'balance_after' => $card->remaining_balance,
            'type' => 'admin_adjustment',
            'performed_by_admin_id' => Auth::guard('admin')->id(),
            'notes' => __('admin.gift_cards_section.admin_blocked_card_note'),
        ]);

        return back()->with('success', __('admin.gift_cards_section.card_blocked'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Expire stale cards
    // ─────────────────────────────────────────────────────────────────────────

    public function expireStale(): JsonResponse
    {
        $expired = GiftCard::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        return response()->json([
            'success' => true,
            'expired_count' => $expired,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────────

    private function cardColumnDefinitions(): array
    {
        return [
            ['title' => 'Code', 'data' => 'code', 'name' => 'code', 'orderable_column' => 'gift_cards.code', 'searchable_columns' => ['gift_cards.code']],
            ['title' => 'Amount', 'data' => 'amount', 'name' => 'amount', 'orderable_column' => 'gift_cards.amount', 'searchable' => false],
            ['title' => 'Remaining', 'data' => 'remaining_balance', 'name' => 'remaining_balance', 'orderable_column' => 'gift_cards.remaining_balance', 'searchable' => false],
            ['title' => 'Currency', 'data' => 'currency_code', 'name' => 'currency_code', 'orderable_column' => 'gift_cards.currency_code', 'searchable' => false],
            ['title' => 'Status', 'data' => 'status', 'name' => 'status', 'orderable_column' => 'gift_cards.status', 'searchable' => false],
            ['title' => 'Redeemed By', 'data' => 'redeemed_by', 'name' => 'redeemed_by', 'orderable' => false, 'searchable' => false],
            ['title' => 'Expiry', 'data' => 'expires_at', 'name' => 'expires_at', 'orderable_column' => 'gift_cards.expires_at', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false],
        ];
    }

    private function batchCardColumnDefinitions(): array
    {
        return [
            ['title' => 'Code', 'data' => 'code', 'name' => 'code', 'orderable_column' => 'gift_cards.code', 'searchable_columns' => ['gift_cards.code']],
            ['title' => 'Amount', 'data' => 'amount', 'name' => 'amount', 'orderable_column' => 'gift_cards.amount', 'searchable' => false],
            ['title' => 'Status', 'data' => 'status', 'name' => 'status', 'orderable_column' => 'gift_cards.status', 'searchable' => false],
            ['title' => 'Redeemed By', 'data' => 'redeemed_by', 'name' => 'redeemed_by', 'orderable' => false, 'searchable' => false],
            ['title' => 'Redeemed At', 'data' => 'redeemed_at', 'name' => 'redeemed_at', 'orderable_column' => 'gift_cards.redeemed_at', 'searchable' => false],
            ['title' => 'Expiry', 'data' => 'expires_at', 'name' => 'expires_at', 'orderable_column' => 'gift_cards.expires_at', 'searchable' => false],
        ];
    }

    private function purchaseColumnDefinitions(): array
    {
        return [
            ['title' => 'Buyer Name', 'data' => 'buyer_name', 'name' => 'buyer_name', 'orderable' => false, 'searchable' => false],
            ['title' => 'Buyer Email', 'data' => 'buyer_email', 'name' => 'buyer_email', 'orderable' => false, 'searchable' => false],
            ['title' => 'Amount Paid', 'data' => 'amount_paid', 'name' => 'amount_paid', 'orderable_column' => 'gift_card_purchases.amount_paid', 'searchable' => false],
            ['title' => 'Currency', 'data' => 'currency_code', 'name' => 'currency_code', 'orderable_column' => 'gift_card_purchases.currency_code', 'searchable' => false],
            ['title' => 'Is Gift', 'data' => 'is_gift', 'name' => 'is_gift', 'orderable_column' => 'gift_card_purchases.is_gift', 'searchable' => false],
            ['title' => 'Recipient Email', 'data' => 'recipient_email', 'name' => 'recipient_email', 'orderable' => false, 'searchable' => false],
            ['title' => 'Delivery Status', 'data' => 'delivery_status', 'name' => 'delivery_status', 'orderable_column' => 'gift_card_purchases.delivery_status', 'searchable' => false],
            ['title' => 'Delivered At', 'data' => 'delivered_at', 'name' => 'delivered_at', 'orderable_column' => 'gift_card_purchases.delivered_at', 'searchable' => false],
            ['title' => 'Order #', 'data' => 'order_number', 'name' => 'order_number', 'orderable' => false, 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false],
        ];
    }
}
