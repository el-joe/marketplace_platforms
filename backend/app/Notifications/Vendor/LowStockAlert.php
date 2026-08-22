<?php

namespace App\Notifications\Vendor;

use App\Models\WarehouseInventory;
use App\Notifications\BaseDatabaseBroadcastNotification;

class LowStockAlert extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly WarehouseInventory $inventory) {}

    public function notificationType(): string
    {
        return 'low_stock_alert';
    }

    public function notificationData(object $notifiable): array
    {
        $listing = $this->inventory->vendorListing ?? null;
        $productName = $listing?->productVariant?->product?->name_en ?? 'a product';

        return [
            'title'        => 'Low Stock Alert',
            'message'      => "Stock for \"{$productName}\" has dropped to {$this->inventory->quantity_on_hand} units (reorder point: {$this->inventory->reorder_point}).",
            'url'          => route('partner.warehouses.show', $this->inventory->warehouse_id),
            'inventory_id' => $this->inventory->id,
            'warehouse_id' => $this->inventory->warehouse_id,
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
                'screen' => 'listing_detail',
                'id'     => $this->inventory->vendorListing?->id,
                'type'   => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
