<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE portal_contents MODIFY type ENUM('text', 'richtext', 'link', 'image') NOT NULL DEFAULT 'text'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE portal_contents MODIFY type ENUM('text', 'richtext', 'link') NOT NULL DEFAULT 'text'");
    }
};
