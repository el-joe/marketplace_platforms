<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom per-agency roles (guard_name = 'travel_agency') are scoped by
 * travel_agency_id so one agency cannot see, edit, or delete another
 * agency's roles. The physical `name` column stays globally unique
 * ({travel_agency_id}::{slug} for custom roles), and `label` holds the
 * human-readable name shown in the UI. System roles (travel_agency_owner/
 * manager/staff) keep travel_agency_id null and are shared across agencies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->uuid('travel_agency_id')->nullable()->after('guard_name');
            $table->string('label')->nullable()->after('travel_agency_id');

            $table->foreign('travel_agency_id')->references('id')->on('travel_agencies')->cascadeOnDelete();
            $table->index(['guard_name', 'travel_agency_id']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['travel_agency_id']);
            $table->dropIndex(['guard_name', 'travel_agency_id']);
            $table->dropColumn(['travel_agency_id', 'label']);
        });
    }
};
