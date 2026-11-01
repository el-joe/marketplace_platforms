<?php

use App\Enums\PortalContentType;
use App\Models\PortalContent;

if (! function_exists('portal_content')) {
    /**
     * Get a localized plain-text/richtext value from the admin-editable
     * "Portal Content" CMS, falling back to a hardcoded string if the row
     * is missing, inactive, or empty. Use for text/richtext fields only —
     * for anchors/buttons use portal_link() instead.
     *
     * Usage in Blade:
     *   {{ portal_content('faq', 'faq_item_1', 'question', 'What is...?', 'ما هو...؟') }}
     */
    function portal_content(
        string $pageKey,
        string $blockKey,
        string $fieldKey,
        ?string $fallbackEn = null,
        ?string $fallbackAr = null
    ): string {
        $isAr = session('locale', 'ar') === 'ar';
        $fallback = ($isAr ? $fallbackAr : $fallbackEn) ?? $fallbackAr ?? $fallbackEn ?? '';

        $row = PortalContent::forPage($pageKey)->get($blockKey.'.'.$fieldKey);

        if (! $row || ! $row->is_active) {
            return $fallback;
        }

        $value = $isAr ? $row->value_ar : $row->value_en;

        return ($value === null || $value === '') ? $fallback : $value;
    }
}

if (! function_exists('portal_link')) {
    /**
     * Get a localized link (label + url) from the admin-editable "Portal
     * Content" CMS. Always returns ['label' => string, 'url' => string],
     * falling back to the given fallback args if the row is missing,
     * inactive, or of the wrong type.
     *
     * Usage in Blade:
     *
     *   @php($cta = portal_link('home', 'hero', 'cta_button', 'Get Started', 'ابدأ الآن', '/register'))
     *   <a href="{{ $cta['url'] }}">{{ $cta['label'] }}</a>
     */
    function portal_link(
        string $pageKey,
        string $blockKey,
        string $fieldKey,
        ?string $fallbackLabelEn = null,
        ?string $fallbackLabelAr = null,
        ?string $fallbackUrl = null
    ): array {
        $isAr = session('locale', 'ar') === 'ar';
        $fallbackLabel = ($isAr ? $fallbackLabelAr : $fallbackLabelEn) ?? $fallbackLabelAr ?? $fallbackLabelEn ?? '';
        $fallback = ['label' => $fallbackLabel, 'url' => $fallbackUrl ?? '#'];

        $row = PortalContent::forPage($pageKey)->get($blockKey.'.'.$fieldKey);

        if (! $row || ! $row->is_active || $row->type !== PortalContentType::Link) {
            return $fallback;
        }

        $label = $isAr ? $row->value_ar : $row->value_en;
        $label = ($label === null || $label === '') ? $fallbackLabel : $label;
        $url = ($row->value_url === null || $row->value_url === '') ? ($fallbackUrl ?? '#') : $row->value_url;

        return ['label' => $label, 'url' => $url];
    }
}

if (! function_exists('portal_image')) {
    /**
     * Get a localized image (src + alt) from the admin-editable "Portal
     * Content" CMS. Always returns ['src' => string, 'alt' => string],
     * falling back to the given fallback args if the row is missing,
     * inactive, or of the wrong type.
     *
     * Usage in Blade:
     *
     *   @php($img = portal_image('home', 'hero', 'photo', 'https://.../fallback.jpg', 'A delivery agent', 'مندوب توصيل'))
     *   <img src="{{ $img['src'] }}" alt="{{ $img['alt'] }}">
     */
    function portal_image(
        string $pageKey,
        string $blockKey,
        string $fieldKey,
        ?string $fallbackSrc = null,
        ?string $fallbackAltEn = null,
        ?string $fallbackAltAr = null
    ): array {
        $isAr = session('locale', 'ar') === 'ar';
        $fallbackAlt = ($isAr ? $fallbackAltAr : $fallbackAltEn) ?? $fallbackAltAr ?? $fallbackAltEn ?? '';
        $fallback = ['src' => $fallbackSrc ?? '', 'alt' => $fallbackAlt];

        $row = PortalContent::forPage($pageKey)->get($blockKey.'.'.$fieldKey);

        if (! $row || ! $row->is_active || $row->type !== PortalContentType::Image) {
            return $fallback;
        }

        $alt = $isAr ? $row->value_ar : $row->value_en;
        $alt = ($alt === null || $alt === '') ? $fallbackAlt : $alt;
        $src = ($row->value_url === null || $row->value_url === '') ? ($fallbackSrc ?? '') : $row->value_url;

        return ['src' => PortalContent::resolveUrl($src), 'alt' => $alt];
    }
}

if (! function_exists('portal_video_embed_url')) {
    /**
     * Normalise an admin-pasted YouTube/Vimeo URL (watch, youtu.be, shorts,
     * live, embed, player) into an iframe-safe embed URL. Other URLs are
     * returned unchanged so direct embeds keep working.
     *
     * Usage in Blade:
     *
     *   <iframe src="{{ portal_video_embed_url($video['url']) }}"></iframe>
     */
    function portal_video_embed_url(string $url): string
    {
        $url = trim($url);

        if (preg_match('~^https?://(?:www\.|m\.)?youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|shorts/|live/|embed/)([\w-]{11})~i', $url, $match)
            || preg_match('~^https?://youtu\.be/([\w-]{11})~i', $url, $match)) {
            $query = (string) parse_url($url, PHP_URL_QUERY);
            parse_str($query, $params);
            $start = isset($params['t']) ? (int) $params['t'] : (int) ($params['start'] ?? 0);

            return 'https://www.youtube.com/embed/'.$match[1].($start > 0 ? '?start='.$start : '');
        }

        if (preg_match('~^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d+)~i', $url, $match)) {
            return 'https://player.vimeo.com/video/'.$match[1];
        }

        return $url;
    }
}
