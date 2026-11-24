<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Order;

/**
 * Links to the Next.js storefront (FRONTEND_URL/{site_code}-{lang}/...) for emails and notifications.
 *
 * R4 (retest 2026-11-24): customer emails linked to the customer JSON API
 * (api.nawyweb.com/api/customer/v1/{country}/orders/{number}), which a browser cannot open.
 * Every customer-facing button goes through this one helper instead.
 */
class StorefrontUrl
{
    /**
     * @param  Country|string|null  $country  a Country or its site_code (e.g. "uae")
     * @param  string|null  $language  "ar" | "en" (anything else falls back to "en")
     */
    public static function to(Country|string|null $country, string $path = '', ?string $language = null): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $siteCode = $country instanceof Country ? $country->site_code : $country;
        $language = in_array($language, ['ar', 'en'], true) ? $language : 'en';
        $path = ltrim($path, '/');

        if (! $siteCode) {
            return $path === '' ? $base : $base.'/'.$path;
        }

        return $base.'/'.$siteCode.'-'.$language.($path === '' ? '' : '/'.$path);
    }

    public static function order(Order $order, ?string $language = null): string
    {
        return self::to($order->country, 'orders/'.rawurlencode($order->order_number), $language);
    }

    /**
     * Order summary page (payment details, refunds).
     */
    public static function orderSummary(Order $order, ?string $language = null): string
    {
        return self::to($order->country, 'orders/'.rawurlencode($order->order_number).'/summary', $language);
    }

    /**
     * The notifiable's preferred storefront language (customers store it in `locale`).
     */
    public static function languageOf(object $notifiable): string
    {
        $locale = $notifiable->locale ?? null;

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
    }
}
