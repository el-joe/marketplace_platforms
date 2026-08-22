<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for all platform notifications.
 *
 * Concrete subclasses MUST implement:
 *   - notificationType(): string  — snake_case event name, e.g. 'order_placed'
 *   - notificationData(object $notifiable): array — payload including at minimum:
 *       title (string), message (string), url (string)
 *   - broadcastOn(): array — returns [new PrivateChannel('guard.{id}')]
 *       because the channel depends on the notifiable's guard, which only
 *       the concrete class knows. Convention: pass the relevant ID in the
 *       constructor and use it here.
 *
 * The 'database' channel is handled by App\Notifications\Channels\CustomDatabaseChannel
 * (registered in AppServiceProvider) which writes to our custom notifications
 * table with channel='database'. The 'broadcast' channel pushes to Reverb
 * in real-time WITHOUT creating an additional database row.
 *
 * Example concrete class:
 *
 *   class OrderPlacedNotification extends BaseDatabaseBroadcastNotification
 *   {
 *       public function __construct(private Order $order) {}
 *
 *       public function notificationType(): string { return 'order_placed'; }
 *
 *       public function notificationData(object $notifiable): array {
 *           return [
 *               'title'    => 'New order #' . $this->order->id,
 *               'message'  => 'You have a new order.',
 *               'url'      => route('partner.orders.show', $this->order->id),
 *               'order_id' => $this->order->id,
 *           ];
 *       }
 *
 *       public function broadcastOn(): array {
 *           return [new \Illuminate\Broadcasting\PrivateChannel('vendor.' . $this->order->vendor_admin_id)];
 *       }
 *   }
 */
abstract class BaseDatabaseBroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function notificationType(): string;

    abstract public function notificationData(object $notifiable): array;

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return array_merge(
            ['type' => $this->notificationType()],
            $this->notificationData($notifiable)
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge(
            ['type' => $this->notificationType()],
            $this->notificationData($notifiable)
        ));
    }

    /**
     * Concrete classes MUST override this to return the correct private channel.
     * Example: return [new PrivateChannel('vendor.' . $this->vendorAdminId)];
     */
    public function broadcastOn(): array
    {
        throw new \LogicException(static::class . ' must implement broadcastOn().');
    }
}
