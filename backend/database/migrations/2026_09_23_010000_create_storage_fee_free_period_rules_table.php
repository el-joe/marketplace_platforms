<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Client feature request doc, section 5 ("رسوم التخزين الحقيقية حسب الوزن").
 *
 * Configurable free-storage-period tiers by chargeable weight, used by
 * GenerateFbnStorageFeesJob instead of a hardcoded free-days value. Admin
 * manageable via App\Http\Controllers\Admin\FbnController free-period-rules
 * endpoints, so ops can add/adjust tiers without a code deploy.
 *
 * Seeded per the plan's initial data: 0-999g -> 60 free days, 1000g+ -> 30
 * free days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_fee_free_period_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('min_weight_grams');
            $table->unsignedInteger('max_weight_grams')->nullable(); // null = open-ended
            $table->unsignedSmallInteger('free_days');
            $table->timestamps();
        });

        DB::table('storage_fee_free_period_rules')->insert([
            [
                'id' => (string) Str::uuid(),
                'min_weight_grams' => 0,
                'max_weight_grams' => 999,
                'free_days' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'min_weight_grams' => 1000,
                'max_weight_grams' => null,
                'free_days' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_fee_free_period_rules');
    }
};
