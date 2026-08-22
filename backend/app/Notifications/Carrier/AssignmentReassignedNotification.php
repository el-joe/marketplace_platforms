<?php

namespace App\Notifications\Carrier;

use App\Models\DeliveryAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentReassignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DeliveryAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New delivery assignment')
            ->line('A delivery assignment has been reassigned to you.')
            ->line('Assignment ID: ' . $this->assignment->id)
            ->line('Please open your app to accept and proceed with the delivery.');
    }
}
