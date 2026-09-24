<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_commission_rules', function (Blueprint $table) {
            $table->json('excluded_category_ids')->nullable()->after('category_id')
                ->comment('Only for scope-default rules (category_id null): categories this rule does not apply to.');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_commission_rules', function (Blueprint $table) {
            $table->dropColumn('excluded_category_ids');
        });
    }
};
