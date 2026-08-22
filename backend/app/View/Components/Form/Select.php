<?php

namespace App\View\Components\Form;

use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Select extends Component
{
    public bool $hasError;
    public bool $isGrouped;
    public array $normalizedOptions;

    public function __construct(
        public string $name,
        public ?string $label = null,
        public mixed $options = [],
        public mixed $value = null,
        public ?string $placeholder = null,
        public bool $required = false,
        public bool $disabled = false,
        public ?string $helpText = null,
        public bool $multiple = false,
        public bool $select2 = false,
        public ?string $select2Url = null,  // for async; handled by AsyncSelect
    ) {
        $this->hasError = \Illuminate\Support\Facades\Session::has('errors')
            ? session('errors')->has($name)
            : false;

        // Normalise options: accept array, Collection, or grouped array
        $opts = $options instanceof Collection ? $options->toArray() : (array) $options;

        // Detect grouped: value is an array/collection (not a scalar)
        $this->isGrouped = !empty($opts) && is_array(reset($opts));

        if ($this->isGrouped) {
            $this->normalizedOptions = $opts;
        } else {
            $this->normalizedOptions = $opts;
        }
    }

    public function isSelected(mixed $optionValue): bool
    {
        $current = old($this->name, $this->value);

        if ($this->multiple) {
            return in_array($this->scalarize($optionValue), array_map(fn ($v) => $this->scalarize($v), (array) $current));
        }

        return $this->scalarize($optionValue) === $this->scalarize($current);
    }

    private function scalarize(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    public function render()
    {
        return view('components.form.select');
    }
}
