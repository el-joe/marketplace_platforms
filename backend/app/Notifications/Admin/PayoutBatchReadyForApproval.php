<?php

namespace App\Notifications\Admin;

use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class PayoutBatchReadyForApproval extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly string $batchType, // 'vendor' or 'marketer'
        private readonly int $payoutCount,
        private readonly string $periodStart,
        private readonly string $periodEnd,
    ) {}

    public function notificationType(): string
    {
        return 'payout_batch_ready_for_approval';
    }

    public function notificationData(object $notifiable): array
    {
        $label = $this->batchType === 'marketer' ? 'Marketer' : 'Vendor';
        $link  = $this->batchType === 'marketer'
            ? route('admin.marketers.payouts.index')
            : route('admin.payouts.index');

        return [
            'title'        => "{$label} Payout Batch Ready",
            'message'      => "{$this->payoutCount} {$label} payout(s) generated for {$this->periodStart} – {$this->periodEnd} and are awaiting approval.",
            'batch_type'   => $this->batchType,
            'payout_count' => $this->payoutCount,
            'period_start' => $this->periodStart,
            'period_end'   => $this->periodEnd,
            'link'         => $link,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
