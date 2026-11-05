<?php

namespace App\Jobs;

use App\Enums\WarehouseType;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VendorApprovedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $vendorId) {}

    /**
     * The approval email is sent synchronously by the approving request; this job only
     * prepares the vendor's default warehouse and activity log.
     */
    public function handle(): void
    {
        $vendor = Vendor::find($this->vendorId);

        if (! $vendor) {
            return;
        }

        // ── Create default warehouse if none assigned ──────────────────────────
        if (!$vendor->default_warehouse_id) {
            $warehouseId = \Illuminate\Support\Str::uuid();

            do {
                $warehouseCode = 'WH-' . strtoupper(Str::random(8));
            } while (DB::table('warehouses')->where('code', $warehouseCode)->exists());

            DB::table('warehouses')->insert([
                'id' => $warehouseId,
                'name' => $vendor->store_name . ' — Default Warehouse',
                'code' => $warehouseCode,
                'type' => WarehouseType::SellerOwned->value,
                'owner_vendor_id' => $vendor->id,
                'country_id' => $vendor->country_id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $vendor->updateQuietly(['default_warehouse_id' => $warehouseId]);
        }

        // ── Log activity ───────────────────────────────────────────────────────
        DB::table('activity_log')->insert([
            'id' => (string) Str::uuid(),
            'log_name' => 'vendor',
            'description' => 'Vendor approved — warehouse initialised',
            'subject_type' => Vendor::class,
            'subject_id' => $vendor->id,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => json_encode(['event' => 'vendor_approved']),
            'created_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('VendorApprovedJob failed', [
            'vendor_id' => $this->vendorId,
            'exception' => $e::class,
            'error' => $e->getMessage(),
        ]);
    }
}
