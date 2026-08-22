<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('packaging_supply_countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('packaging_supply_id');
            $table->uuid('country_id');
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->integer('stock_available')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['packaging_supply_id', 'country_id']);

            $table->foreign('packaging_supply_id')->references('id')->on('packaging_supplies')->cascadeOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
        });

        $this->backfill();
    }

    /**
     * Backfill a packaging_supply_countries row for every existing packaging
     * supply x active country combination, so existing supplies keep showing
     * up in partner views after this migration runs.
     */
    private function backfill(): void
    {
        $countryIds = DB::table('countries')->where('is_active', true)->pluck('id');

        if ($countryIds->isEmpty()) {
            return;
        }

        DB::table('packaging_supplies')
            ->orderBy('id')
            ->select(['id', 'unit_cost', 'stock_available', 'is_active'])
            ->chunkById(200, function ($supplies) use ($countryIds) {
                $now = now();
                $rows = [];

                foreach ($supplies as $supply) {
                    foreach ($countryIds as $countryId) {
                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'packaging_supply_id' => $supply->id,
                            'country_id' => $countryId,
                            'unit_cost' => $supply->unit_cost,
                            'stock_available' => $supply->stock_available,
                            'is_active' => $supply->is_active,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('packaging_supply_countries')->insert($chunk);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packaging_supply_countries');
    }
};
