<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->unique()->constrained('vendors')->cascadeOnDelete();

            // Boutiqaat-style profile
            $table->foreignId('banner_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('video_url', 500)->nullable()->comment('YouTube/Instagram reel URL');
            $table->text('bio_ar')->nullable();
            $table->text('bio_en')->nullable();

            // Social media links (JSON object: {instagram, tiktok, youtube, twitter, facebook, snapchat})
            $table->json('social_links')->nullable();

            // Contact details (JSON: {phone, whatsapp, email, website})
            $table->json('contact_details')->nullable();

            // QR code pointing to marketer public profile or campaign URL
            $table->string('qr_code_path', 500)->nullable();

            // Public profile slug (e.g. platform.com/m/my-store-name)
            $table->string('profile_slug', 100)->nullable()->unique();

            // Stats (denormalized for display)
            $table->unsignedInteger('total_campaigns')->default(0);
            $table->unsignedInteger('total_conversions')->default(0);
            $table->bigInteger('total_earnings')->default(0)->comment('BIGINT base-currency. No /100.');
            $table->char('earnings_currency', 3)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_profiles');
    }
};
