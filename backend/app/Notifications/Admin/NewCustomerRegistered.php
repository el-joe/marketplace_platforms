<?php

namespace App\Notifications\Admin;

use App\Models\Customer;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewCustomerRegistered extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly Customer $customer) {}

    public function notificationType(): string
    {
        return 'new_customer_registered';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'New Customer Registered',
            'message' => "New customer {$this->customer->name} registered.",
            'url' => route('admin.customers.show', $this->customer),
            'customer_id' => $this->customer->id,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.'.$notifiable->id)];
    }
}
