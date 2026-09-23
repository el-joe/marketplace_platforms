<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['badge_label_en', 'badge_label_ar'] as $col) {
            DB::table('shipping_methods')
                ->where(fn ($q) => $q->whereNull($col)->orWhere($col, ''))
                ->update([$col => DB::raw('name')]);
        }
    }

    public function down(): void {}
};
