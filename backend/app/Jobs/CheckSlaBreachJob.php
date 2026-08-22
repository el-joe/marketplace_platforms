<?php

namespace App\Jobs;

use App\Models\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckSlaBreachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Sub-order statuses in which an SLA breach is still relevant.
     */
    private const BREACHABLE_STATUSES = ['placed', 'confirmed', 'processing', 'packed'];

    public function handle(): void
    {
        $updated = DB::table('sub_orders')
            ->whereNull('deleted_at')
            ->where('sla_breached', false)
            ->whereNotNull('sla_ship_deadline')
            ->where('sla_ship_deadline', '<', now())
            ->whereIn('status', self::BREACHABLE_STATUSES)
            ->update([
                'sla_breached' => true,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            Log::info('CheckSlaBreachJob: marked ' . $updated . ' sub-order(s) as SLA breached.');
        }
    }
}
