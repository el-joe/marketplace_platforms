<?php

namespace App\View\Components\Form;

use Illuminate\View\Component;

class PriceInput extends Component
{
    public bool $hasError;
    public int $storedValue;
    public string $currencySymbol;

    public function __construct(
        public string $name,
        public ?string $label = null,
        public int $value = 0,
        public string $currency = 'EGP',
        public bool $required = false,
        public ?string $helpText = null,
        public int $min = 0,
        public ?int $max = null,
    ) {
        $this->hasError = \Illuminate\Support\Facades\Session::has('errors')
            ? session('errors')->has($name)
            : false;

        $old = old($name);
        $this->storedValue = $old !== null ? (int) $old : $value;

        $this->currencySymbol = match (strtoupper($currency)) {
            'USD' => '$', 'EUR' => '€', 'GBP' => '£',
            'SAR' => 'SR', 'AED' => 'AED',
            default => $currency,
        };
    }

    public function render()
    {
        return view('components.form.price-input');
    }
}
