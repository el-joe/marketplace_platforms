<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Admin toggle (Settings → General) for the floating "Live / Streams" button on the storefront.
 * Public so the Next.js storefront can read it from GET /api/public/settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Setting::where('key', 'live_stream_button_enabled')->exists()) {
            Setting::create([
                'key' => 'live_stream_button_enabled',
                'value' => true,
                'category' => 'general',
                'description' => 'Show the floating Live Streams button on the storefront',
                'is_public' => true,
            ]);
        }

        Cache::forget('settings:public');
    }

    public function down(): void
    {
        Setting::where('key', 'live_stream_button_enabled')->delete();
        Cache::forget('settings:public');
    }
};
