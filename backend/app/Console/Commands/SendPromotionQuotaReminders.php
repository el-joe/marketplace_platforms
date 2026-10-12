<?php

namespace App\Console\Commands;

use App\Models\Marketer;
use App\Notifications\Marketer\PromotionQuotaBehindNotification;
use App\Services\Marketer\PromotionQuotaService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendPromotionQuotaReminders extends Command
{
    protected $signature = 'marketers:promotion-quota-reminders';

    protected $description = 'In the last week of the month, warn marketers who are short of their monthly promotion minimum (in-app + WhatsApp, once per month)';

    public function handle(PromotionQuotaService $quota, WhatsAppService $whatsapp): int
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

                    if (! Cache::add("promotion-quota-reminder:{$marketer->id}:{$progress['month']}", true, now()->addDays(40))) {
                        continue;
                    }

                    $marketer->marketerAdmins->each(
                        fn ($admin) => $admin->notify(new PromotionQuotaBehindNotification($progress['done'], $progress['target'], $admin->id))
                    );

                    if ($marketer->whatsapp_for_campaigns) {
                        $whatsapp->send($marketer->whatsapp_for_campaigns, __('marketer.quota.reminder_message', [
                            'done' => $progress['done'],
                            'target' => $progress['target'],
                        ], config('app.locale')));
                    }

                    $sent++;
                }
            });

        $this->info("Sent {$sent} promotion quota reminder(s).");

        return self::SUCCESS;
    }
}
