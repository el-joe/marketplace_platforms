<?php

namespace App\Services;

use App\Models\MarketerCampaignConversion;
use App\Services\Marketer\CampaignSaleRecorder;

/**
 * Reverse a marketer conversion (and any wallet credit already given for it) when the order item it was earned on
 * is cancelled or returned. Shared by OrderCancellationService (full/partial order cancel) and RefundService
 * (item-level return refunds).
 *
 * The work itself (wallet, ledger, campaign budget) lives in CampaignSaleRecorder::reverse(). A conversion already
 * 'paid' (included in a settled marketer payout) is NEVER clawed back automatically: that money has left the
 * platform's control; recovering it is a manual/admin process.
 */
class MarketerConversionReversalService
{
    private readonly CampaignSaleRecorder $recorder;

    public function __construct(?CampaignSaleRecorder $recorder = null)
    {
        $this->recorder = $recorder ?? app(CampaignSaleRecorder::class);
    }

    /**
     * @param  array<int, string>  $orderItemIds
     */
    public function reverseForOrderItemIds(array $orderItemIds): void
    {
        if (empty($orderItemIds)) {
            return;
        }

        MarketerCampaignConversion::whereIn('order_item_id', $orderItemIds)
            ->whereIn('status', ['pending', 'approved'])
            ->get()
            ->each(fn (MarketerCampaignConversion $conversion) => $this->reverseOne($conversion));
    }

    public function reverseOne(MarketerCampaignConversion $conversion): void
    {
        $this->recorder->reverse($conversion);
    }
}
