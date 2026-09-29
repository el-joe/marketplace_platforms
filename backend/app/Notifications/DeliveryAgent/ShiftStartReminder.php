<?php

namespace App\Notifications\DeliveryAgent;

use App\Models\DeliveryAgentShift;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Carbon\Carbon;
use Illuminate\Broadcasting\PrivateChannel;

class ShiftStartReminder extends BaseDatabaseBroadcastNotification
{
    // TODO: dispatch from a scheduled command or job that runs 30 minutes before
    // each upcoming shift. Example:
    //   $agent->notify(new ShiftStartReminder($shift));

    public function __construct(private readonly DeliveryAgentShift $shift) {}

    public function notificationType(): string
    {
        return 'shift_start_reminder';
    }

    public function notificationData(object $notifiable): array
    {
        $time = $this->shift->scheduled_start
            ? Carbon::parse($this->shift->scheduled_start)->format('H:i')
            : '—';

        return [
            'title' => 'Shift Starting Soon',
            'message' => "Your shift starts in 30 minutes at {$time}.",
            'url' => route('delivery.earnings.index'),
            'shift_id' => $this->shift->id,
            'starts_at' => $this->shift->scheduled_start,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('delivery-agent.'.$this->shift->agent_id)];
    }
}
