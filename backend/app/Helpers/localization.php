<?php

use App\Support\Bilingual;

if (! function_exists('localized')) {
    /**
     * {field}_{current locale} of a model, row object or array, falling back
     * to the other language when empty. Works for Eloquent models and plain
     * query-builder rows alike, e.g. localized($product) / localized($row, 'title').
     */
    function localized(mixed $source, string $field = 'name'): string
    {
        return $source === null ? '' : Bilingual::localized($source, $field);
    }
}
