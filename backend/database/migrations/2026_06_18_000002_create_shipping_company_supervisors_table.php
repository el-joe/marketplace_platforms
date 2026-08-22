<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_company_supervisors', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('shipping_company_id', 36)->index();
            $table->foreign('shipping_company_id')->references('id')->on('shipping_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->json('permissions')->nullable();
            // ['manage_agents','view_orders','assign_orders','view_reports']
            $table->tinyInteger('is_active')->default(1);
            $table->tinyInteger('receives_all_notifications')->default(1);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_company_supervisors');
    }
};
