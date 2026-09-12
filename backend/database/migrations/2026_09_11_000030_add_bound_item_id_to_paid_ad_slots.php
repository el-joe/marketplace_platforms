<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paid_ad_slots', function (Blueprint $t) {
            $t->char('bound_item_id', 36)->nullable()->after('item_position')
              ->comment('DB id of the SliderSlide or AdImageItem this slot is bound to. Used to remap item_position after reorders.');
            $t->index('bound_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('paid_ad_slots', function (Blueprint $t) {
            $t->dropIndex(['bound_item_id']);
            $t->dropColumn('bound_item_id');
        });
    }
};
