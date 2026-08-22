<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email', 191)->index();
            $table->char('country_id', 36)->nullable()->index();
            $table->char('customer_id', 36)->nullable()->index();
            $table->string('source', 50)->default('website'); // website|app|checkout|page_builder
            $table->string('locale', 5)->default('ar');       // ar|en
            $table->string('status', 20)->default('active');  // active|unsubscribed
            $table->string('unsubscribe_token', 64)->unique()->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Unique per email+country — prevent duplicates per market
            $table->unique(['email', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
