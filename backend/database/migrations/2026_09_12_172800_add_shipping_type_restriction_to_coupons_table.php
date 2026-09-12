<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->enum('shipping_type_restriction', ['all', 'fbn', 'fbp', 'fbm'])
                ->default('all')
                ->after('scope')
                ->comment('fbn=Nawi fulfillment, fbp=partner/cross-dock carrier, fbm=vendor self-ship, all=no restriction');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('shipping_type_restriction');
        });
    }
};
