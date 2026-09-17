<?php

namespace App\DTO;

/**
 * A single resolved image, always carrying an absolute URL.
 */
class ImageDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly array $alt,
        public readonly bool $isPrimary,
        public readonly int $position,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'alt' => $this->alt,
            'is_primary' => $this->isPrimary,
            'position' => $this->position,
        ];
    }
}
