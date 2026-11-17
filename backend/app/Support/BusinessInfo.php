<?php

namespace App\Support;

/**
 * Business details managed in Admin → Settings → Business Info (settings
 * category "business") and the {{placeholder}} tokens that put them into
 * admin-written HTML such as static pages (Contact Us, Terms, Privacy, ...).
 *
 * Tokens are filled at render time, so editing the business details updates
 * every page at once. Values are HTML-escaped; an unset value renders empty.
 */
class BusinessInfo
{
    /**
     * Token => [label, setting key, or [en key, ar key] for per-language values].
     *
     * @var array<string, array{0: string, 1: string|array{0: string, 1: string}}>
     */
    private const TOKENS = [
        'business_trade_name' => ['Brand / trade name', 'business_trade_name'],
        'business_legal_name' => ['Legal company name', 'business_legal_name'],
        'business_cr_number' => ['Commercial Registration number', 'business_cr_number'],
        'business_vat_number' => ['VAT number', 'business_vat_number'],
        'business_address' => ['Business address (page language)', ['business_address_en', 'business_address_ar']],
        'business_support_email' => ['Support email', 'support_email'],
        'business_support_phone' => ['Support phone', 'support_phone'],
        'business_whatsapp' => ['WhatsApp number', 'business_whatsapp'],
        'business_support_hours' => ['Support hours (page language)', ['business_support_hours_en', 'business_support_hours_ar']],
        // Link-ready forms, for use inside href="...".
        'business_support_phone_tel' => ['Phone for tel: links (digits only)', 'support_phone'],
        'business_whatsapp_link' => ['WhatsApp chat link (https://wa.me/...)', 'business_whatsapp'],
    ];

    /**
     * Token => label, for the admin help lists.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return array_map(fn (array $t) => $t[0], self::TOKENS);
    }

    /**
     * Token => raw (unescaped) value for the given language.
     *
     * @return array<string, string>
     */
    public static function values(string $locale): array
    {
        $values = [];

        foreach (self::TOKENS as $token => [, $key]) {
            $settingKey = is_array($key) ? ($locale === 'ar' ? $key[1] : $key[0]) : $key;
            $value = trim((string) setting($settingKey, ''));

            // An unset Arabic value falls back to English (and vice versa) rather than leaving a gap.
            if ($value === '' && is_array($key)) {
                $value = trim((string) setting($locale === 'ar' ? $key[0] : $key[1], ''));
            }

            $values[$token] = match ($token) {
                'business_support_phone_tel' => self::dialable($value),
                'business_whatsapp_link' => ($digits = ltrim(self::dialable($value), '+')) !== '' ? 'https://wa.me/'.$digits : '',
                default => $value,
            };
        }

        // Until the legal name is entered, sentences such as "operated by ..." use the brand name.
        if ($values['business_legal_name'] === '') {
            $values['business_legal_name'] = $values['business_trade_name'];
        }

        return $values;
    }

    /** {{token}}, or %7B%7Btoken%7D%7D: HtmlSanitizer URL-encodes the braces inside href="...". */
    private const TOKEN_PATTERN = '/(?:\{\{|%7B%7B)\s*([a-z_]+)\s*(?:\}\}|%7D%7D)/i';

    /**
     * Replaces {{token}} (spaces inside the braces allowed) with the escaped business value.
     *
     * A paragraph, list item or table row that uses a token whose value is not set yet is
     * left out entirely, so a page never shows a dangling "CR No.:" with nothing after it.
     */
    public static function fill(?string $html, string $locale): ?string
    {
        if ($html === null || ! preg_match(self::TOKEN_PATTERN, $html)) {
            return $html;
        }

        $values = self::values($locale);
        $usesUnsetToken = function (string $fragment) use ($values): bool {
            preg_match_all(self::TOKEN_PATTERN, $fragment, $matches);

            foreach ($matches[1] as $token) {
                if (($values[$token] ?? null) === '') {
                    return true;
                }
            }

            return false;
        };

        $html = preg_replace_callback(
            '#<(p|li|tr)\b[^>]*>.*?</\1>#is',
            fn (array $m) => $usesUnsetToken($m[0]) ? '' : $m[0],
            $html,
        ) ?? $html;

        return preg_replace_callback(
            self::TOKEN_PATTERN,
            fn (array $m) => array_key_exists($m[1], $values) ? e($values[$m[1]]) : $m[0],
            $html,
        );
    }

    private static function dialable(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? '' : (str_starts_with(trim($phone), '+') || str_starts_with($digits, '00') ? '+'.ltrim($digits, '0') : $digits);
    }
}
