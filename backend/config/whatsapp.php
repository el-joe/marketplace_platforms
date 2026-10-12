<?php

/**
 * WhatsApp delivery. The driver is chosen by the admin setting `whatsapp_provider`
 * and falls back to WHATSAPP_DRIVER (default "log": nothing is delivered, messages are only logged).
 *
 * Real delivery ("meta" = WhatsApp Cloud API) needs the settings (or env fallbacks below):
 *   whatsapp_meta_phone_number_id / WHATSAPP_META_PHONE_NUMBER_ID
 *   whatsapp_meta_access_token    / WHATSAPP_META_ACCESS_TOKEN
 */
return [
    'driver' => env('WHATSAPP_DRIVER', 'log'),
    'meta' => [
        'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_META_ACCESS_TOKEN'),
        'api_url' => env('WHATSAPP_META_API_URL', 'https://graph.facebook.com/v20.0'),
    ],
    // Job retry schedule (seconds) for transient provider failures.
    'backoff' => [60, 300, 900, 3600],
];
