<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_agents', 'zone_id')) {
                $table->char('zone_id', 36)->nullable()->after('country_id');
                $table->foreign('zone_id')->references('id')->on('delivery_zones')->nullOnDelete();
            }

            if (!Schema::hasColumn('delivery_agents', 'national_id')) {
                $table->string('national_id', 50)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('delivery_agents', 'vehicle_plate')) {
                $table->string('vehicle_plate', 30)->nullable()->after('vehicle_type');
            }
            if (!Schema::hasColumn('delivery_agents', 'emergency_contact_name')) {
                $table->string('emergency_contact_name', 150)->nullable()->after('vehicle_plate');
            }
            if (!Schema::hasColumn('delivery_agents', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
            }
            if (!Schema::hasColumn('delivery_agents', 'base_salary')) {
                $table->unsignedInteger('base_salary')->nullable()->after('emergency_contact_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_agents', 'zone_id')) {
                $table->dropForeign(['zone_id']);
            }

            $table->dropColumn(array_filter([
                Schema::hasColumn('delivery_agents', 'zone_id') ? 'zone_id' : null,
                Schema::hasColumn('delivery_agents', 'national_id') ? 'national_id' : null,
                Schema::hasColumn('delivery_agents', 'vehicle_plate') ? 'vehicle_plate' : null,
                Schema::hasColumn('delivery_agents', 'emergency_contact_name') ? 'emergency_contact_name' : null,
                Schema::hasColumn('delivery_agents', 'emergency_contact_phone') ? 'emergency_contact_phone' : null,
                Schema::hasColumn('delivery_agents', 'base_salary') ? 'base_salary' : null,
            ]));
        });
    }
};
