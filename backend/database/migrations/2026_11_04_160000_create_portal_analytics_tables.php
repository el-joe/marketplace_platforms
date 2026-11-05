<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal visitor analytics + registration funnel (client requirement 2).
 *
 * No personal data is stored: the visitor is an anonymous random UUID kept in a cookie, the
 * IP address is only kept as a salted hash, and the referrer is reduced to its host.
 *
 *  - portal_visitors: one row per anonymous visitor (id = the cookie value).
 *  - portal_page_views: one row per counted portal page view (refreshes are not counted).
 *  - registration_progress: one row per visitor and registration flow; set to completed (and
 *    linked to the created vendor / travel agency) when the application is submitted.
 *  - registration_step_events: viewed / completed / validation_failed per progress and step.
 *    UNIQUE per progress+step+event, so a repeated event can never be double counted
 *    (validation_failed repeats only bump `occurrences`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_visitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->string('country_code', 20)->nullable()->comment('countries.site_code at first visit');
            $table->string('device_type', 10)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('referrer_host', 191)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->char('ip_hash', 64)->nullable()->comment('HMAC of the IP, never the IP itself');

            $table->index('first_seen_at');
            $table->index('last_seen_at');
        });

        Schema::create('portal_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('visitor_id')->constrained('portal_visitors')->cascadeOnDelete();
            $table->string('path', 191);
            $table->string('route_name', 100)->nullable();
            $table->string('country_code', 20)->nullable()->comment('countries.site_code shown to the visitor');
            $table->boolean('is_new_visit')->default(false);
            $table->timestamp('viewed_at');

            // Covering index for the period / country aggregates (COUNT DISTINCT visitor_id).
            $table->index(['viewed_at', 'country_code', 'visitor_id'], 'portal_page_views_period_idx');
            $table->index(['visitor_id', 'viewed_at']);
        });

        Schema::create('registration_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('visitor_id')->constrained('portal_visitors')->cascadeOnDelete();
            $table->string('flow', 30);
            $table->string('country_code', 20)->nullable()->comment('countries.site_code when the wizard was opened');
            $table->unsignedTinyInteger('highest_step_reached')->default(0);
            $table->unsignedTinyInteger('last_step_completed')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('first_submitted_at')->nullable()->comment('first time any step was submitted');
            $table->timestamp('last_activity_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignUuid('travel_agency_id')->nullable()->constrained('travel_agencies')->nullOnDelete();

            $table->unique(['visitor_id', 'flow']);
            $table->index(['flow', 'started_at', 'country_code'], 'registration_progress_period_idx');
        });

        Schema::create('registration_step_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('registration_progress_id')->constrained('registration_progress')->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->enum('event', ['viewed', 'completed', 'validation_failed']);
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('occurred_at');

            $table->unique(['registration_progress_id', 'step', 'event'], 'registration_step_events_unique');
            $table->index(['step', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_step_events');
        Schema::dropIfExists('registration_progress');
        Schema::dropIfExists('portal_page_views');
        Schema::dropIfExists('portal_visitors');
    }
};
