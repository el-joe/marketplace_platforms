<?php

namespace App\View\Components\Form;

use Illuminate\View\Component;

class RichEditor extends Component
{
    /** @var array<string, array<string, bool>> */
    public array $toolbarConfig;

    public function __construct(
        public string $name,
        public ?string $label = null,
        public ?string $value = null,
        public bool $required = false,
        public ?string $helpText = null,
        public string $profile = 'default', // minimal | default | full
        public ?string $uploadUrl = null,
        public int $minHeight = 200,
    ) {
        $this->toolbarConfig = $this->buildToolbarConfig($profile);
    }

    protected function buildToolbarConfig(string $profile): array
    {
        $base = [
            'bold' => true,
            'italic' => true,
            'bulletList' => true,
            'orderedList' => true,
            'link' => true,
        ];

        if ($profile === 'minimal') {
            return $base;
        }

        $default = array_merge($base, [
            'heading' => true,
            'blockquote' => true,
            'code' => true,
            'codeBlock' => true,
            'hardBreak' => true,
            'horizontalRule' => true,
        ]);

        if ($profile === 'default') {
            return $default;
        }

        // full
        return array_merge($default, [
            'image' => true,
            'table' => true,
            'textAlign' => true,
            'highlight' => true,
            'strike' => true,
            'underline' => true,
        ]);
    }

    public function render()
    {
        return view('components.form.rich-editor');
    }
}
