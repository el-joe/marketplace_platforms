<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    /**
     * Show both own-listing sub-orders and campaign conversion orders.
     */
    public function index(Request $request): View
    {
        $marketer = $this->marketer();

        // ── Tab 1: Own listing orders ──────────────────────────────────────────
        $ownOrders = SubOrder::where('marketer_id', $marketer->id)
            ->where('seller_type', 'marketer')
            ->with([
                'order:id,order_number,status,currency,total,payment_status,placed_at,shipping_address_snapshot,country_id',
                'order.country:id,name_ar,name_en',
                'items:id,sub_order_id,product_snapshot,quantity,unit_price,line_total',
            ])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20, ['*'], 'own_page')
            ->withQueryString();

        // ── Tab 2: Campaign conversion orders ─────────────────────────────────
        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->pluck('id');

        $campaignOrders = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->whereNotNull('order_id')
            ->with([
                'order:id,order_number,status,currency,total,payment_status,placed_at',
                'order.items:id,order_id,product_snapshot,quantity,unit_price,line_total,fulfillment_status',
                'invitation.campaign',
            ])
            ->when($request->campaign_id, fn ($q) => $q->whereHas('invitation', fn ($s) => $s->where('campaign_id', $request->campaign_id)))
            ->latest()
            ->paginate(20, ['*'], 'campaign_page')
            ->withQueryString();

        // ── Summary (campaign commissions) ────────────────────────────────────
        $summaryQuery = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds);

        $summary = [
            'total_orders' => (clone $summaryQuery)->count(),
            'total_commission' => (clone $summaryQuery)->sum('commission_amount'),
            'paid_commission' => (clone $summaryQuery)->where('commissioned', true)->sum('commission_amount'),
            'pending_commission' => (clone $summaryQuery)->where('commissioned', false)->sum('commission_amount'),
            'own_orders_count' => SubOrder::where('marketer_id', $marketer->id)->where('seller_type', 'marketer')->count(),
        ];

        $activeTab = $request->get('tab', 'own');

        return view('marketer.orders.index', compact('marketer', 'ownOrders', 'campaignOrders', 'summary', 'activeTab'));
    }

    /**
     * Show detail for a single order — own-listing sub-order first, then conversion fallback.
     */
    public function show(string $orderId): View
    {
        $marketer = $this->marketer();

        // Try own-listing sub-order first
        $subOrder = SubOrder::where('marketer_id', $marketer->id)
            ->where('seller_type', 'marketer')
            ->where('id', $orderId)
            ->with([
                'order:id,order_number,status,currency,total,payment_status,placed_at,shipping_address_snapshot,country_id',
                'order.country:id,name_ar,name_en',
                'items:id,sub_order_id,product_snapshot,quantity,unit_price,line_total,fulfillment_status,product_variant_id',
                'items.productVariant.product:id,name_ar,name_en',
                'warehouse:id,name',
            ])
            ->first();

        if ($subOrder) {
            return view('marketer.orders.show', compact('marketer', 'subOrder'));
        }

        // Fall back to campaign conversion lookup
        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)->pluck('id');

        $conversion = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->where('order_id', $orderId)
            ->with([
                'order.items',
                'invitation.campaign.vendorListing.productVariant' => fn ($q) => $q->withTrashed(),
                'invitation.campaign.vendorListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'invitation.campaign.adminListing' => fn ($q) => $q->withTrashed(),
                'invitation.campaign.adminListing.productVariant' => fn ($q) => $q->withTrashed(),
                'invitation.campaign.adminListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'invitation.campaign.travelPackage',
                'invitation.campaign.classifiedListing',
                'invitation.campaign.country',
            ])
            ->firstOrFail();

        return view('marketer.orders.show', compact('marketer', 'conversion'));
    }
}
