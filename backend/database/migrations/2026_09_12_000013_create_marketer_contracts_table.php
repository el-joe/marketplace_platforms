<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_id');
            $table->unsignedInteger('current_version')->default(0);
            $table->boolean('is_required')->default(true)
                ->comment('If true, customer must accept before proceeding');
            $table->timestamps();

            $table->unique('marketer_id');

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_contracts');
    }
};
