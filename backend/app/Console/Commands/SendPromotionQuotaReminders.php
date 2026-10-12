<?php

namespace App\Console\Commands;

use App\Models\Marketer;
use App\Notifications\Marketer\PromotionQuotaBehindNotification;
use App\Services\Marketer\PromotionQuotaService;
use App\Contracts\WhatsAppGateway;
use Illuminate\Console\Command;
use App\Models\MarketerPromotionReminder;
use Illuminate\Database\UniqueConstraintViolationException;

class SendPromotionQuotaReminders extends Command
{
    protected $signature = 'marketers:promotion-quota-reminders';

    protected $description = 'In the last week of the month, warn marketers who are short of their monthly promotion minimum (in-app + WhatsApp, once per month)';

    public function handle(PromotionQuotaService $quota, WhatsAppGateway $whatsapp): int
    {
        $sent = 0;

        Marketer::query()
            ->where('global_status', 'active')
            ->whereHas('marketerProfile', fn ($q) => $q->whereNotNull('promotion_category'))
            ->with(['marketerProfile', 'marketerAdmins'])
            ->chunkById(200, function ($marketers) use ($quota, $whatsapp, &$sent) {
                foreach ($marketers as $marketer) {
                    $progress = $quota->progressFor($marketer);

                    if (! $progress['warning']) {
                        continue;
                    }

                    // Claim marketer/category/month first (unique key): a second run or a concurrent worker never re-sends.
                    try {
                        $reminder = MarketerPromotionReminder::create([
                            'marketer_id' => $marketer->id,
                            'promotion_category' => $progress['category']->value,
                            'period' => $progress['month'],
                            'done' => $progress['done'],
                            'target' => $progress['target'],
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        continue;
                    }

                    $marketer->marketerAdmins->each(
                        fn ($admin) => $admin->notify(new PromotionQuotaBehindNotification($progress['done'], $progress['target'], $admin->id))
                    );

                    if ($marketer->whatsapp_for_campaigns) {
                        $reminder->update(['whatsapp_sent' => $whatsapp->send($marketer->whatsapp_for_campaigns, __('marketer.quota.reminder_message', [
                            'done' => $progress['done'],
                            'target' => $progress['target'],
                        ], config('app.locale')))]);
                    }

                    $sent++;
                }
            });

        $this->info("Sent {$sent} promotion quota reminder(s).");

        return self::SUCCESS;
    }
}
