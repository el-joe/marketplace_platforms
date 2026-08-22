<?php

namespace App\Jobs;

use App\Models\CartInventoryLock;
use App\Models\InventoryMovement;
use App\Models\WarehouseInventory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredLocksJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $expired = CartInventoryLock::where('expires_at', '<', now())->get();

        foreach ($expired as $lock) {
            try {
                DB::transaction(function () use ($lock) {
                    $inventory = WarehouseInventory::where('id', $lock->warehouse_inventory_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory) {
                        $lock->delete();
                        return;
                    }

                    $releaseQty = min($lock->quantity, $inventory->quantity_reserved);
                    if ($releaseQty > 0) {
                        $inventory->decrement('quantity_reserved', $releaseQty);
                        $inventory->refresh();

                        InventoryMovement::create([
                            'warehouse_inventory_id' => $inventory->id,
                            'movement_type'          => 'release',
                            'quantity_delta'         => -$releaseQty,
                            'quantity_after'         => $inventory->quantity_on_hand,
                            'reference_type'         => 'order',
                            'reference_id'           => $lock->cart_id,
                            'reason'                 => 'Expired checkout lock released by scheduler',
                            'created_by_user_id'     => $lock->cart_id, // system actor
                        ]);
                    }

                    $lock->delete();
                });
            } catch (\Throwable $e) {
                Log::error("Failed to release expired lock {$lock->id}: {$e->getMessage()}");
            }
        }
    }
}
