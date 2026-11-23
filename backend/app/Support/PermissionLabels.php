<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Translated labels for admin permissions on the role form (lang/{en,ar}/permissions.php).
 * A permission `group.action` gets its group from `permissions.groups[group]` and its
 * label from `permissions.permissions[name]`, then `permissions.actions[action]`.
 * Portal-content permissions keep their richer labels from PortalPermissions.
 *
 * Seller (`vendor`) and travel-agency role forms pass their guard: `permissions.guards.{guard}`
 * is checked first (own group names / labels), then the shared actions.
 */
final class PermissionLabels
{
    public static function group(string $permissionOrPrefix, ?string $locale = null, ?string $guard = null): string
    {
        $prefix = self::prefix($permissionOrPrefix);

        if ($guard !== null && $guard !== 'admin') {
            return self::lookup("guards.{$guard}.groups", $prefix, $locale) ?? Str::headline($prefix);
        }

        // Seller-portal groups keep their established labels (shared with the staff form).
        $portalGroupKey = match ($prefix) {
            'portal_content' => 'admin.portal_permissions.group',
            'portal_analytics' => 'admin.portal_permissions.analytics_group',
            default => null,
        };
        if ($portalGroupKey !== null && trans($portalGroupKey, [], $locale) !== $portalGroupKey) {
            return trans($portalGroupKey, [], $locale);
        }

        return self::lookup('groups', $prefix, $locale) ?? Str::headline($prefix);
    }

    public static function label(string $permission, ?string $locale = null, ?string $guard = null): string
    {
        if ($guard !== null && $guard !== 'admin') {
            return self::lookup("guards.{$guard}.permissions", $permission, $locale)
                ?? self::lookup('actions', self::action($permission), $locale)
                ?? Str::headline(str_replace('.', ' ', self::action($permission)));
        }

        if (in_array($permission, PortalPermissions::ALL, true)) {
            return PortalPermissions::label($permission);
        }

        return self::lookup('permissions', $permission, $locale)
            ?? self::lookup('actions', self::action($permission), $locale)
            ?? Str::headline(str_replace('.', ' ', self::action($permission)));
    }

    /**
     * Whether both the group and the permission resolve to a real translation
     * (not the headline fallback) in the given locale.
     */
    public static function isTranslated(string $permission, string $locale): bool
    {
        $hasGroup = self::lookup('groups', self::prefix($permission), $locale) !== null;
        if (in_array($permission, PortalPermissions::ALL, true)) {
            $key = 'admin.portal_permissions.items.'.str_replace('.', '_', $permission).'.label';
            $hasLabel = trans($key, [], $locale) !== $key;
        } else {
            $hasLabel = (self::lookup('permissions', $permission, $locale) ?? self::lookup('actions', self::action($permission), $locale)) !== null;
        }

        return $hasGroup && $hasLabel;
    }

    private static function prefix(string $permission): string
    {
        return explode('.', $permission, 2)[0];
    }

    private static function action(string $permission): string
    {
        return explode('.', $permission, 2)[1] ?? $permission;
    }

    /** Array lookup instead of dotted keys: action keys such as `documents.verify` contain dots. */
    private static function lookup(string $section, string $key, ?string $locale): ?string
    {
        $entries = trans('permissions.'.$section, [], $locale);

        return is_array($entries) && isset($entries[$key]) && $entries[$key] !== '' ? $entries[$key] : null;
    }
}
