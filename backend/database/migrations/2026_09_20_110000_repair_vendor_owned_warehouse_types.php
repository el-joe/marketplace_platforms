<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('warehouses')
            ->whereNotNull('owner_vendor_id')
            ->where('type', 'platform_fbn')
            ->update(['type' => 'seller_owned']);
    }

    public function down(): void
    {
        // Intentionally a no-op: original (invalid) types are not restored.
    }
};
