<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Replaces Laravel's built-in DatabaseChannel to write to our custom
 * notifications table which has a required `channel` enum column and
 * no `updated_at` column (non-standard schema).
 */
class CustomDatabaseChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $data = method_exists($notification, 'toDatabase')
            ? $notification->toDatabase($notifiable)
            : $notification->toArray($notifiable);

        DB::table('notifications')->insert([
            'id'              => Str::uuid()->toString(),
            'type'            => get_class($notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id'   => $notifiable->getKey(),
            'data'            => json_encode($data),
            'channel'         => 'database',
            'read_at'         => null,
            'sent_at'         => now(),
            'created_at'      => now(),
        ]);
    }
}
