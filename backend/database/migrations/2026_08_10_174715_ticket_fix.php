<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix ticket_messages.sender_id column type.
 *
 * The original migration used $table->morphs('sender') which creates sender_id
 * as unsignedBigInteger. However every sender model (Admin, VendorAdmin,
 * TravelAgencyMember, Customer) uses UUID primary keys, so inserting a UUID
 * string caused "Data truncated for column 'sender_id'" errors.
 *
 * This migration:
 *  1. Drops the composite morph index on (sender_type, sender_id)
 *  2. Alters sender_id to varchar(36) (UUID-compatible)
 *  3. Recreates the morph index
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the index that morphs() auto-created (named ticket_messages_sender_type_sender_id_index)
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['sender_type', 'sender_id']);
        });

        // Change sender_id to varchar(36) to hold UUIDs
        DB::statement('ALTER TABLE ticket_messages MODIFY sender_id VARCHAR(36) NOT NULL');

        // Recreate the morph index
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->index(['sender_type', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['sender_type', 'sender_id']);
        });

        DB::statement('ALTER TABLE ticket_messages MODIFY sender_id BIGINT UNSIGNED NOT NULL');

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->index(['sender_type', 'sender_id']);
        });
    }
};
