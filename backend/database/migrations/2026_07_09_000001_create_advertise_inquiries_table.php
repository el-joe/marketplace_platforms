<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertise_inquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('country', 2);
            $table->string('name');
            $table->string('email');
            $table->string('company_name');
            $table->string('phone')->nullable();
            $table->text('description');
            $table->enum('status', ['new', 'contacted', 'closed'])->default('new');
            $table->timestamps();

            $table->index(['country', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertise_inquiries');
    }
};
