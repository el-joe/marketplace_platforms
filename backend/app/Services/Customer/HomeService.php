<?php

namespace App\Services\Customer;

use App\Models\Country;
use App\Services\Shared\PageBuilderService;

class HomeService
{
    public function __construct(
        private readonly PageBuilderService $pageBuilder,
    ) {}

    public function getHomeData(
        Country $country,
        string $deviceTarget = 'all',
        string $audience = 'guest',
    ): array {
        return [
            'page_builder' => $this->pageBuilder->resolve($country, 'home', null, $deviceTarget, $audience),
            'meta' => [
                'country_code' => strtolower($country->iso_code_2),
                'currency' => $country->currency_code,
                'locale' => $country->default_locale ?? app()->getLocale(),
            ],
        ];
    }
}
