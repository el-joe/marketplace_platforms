<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * files.model_id was created via morphs() as unsignedBigInteger, but every model
     * currently attached through files.model_type (Banner, Vendor, Order, Review, ...)
     * uses HasUuids. A UUID can't be stored in a bigint column, so the polymorphic
     * link silently breaks (e.g. cart banner images never resolve). Widen it to match
     * the uuidMorphs pattern already used by personal_access_tokens.tokenable_id.
     */
    public function up(): void
    {
        Schema::table('files', function ($table) {
            $table->dropIndex('files_model_type_model_id_index');
        });

        DB::statement('ALTER TABLE files MODIFY model_id CHAR(36) NULL');

        Schema::table('files', function ($table) {
            $table->index(['model_type', 'model_id'], 'files_model_type_model_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('files', function ($table) {
            $table->dropIndex('files_model_type_model_id_index');
        });

        DB::statement('ALTER TABLE files MODIFY model_id BIGINT UNSIGNED NULL');

        Schema::table('files', function ($table) {
            $table->index(['model_type', 'model_id'], 'files_model_type_model_id_index');
        });
    }
};
