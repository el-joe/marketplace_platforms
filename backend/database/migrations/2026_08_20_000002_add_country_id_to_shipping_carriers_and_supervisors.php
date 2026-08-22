<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table): void {
            // Scopes a carrier integration to the country it operates in. Nullable:
            // existing carriers predate this column and may serve multiple countries
            // via their linked shipping company until backfilled.
            $table->char('country_id', 36)->nullable()->after('shipping_company_id');
            $table->foreign('country_id')
                ->references('id')->on('countries')
                ->nullOnDelete();
            $table->index('country_id');
        });

        Schema::table('shipping_company_supervisors', function (Blueprint $table): void {
            // A shipping company may operate in multiple countries; a supervisor is
            // scoped to a single one. Nullable for backward compat with existing
            // supervisors — controllers fall back to the parent company's country_id
            // until this is backfilled.
            $table->char('country_id', 36)->nullable()->after('shipping_company_id');
            $table->foreign('country_id')
                ->references('id')->on('countries')
                ->nullOnDelete();
            $table->index('country_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });

        Schema::table('shipping_company_supervisors', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
