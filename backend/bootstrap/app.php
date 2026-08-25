<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/customer')
                ->group(base_path('routes/api_customer.php'));

            Route::middleware('api')
                ->prefix('api/vendor')
                ->group(base_path('routes/api_vendor.php'));

            // Public storefront routes (no auth) — deliberately outside /marketer/ prefix
            Route::middleware('api')
                ->prefix('api/public')
                ->group(base_path('routes/api_public.php'));

            // Delivery Agent mobile API
            Route::middleware('api')
                ->prefix('api/delivery')
                ->group(base_path('routes/api_delivery.php'));

            // Carrier (shipping company supervisor) API
            Route::middleware('api')
                ->prefix('api/carrier')
                ->group(base_path('routes/api_carrier.php'));

            // Travel Agency API — authenticated travel agency actions
            Route::middleware('api')
                ->prefix('api/travel-agency')
                ->group(base_path('routes/api_travel_agency.php'));

            // Partner app mobile API (vendor_api JWT guard, read-only)
            Route::middleware('api')
                ->prefix('api/partner')
                ->group(base_path('routes/api_partner.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\ResolveAppContext::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SubdomainDetect::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/payment/*',
        ]);

        $middleware->alias([
            'auth.admin' => \App\Http\Middleware\AdminAuth::class,
            'auth.optional' => \App\Http\Middleware\OptionalCustomerAuth::class,
            'admin.permission' => \App\Http\Middleware\CheckAdminPermission::class,
            'admin.vendor.scope' => \App\Http\Middleware\ScopeAdminToAssignedVendor::class,
            'vendor.auth' => \App\Http\Middleware\VendorAuth::class,
            'vendor.active' => \App\Http\Middleware\VendorActive::class,
            'vendor.onboarded' => \App\Http\Middleware\VendorOnboarded::class,
            'vendor.locale' => \App\Http\Middleware\SetVendorLocale::class,
            'vendor.can' => \App\Http\Middleware\VendorPermissionMiddleware::class,
            'auth.delivery' => \App\Http\Middleware\DeliveryAuth::class,
            'delivery.api.auth' => \App\Http\Middleware\DeliveryApiAuth::class,
            'delivery.api.active' => \App\Http\Middleware\DeliveryApiActive::class,
            'auth.travel_agency' => \App\Http\Middleware\TravelAgencyAuth::class,
            'travel_agency.can' => \App\Http\Middleware\TravelAgencyPermissionMiddleware::class,
            'auth.carrier' => \App\Http\Middleware\ShippingCompanySupervisorAuth::class,
            'carrier.api.auth' => \App\Http\Middleware\CarrierApiAuth::class,
            'carrier.api.active' => \App\Http\Middleware\CarrierApiActive::class,
            'carrier.permission' => \App\Http\Middleware\CarrierPermission::class,
            'vendor.api.auth' => \App\Http\Middleware\VendorApiAuth::class,
            'vendor.api.active' => \App\Http\Middleware\VendorApiActive::class,
            'detect.country' => \App\Http\Middleware\DetectCountry::class,
            'guest.cart.token' => \App\Http\Middleware\GuestCartToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
