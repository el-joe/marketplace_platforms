<?php

namespace App\Notifications;

use App\Models\DeliveryAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly DeliveryAssignment $assignment
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('طلب توصيل جديد #' . $this->assignment->id)
            ->line('لديك طلب توصيل جديد تم تعيينه.')
            ->line('رقم الطلب: ' . $this->assignment->id)
            ->action('عرض الطلب', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'status'        => $this->assignment->status?->value,
            'assigned_at'   => $this->assignment->assigned_at?->toIso8601String(),
        ];
    }
}
