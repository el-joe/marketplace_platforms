<?php

namespace App\View\Components\Form;

use Illuminate\View\Component;

class FileUpload extends Component
{
    public string $existingJson;

    public function __construct(
        public string $name,
        public ?string $label = null,
        public bool $multiple = false,
        public string $accept = '*',
        public int $maxSizeMb = 10,
        public int $maxFiles = 10,
        public array $existingFiles = [],
        public ?string $uploadUrl = null,
        public ?string $deleteUrl = null,
        public bool $required = false,
        public ?string $helpText = null,
    ) {
        $this->existingJson = json_encode(
            array_map(fn($f) => is_array($f) ? $f : ['id' => $f, 'url' => $f], $existingFiles),
            JSON_THROW_ON_ERROR,
        );
    }

    public function maxSizeBytes(): int
    {
        return $this->maxSizeMb * 1024 * 1024;
    }

    public function render()
    {
        return view('components.form.file-upload');
    }
}
