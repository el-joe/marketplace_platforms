<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    private function marketer(): \App\Models\Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    /**
     * All orders that came through this marketer's referral links.
     * Read-only — marketer cannot modify order status.
     */
    public function index(Request $request): View
    {
        $marketer = $this->marketer();

        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->pluck('id');

        $conversions = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->whereNotNull('order_id')
            ->with([
                'order:id,order_number,status,currency,total,payment_status,placed_at',
                'order.items:id,order_id,product_snapshot,quantity,unit_price,line_total,fulfillment_status',
                'invitation.campaign',
            ])
            ->when($request->status, fn ($q) => $q->whereHas('order', fn ($s) => $s->where('status', $request->status)))
            ->when($request->campaign_id, fn ($q) => $q->whereHas('invitation', fn ($s) => $s->where('campaign_id', $request->campaign_id)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $summaryQuery = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds);

        $summary = [
            'total_orders'       => (clone $summaryQuery)->count(),
            'total_commission'   => (clone $summaryQuery)->sum('commission_amount'),
            'paid_commission'    => (clone $summaryQuery)->where('commissioned', true)->sum('commission_amount'),
            'pending_commission' => (clone $summaryQuery)->where('commissioned', false)->sum('commission_amount'),
        ];

        return view('marketer.orders.index', compact('marketer', 'conversions', 'summary'));
    }

    /**
     * Single order detail (via conversion) — read-only.
     */
    public function show(string $orderId): View
    {
        $marketer      = $this->marketer();
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
