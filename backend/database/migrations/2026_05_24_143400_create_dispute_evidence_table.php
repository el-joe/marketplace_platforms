<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dispute_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dispute_id')->index();
            $table->uuid('uploaded_by_user_id')->index();
            // $table->uuid('media_id')->index();
            $table->string('description', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_evidence');
    }
};
