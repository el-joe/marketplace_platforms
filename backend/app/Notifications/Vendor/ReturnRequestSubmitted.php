<?php

namespace App\Notifications\Vendor;

use App\Models\ReturnRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ReturnRequestSubmitted extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ReturnRequest $returnRequest) {}

    public function notificationType(): string
    {
        return 'return_request_submitted';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'             => 'Return Request Submitted',
            'message'           => "A customer has submitted a return request ({$this->returnRequest->return_number}). Please review it.",
            'url'               => route('partner.returns.show', $this->returnRequest->return_number),
            'return_request_id' => $this->returnRequest->id,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->notificationData($notifiable);

        return [
            'title' => $data['title'],
            'body'  => $data['message'],
            'data'  => [
                'screen' => 'return_detail',
                'id'     => $this->returnRequest->return_number,
                'type'   => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
