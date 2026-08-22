<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Shared helpers for backed string enums used across the platform.
 *
 * Each enum ships its own lang file at lang/{locale}/enums/{snake_case_enum_name}.php,
 * e.g. App\Enums\OrderStatus -> lang/en/enums/order_status.php, keyed by enum value.
 */
trait EnumHelpers
{
    public function label(): string
    {
        return trans($this->langGroup() . '.' . $this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    protected function langGroup(): string
    {
        return 'enums/' . Str::snake(class_basename(static::class));
    }
}
