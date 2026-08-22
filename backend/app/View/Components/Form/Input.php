<?php

namespace App\View\Components\Form;

use Illuminate\View\Component;

class Input extends Component
{
    public string $id;
    public string $inputClasses;
    public bool $hasError;

    public function __construct(
        public string $name,
        public ?string $label = null,
        public string $type = 'text',
        public mixed $value = null,
        public string $placeholder = '',
        public bool $required = false,
        public bool $disabled = false,
        public bool $readonly = false,
        public ?string $helpText = null,
        public ?string $prefix = null,
        public ?string $suffix = null,
        public ?int $maxlength = null,
        public string $extraClass = '',
    ) {
        $this->id = $name;
        $this->hasError = \Illuminate\Support\Facades\Session::has('errors')
            ? session('errors')->has($name)
            : false;

        $base = 'block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 '
            . 'placeholder-gray-400 transition '
            . 'focus:outline-none focus:ring-2 focus:ring-offset-0 ';

        if ($this->hasError) {
            $base .= 'border-danger-500 focus:border-danger-500 focus:ring-danger-200 ';
        } else {
            $base .= 'border-gray-300 focus:border-primary-500 focus:ring-primary-200 ';
        }

        if ($disabled) {
            $base .= 'bg-gray-50 cursor-not-allowed opacity-60 ';
        } else {
            $base .= 'bg-white ';
        }

        $this->inputClasses = trim($base . $extraClass);
    }

    public function render()
    {
        return view('components.form.input');
    }
}
