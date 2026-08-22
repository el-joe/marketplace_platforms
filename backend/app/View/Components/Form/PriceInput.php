<?php

namespace App\View\Components\Form;

use Illuminate\View\Component;

class PriceInput extends Component
{
    public bool $hasError;
    /** Display value (decimal, not cents) */
    public string $displayValue;
    /** Stored value (cents) */
    public int $centsValue;
    public string $currencySymbol;

    public function __construct(
        public string $name,
        public ?string $label = null,
        public int $value = 0,   // cents
        public string $currency = 'EGP',
        public bool $required = false,
        public ?string $helpText = null,
        public int $min = 0,   // cents
        public ?int $max = null, // cents, null = no max
    ) {
        $this->hasError = \Illuminate\Support\Facades\Session::has('errors')
            ? session('errors')->has($name)
            : false;

        $oldCents = old($name);
        $this->centsValue = $oldCents !== null ? (int) $oldCents : $value;
        $this->displayValue = number_format($this->centsValue / 100, 2, '.', '');

        $this->currencySymbol = match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'SAR' => 'SR',
            'AED' => 'AED',
            default => $currency,
        };
    }

    public function minDisplay(): float
    {
        return $this->min / 100;
    }

    public function maxDisplay(): ?float
    {
        return $this->max !== null ? $this->max / 100 : null;
    }

    public function render()
    {
        return view('components.form.price-input');
    }
}
