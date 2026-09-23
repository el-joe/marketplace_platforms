<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookable_units MODIFY type ENUM('chalet','hotel_room','apartment','other') NOT NULL DEFAULT 'chalet'");

        Schema::table('bookable_units', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft')->after('description');
            $table->uuid('approved_by_admin_id')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by_admin_id');
            $table->foreign('approved_by_admin_id')->references('id')->on('admins')->onDelete('set null');
            $table->index(['travel_agency_id', 'status']);
        });

        Schema::table('bookable_unit_reservations', function (Blueprint $table) {
            $table->string('reservation_number', 30)->nullable()->unique()->after('id');
            $table->string('currency', 3)->nullable()->after('total_price');
            $table->text('customer_notes')->nullable()->after('status');
            $table->uuid('confirmed_by_admin_id')->nullable()->after('customer_notes');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by_admin_id');
            $table->foreign('confirmed_by_admin_id')->references('id')->on('admins')->onDelete('set null');
        });

        // Existing units were already live before the approval workflow existed.
        DB::table('bookable_units')->update(['status' => 'active']);
        DB::table('bookable_unit_reservations')->whereNull('reservation_number')->orderBy('created_at')->each(
            fn ($r) => DB::table('bookable_unit_reservations')->where('id', $r->id)
                ->update(['reservation_number' => 'BU-'.strtoupper(Str::random(8))])
        );
    }

    public function down(): void
    {
        Schema::table('bookable_unit_reservations', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by_admin_id']);
            $table->dropColumn(['reservation_number', 'currency', 'customer_notes', 'confirmed_by_admin_id', 'confirmed_at']);
        });
        Schema::table('bookable_units', function (Blueprint $table) {
            $table->dropForeign(['approved_by_admin_id']);
            $table->dropIndex(['travel_agency_id', 'status']);
            $table->dropColumn(['name_ar', 'status', 'approved_by_admin_id', 'approved_at']);
        });
    }
};
