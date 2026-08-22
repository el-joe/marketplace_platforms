<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('marketer_sample_quota')->default(0)
                ->comment('Max samples a marketer can request per campaign targeting this category')
                ->after('is_featured');
            $table->unsignedSmallInteger('admin_sample_quota')->default(0)
                ->comment('Additional admin-mandated samples auto-added on approval, hidden from marketer, visible to vendor as part of combined total')
                ->after('marketer_sample_quota');
        });

        // Fix inverted default: existing items were created with is_mandatory=1 (the
        // column default), but marketer-requested items should be 0. Since no admin
        // approval flow has ever populated this column deliberately, all existing rows
        // are marketer-requested items incorrectly flagged as mandatory. Reset them.
        if (Schema::hasTable('marketer_sample_items')) {
            DB::table('marketer_sample_items')->update(['is_mandatory' => 0]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['marketer_sample_quota', 'admin_sample_quota']);
        });
    }
};
