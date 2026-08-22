<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table): void {
            // Links a carrier integration (e.g. Aramex API) to the local ShippingCompany
            // that physically handles deliveries under that carrier name.
            // Nullable: some carriers (e.g. DHL) may not have a local company in the system yet.
            $table->char('shipping_company_id', 36)->nullable()->after('id');
            $table->foreign('shipping_company_id')
                ->references('id')->on('shipping_companies')
                ->nullOnDelete();
            $table->index('shipping_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table): void {
            $table->dropForeign(['shipping_company_id']);
            $table->dropColumn('shipping_company_id');
        });
    }
};
