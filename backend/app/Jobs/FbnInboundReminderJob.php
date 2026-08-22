<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\FbnInboundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Weekly job: notifies warehouse managers / super-admins of
 * FBN inbound requests in "submitted" status awaiting approval.
 *
 * Scheduled: every Monday (see routes/console.php).
 */
class FbnInboundReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function handle(): void
    {
        $pending = FbnInboundRequest::where('status', 'submitted')
            ->with(['vendor', 'warehouse'])
            ->orderBy('created_at')
            ->get();

        if ($pending->isEmpty()) {
            Log::info('[FbnInboundReminderJob] No pending inbound requests. Skipping.');
            return;
        }

        Log::info("[FbnInboundReminderJob] Found {$pending->count()} pending inbound request(s).");

        // Collect warehouse manager admin IDs
        $managerIds = $pending
            ->pluck('warehouse.manager_admin_id')
            ->filter()
            ->unique()
            ->toArray();

        // Also include super_admin role holders
        $admins = Admin::whereIn('id', $managerIds)
            ->orWhereHas('roles', fn($q) => $q->where('name', 'super_admin'))
            ->get(['id', 'name', 'email']);

        foreach ($admins as $admin) {
            Log::info("[FbnInboundReminderJob] Notifying {$admin->email} — {$pending->count()} pending request(s).");

            // Mail::to($admin->email)->send(new \App\Mail\FbnInboundReminderMail($admin, $pending));
            // NOTE: Mailable not yet implemented; log only.
        }

        Log::info('[FbnInboundReminderJob] Reminder job completed.');
    }
}
