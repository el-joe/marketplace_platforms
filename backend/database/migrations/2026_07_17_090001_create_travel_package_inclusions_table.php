<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_package_inclusions', function (Blueprint $table) {
            $table->id();
            $table->uuid('travel_package_id');
            $table->uuid('travel_inclusion_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('travel_package_id')->references('id')->on('travel_packages')->cascadeOnDelete();
            $table->foreign('travel_inclusion_id')->references('id')->on('travel_inclusions')->cascadeOnDelete();
            $table->unique(['travel_package_id', 'travel_inclusion_id'], 'tp_inclusions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_package_inclusions');
    }
};
