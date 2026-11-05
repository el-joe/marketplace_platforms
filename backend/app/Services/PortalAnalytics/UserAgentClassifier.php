<?php

namespace App\Services\PortalAnalytics;

/**
 * Cheap user-agent heuristics: crawler detection and a coarse device type.
 * Only the coarse result is stored; the user agent itself is never persisted.
 */
final class UserAgentClassifier
{
    public static function isBot(?string $userAgent): bool
    {
        $userAgent = strtolower(trim((string) $userAgent));

        if ($userAgent === '') {
            return true;
        }

        foreach ((array) config('portal_analytics.bot_user_agents', []) as $fragment) {
            if ($fragment !== '' && str_contains($userAgent, strtolower($fragment))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return 'mobile'|'tablet'|'desktop'
     */
    public static function deviceType(?string $userAgent): string
    {
        $userAgent = strtolower((string) $userAgent);

        if (preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|windows phone/', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
