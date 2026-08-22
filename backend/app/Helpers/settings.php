<?php

if (!function_exists('setting')) {
    /**
     * Get a platform setting value by key.
     *
     * Usage:
     *   setting('site_name')           // "Noon"
     *   setting('missing_key', 'def')  // "def"
     *
     * In Blade: {{ setting('site_name') }}
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Models\Setting::get($key, $default);
    }
}
