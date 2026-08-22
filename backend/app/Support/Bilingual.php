<?php

namespace App\Support;

/**
 * Central helper for the {field}_ar / {field}_en column convention used
 * throughout the catalog. Replaces the many scattered locale-ternary and
 * manual dual-key blocks with one call site.
 */
class Bilingual
{
    /**
     * Build a {"ar": ..., "en": ...} pair from a model, array, or stdClass
     * that carries {field}_ar / {field}_en attributes.
     */
    public static function pair(mixed $source, string $field): array
    {
        return [
            'ar' => static::get($source, $field . '_ar'),
            'en' => static::get($source, $field . '_en'),
        ];
    }

    /**
     * Same as pair(), but reads from two explicit keys instead of a shared
     * {field} prefix (e.g. differing column names).
     */
    public static function pairFromKeys(mixed $source, string $arKey, string $enKey): array
    {
        return [
            'ar' => static::get($source, $arKey),
            'en' => static::get($source, $enKey),
        ];
    }

    protected static function get(mixed $source, string $key): mixed
    {
        if (is_array($source)) {
            return $source[$key] ?? null;
        }

        return $source->{$key} ?? null;
    }
}
