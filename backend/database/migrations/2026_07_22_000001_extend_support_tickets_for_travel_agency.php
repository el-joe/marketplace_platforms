<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MySQL requires re-specifying the full ENUM to add a value
        DB::statement("ALTER TABLE support_tickets MODIFY COLUMN requester_role ENUM('customer','seller','marketer','delivery_agent','shipping_supervisor','travel_agency') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE support_tickets MODIFY COLUMN requester_role ENUM('customer','seller','marketer','delivery_agent','shipping_supervisor') NOT NULL");
    }
};
