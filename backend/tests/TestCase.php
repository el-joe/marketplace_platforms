<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Blade layouts call @vite(); CI/cloud containers have no `npm run build`
        // manifest. Stubbing the directive only removes the asset-manifest lookup;
        // routing, controllers, queries and view logic still execute for real.
        $this->withoutVite();
    }
}
