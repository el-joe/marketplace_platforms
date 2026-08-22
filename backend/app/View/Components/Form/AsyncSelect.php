<?php

namespace App\View\Components\Form;

use Illuminate\View\Component;

class AsyncSelect extends Component
{
    public string $configJson;

    public function __construct(
        public string $name,
        public ?string $label = null,
        public mixed $value = null,
        public ?string $valueLabel = null,   // label shown for pre-selected value
        public string $searchUrl = '',
        public string $searchParam = 'q',
        public bool $multiple = false,
        public string $placeholder = 'Type to search…',
        public bool $required = false,
        public ?string $helpText = null,
        public int $minLength = 2,
        public int $delay = 300,
    ) {
        $this->configJson = json_encode([
            'url' => $searchUrl,
            'param' => $searchParam,
            'multiple' => $multiple,
            'minLength' => $minLength,
            'delay' => $delay,
        ], JSON_THROW_ON_ERROR);
    }

    public function render()
    {
        return view('components.form.async-select');
    }
}
