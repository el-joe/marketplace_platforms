<?php

namespace App\Jobs;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VendorApprovedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $vendorId)
    {
    }

    public function handle(): void
    {
        $vendor = Vendor::find($this->vendorId);

        if (!$vendor) {
            return;
        }

        // ── Send welcome e-mail ───────────────────────────────────────────────
        Mail::raw(
            "Welcome to the platform, {$vendor->store_name}! Your vendor account has been approved. You can now start listing your products.",
            function ($message) use ($vendor) {
                $message->to($vendor->contact_email ?? $vendor->email)
                    ->subject('Your vendor account has been approved!');
            }
        );

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
            'log_name' => 'vendor',
            'description' => 'Vendor approved — welcome email sent, warehouse initialised',
            'subject_type' => Vendor::class,
            'subject_id' => $vendor->id,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => json_encode(['event' => 'vendor_approved']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
