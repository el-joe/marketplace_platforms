<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE support_tickets MODIFY COLUMN requester_role ENUM('customer','seller','marketer','delivery_agent') NOT NULL");

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->uuid('related_assignment_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('related_assignment_id');
        });

        DB::statement("ALTER TABLE support_tickets MODIFY COLUMN requester_role ENUM('customer','seller','marketer') NOT NULL");
    }
};
