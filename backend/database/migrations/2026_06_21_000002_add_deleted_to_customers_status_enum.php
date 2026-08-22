<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE customers MODIFY COLUMN status ENUM('active','suspended','banned','deleted') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        // First ensure no 'deleted' rows exist before shrinking the enum
        DB::statement("UPDATE customers SET status = 'banned' WHERE status = 'deleted'");
        DB::statement("ALTER TABLE customers MODIFY COLUMN status ENUM('active','suspended','banned') NOT NULL DEFAULT 'active'");
    }
};
