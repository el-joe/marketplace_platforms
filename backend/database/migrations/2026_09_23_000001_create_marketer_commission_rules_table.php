<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketer_commission_rules')) {
            Schema::create('marketer_commission_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('marketer_id')->nullable()->comment('Null = platform default for the scope.');
                $table->string('scope', 20)->comment('products | open_market | travel');
                $table->string('category_type')->nullable();
                $table->uuid('category_id')->nullable()->comment('Null = default for the whole scope.');
                $table->string('commission_mode', 20)->default('percentage');
                $table->decimal('commission_rate', 5, 2)->default(0);
                $table->unsignedBigInteger('commission_flat_amount')->nullable();
                $table->uuid('updated_by_admin_id')->nullable();
                $table->timestamps();

                $table->unique(['marketer_id', 'scope', 'category_type', 'category_id'], 'mcr_unique');
                $table->index(['scope', 'category_type', 'category_id'], 'mcr_scope_category_idx');
                $table->index('marketer_id');
                $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
                $table->foreign('updated_by_admin_id')->references('id')->on('admins')->nullOnDelete();
            });
        }

        $this->backfill('marketer_category_commissions', 'category_id', 'products', \App\Models\Category::class);
        $this->backfill('open_market_category_commissions', 'classified_category_id', 'open_market', \App\Models\ClassifiedCategory::class);
    }

    private function backfill(string $table, string $col, string $scope, string $type): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        DB::table($table)->orderBy('created_at')->get()->each(function ($row) use ($col, $scope, $type) {
            $exists = DB::table('marketer_commission_rules')
                ->where('scope', $scope)
                ->when($row->marketer_id, fn ($q) => $q->where('marketer_id', $row->marketer_id), fn ($q) => $q->whereNull('marketer_id'))
                ->when($row->{$col}, fn ($q) => $q->where('category_type', $type)->where('category_id', $row->{$col}),
                    fn ($q) => $q->whereNull('category_id'))
                ->exists();
            if ($exists) {
                return;
            }
            DB::table('marketer_commission_rules')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                // Present once the dedupe-key migration has run (re-runs, tests).
                ...(Schema::hasColumn('marketer_commission_rules', 'rule_key')
                    ? ['rule_key' => \App\Models\MarketerCommissionRule::makeRuleKey($row->marketer_id, $scope, $row->{$col} ? $type : null, $row->{$col})]
                    : []),
                'marketer_id' => $row->marketer_id,
                'scope' => $scope,
                'category_type' => $row->{$col} ? $type : null,
                'category_id' => $row->{$col},
                'commission_mode' => $row->commission_mode ?? 'percentage',
                'commission_rate' => $row->commission_rate,
                'commission_flat_amount' => $row->commission_flat_amount ?? null,
                'updated_by_admin_id' => $row->updated_by_admin_id ?? null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_commission_rules');
    }
};
