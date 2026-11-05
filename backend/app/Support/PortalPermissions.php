<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;

/**
 * Single source of truth for the portal-content permission set (guard `admin`).
 *
 * The permissions live in the existing spatie/laravel-permission tables; this class only
 * names them, so the seeder, the migration, the controllers and the role/staff forms agree.
 * Labels and descriptions are translated under `admin.portal_permissions`.
 */
final class PortalPermissions
{
    public const GUARD = 'admin';

    public const VIEW = 'portal_content.view';

    public const CREATE = 'portal_content.create';

    public const EDIT = 'portal_content.edit';

    public const DELETE = 'portal_content.delete';

    public const PUBLISH = 'portal_content.publish';

    public const MANAGE = 'portal_content.manage';

    public const ANALYTICS_VIEW = 'portal_analytics.view';

    /** Every permission in the "Portal Content" group, in the order shown to administrators. */
    public const CONTENT = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
        self::PUBLISH,
        self::MANAGE,
    ];

    /** Everything this feature owns (content + analytics, which WP4 consumes). */
    public const ALL = [...self::CONTENT, self::ANALYTICS_VIEW];

    /**
     * Permissions that may be granted directly to one employee (in addition to roles).
     *
     * @return list<string>
     */
    public static function directlyAssignable(): array
    {
        return self::CONTENT;
    }

    /** Idempotently create every portal permission for the admin guard. */
    public static function ensureExist(): void
    {
        foreach (self::ALL as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }
    }

    /** Translated short label, e.g. "View portal content". */
    public static function label(string $permission): string
    {
        $key = self::translationKey($permission, 'label');
        $label = __($key);

        return $label === $key ? ucwords(str_replace(['_', '.'], ' ', $permission)) : $label;
    }

    /** Translated plain-language description of what the permission allows. */
    public static function description(string $permission): ?string
    {
        $key = self::translationKey($permission, 'description');
        $description = __($key);

        return $description === $key ? null : $description;
    }

    /** Dots are nesting separators in translation keys, so `portal_content.view` is stored as `portal_content_view`. */
    private static function translationKey(string $permission, string $part): string
    {
        return 'admin.portal_permissions.items.'.str_replace('.', '_', $permission).'.'.$part;
    }
}
