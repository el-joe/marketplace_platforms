<?php

use App\Models\MarketerCommissionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketer_commission_rules', 'rule_key')) {
            Schema::table('marketer_commission_rules', function (Blueprint $table) {
                $table->string('rule_key', 40)->nullable()->after('id');
            });
        }

        // Fill keys; where several rows share one key keep the most recently
        // updated and drop the rest, so the unique index can be created.
        $seen = [];
        DB::table('marketer_commission_rules')->orderByDesc('updated_at')->orderByDesc('created_at')->get()
            ->each(function ($row) use (&$seen) {
                $key = MarketerCommissionRule::makeRuleKey($row->marketer_id, $row->scope, $row->category_type, $row->category_id);
                if (isset($seen[$key])) {
                    DB::table('marketer_commission_rules')->where('id', $row->id)->delete();

                    return;
                }
                $seen[$key] = true;
                DB::table('marketer_commission_rules')->where('id', $row->id)->update(['rule_key' => $key]);
            });

        Schema::table('marketer_commission_rules', function (Blueprint $table) {
            $table->string('rule_key', 40)->nullable(false)->change();
            $table->unique('rule_key', 'mcr_rule_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_commission_rules', function (Blueprint $table) {
            $table->dropUnique('mcr_rule_key_unique');
            $table->dropColumn('rule_key');
        });
    }
};
