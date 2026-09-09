<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('vendor_type', ['product_vendor', 'classified_vendor'])
                ->default('product_vendor')
                ->after('global_status')
                ->comment('Mutually exclusive account type: sells products via vendor_listings, or classified ads via classified_listings.');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('vendor_type');
        });
    }
};
