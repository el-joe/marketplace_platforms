<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->unsignedInteger('cod_max_amount')->default(0)->after('cod_available');
            $table->unsignedInteger('cod_supermall_max_amount')->default(0)->after('cod_max_amount');
            $table->char('cod_supermall_category_id', 36)->nullable()->after('cod_supermall_max_amount');

            $table->foreign('cod_supermall_category_id')
                ->references('id')->on('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropForeign(['cod_supermall_category_id']);
            $table->dropColumn([
                'cod_max_amount',
                'cod_supermall_max_amount',
                'cod_supermall_category_id',
            ]);
        });
    }
};
