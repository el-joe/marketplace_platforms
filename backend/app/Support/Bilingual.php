<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            'ar' => static::get($source, $field.'_ar'),
            'en' => static::get($source, $field.'_en'),
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

    /**
     * The value of {field} in the current (or given) locale, falling back to
     * the other language when the localized value is empty.
     */
    public static function localized(mixed $source, string $field, ?string $locale = null): string
    {
        [$primary, $fallback] = static::localeOrder($locale);

        $value = static::get($source, $field.'_'.$primary);

        if ($value === null || trim((string) $value) === '') {
            $value = static::get($source, $field.'_'.$fallback);
        }

        return (string) ($value ?? '');
    }

    /**
     * SQL expression yielding {column}_{locale} with fallback to the other
     * language, e.g. COALESCE(NULLIF(products.name_ar, ''), products.name_en).
     * Use it in select/orderBy so sorting matches what the admin sees.
     */
    public static function column(string $qualifiedBase, ?string $locale = null, ?string $alias = null): ExpressionContract
    {
        [$primary, $fallback] = static::localeOrder($locale);

        $grammar = DB::connection()->getQueryGrammar();
        $primaryColumn = $grammar->wrap($qualifiedBase.'_'.$primary);
        $fallbackColumn = $grammar->wrap($qualifiedBase.'_'.$fallback);

        $sql = "COALESCE(NULLIF({$primaryColumn}, ''), {$fallbackColumn})";

        if ($alias !== null) {
            $sql .= ' as '.$grammar->wrap($alias);
        }

        return DB::raw($sql);
    }

    /**
     * Constrain a query to rows whose {base}_en OR {base}_ar contains the term,
     * so admin search works whichever language the admin types in.
     */
    public static function whereLike(BuilderContract $query, string $qualifiedBase, string $term, string $boolean = 'and'): BuilderContract
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function ($sub) use ($qualifiedBase, $like) {
            $sub->where($qualifiedBase.'_en', 'like', $like)
                ->orWhere($qualifiedBase.'_ar', 'like', $like);
        }, null, null, $boolean);
    }

    /**
     * id => localized label map for selects, ordered by the label the admin sees.
     *
     * @return Collection<string|int, string>
     */
    public static function options(BuilderContract $query, string $base = 'name', string $key = 'id'): Collection
    {
        return $query
            ->select($key)
            ->addSelect(static::column($base, null, 'localized_label'))
            ->orderBy('localized_label')
            ->pluck('localized_label', $key);
    }

    /**
     * @return array{0: string, 1: string} [primary locale, fallback locale]
     */
    protected static function localeOrder(?string $locale): array
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar' ? ['ar', 'en'] : ['en', 'ar'];
    }

    protected static function get(mixed $source, string $key): mixed
    {
        if (is_array($source)) {
            return $source[$key] ?? null;
        }

        return $source->{$key} ?? null;
    }
}
