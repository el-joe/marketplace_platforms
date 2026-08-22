<?php

namespace App\Services;

class AppContextService
{
    public function current(): string
    {
        return app('app.context') ?? 'main';
    }

    public function isNawyNow(): bool
    {
        return $this->current() === 'nawy_now';
    }

    public function isMain(): bool
    {
        return $this->current() === 'main';
    }

    public function isClassifieds(): bool
    {
        return $this->current() === 'classifieds';
    }

    public function isTravel(): bool
    {
        return $this->current() === 'travel';
    }
}
