<?php

use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\CarrierApiActive;
use App\Http\Middleware\CarrierApiAuth;
use App\Http\Middleware\CarrierPermission;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\DeliveryApiActive;
use App\Http\Middleware\DeliveryApiAuth;
use App\Http\Middleware\DeliveryAuth;
use App\Http\Middleware\DetectCountry;
use App\Http\Middleware\EnforceVendorCategoryContracts;
use App\Http\Middleware\GuestCartToken;
use App\Http\Middleware\MarketerApiActive;
use App\Http\Middleware\MarketerApiAuth;
use App\Http\Middleware\MarketerAuth;
use App\Http\Middleware\OptionalCustomerAuth;
use App\Http\Middleware\ResolveAppContext;
use App\Http\Middleware\ScopeAdminToAssignedVendor;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetVendorLocale;
use App\Http\Middleware\ShippingCompanySupervisorAuth;
use App\Http\Middleware\SubdomainDetect;
use App\Http\Middleware\TravelAgencyAuth;
use App\Http\Middleware\TravelAgencyPermissionMiddleware;
use App\Http\Middleware\VendorActive;
use App\Http\Middleware\VendorApiActive;
use App\Http\Middleware\VendorApiAuth;
use App\Http\Middleware\VendorAuth;
use App\Http\Middleware\VendorDualAuth;
use App\Http\Middleware\VendorOnboarded;
use App\Http\Middleware\VendorPermissionMiddleware;
use App\Http\Middleware\VendorTokenAuth;
use App\Http\Middleware\VendorTypeMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
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

            // Marketer Flutter app API (marketer_api JWT guard)
            Route::middleware('api')
                ->prefix('api/marketer')
                ->group(base_path('routes/api_marketer.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            HandleCors::class,
            ResolveAppContext::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            SubdomainDetect::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/payment/*',
        ]);

        $middleware->alias([
            'auth.admin' => AdminAuth::class,
            'auth.optional' => OptionalCustomerAuth::class,
            'admin.permission' => CheckAdminPermission::class,
            'admin.vendor.scope' => ScopeAdminToAssignedVendor::class,
            'vendor.auth' => VendorAuth::class,
            'vendor.active' => VendorActive::class,
            'vendor.onboarded' => VendorOnboarded::class,
            'vendor.locale' => SetVendorLocale::class,
            'vendor.can' => VendorPermissionMiddleware::class,
            'vendor.type' => VendorTypeMiddleware::class,
            'vendor.contracts.enforce' => EnforceVendorCategoryContracts::class,
            'auth.delivery' => DeliveryAuth::class,
            'delivery.api.auth' => DeliveryApiAuth::class,
            'delivery.api.active' => DeliveryApiActive::class,
            'auth.travel_agency' => TravelAgencyAuth::class,
            'travel_agency.can' => TravelAgencyPermissionMiddleware::class,
            'auth.carrier' => ShippingCompanySupervisorAuth::class,
            'carrier.api.auth' => CarrierApiAuth::class,
            'carrier.api.active' => CarrierApiActive::class,
            'carrier.permission' => CarrierPermission::class,
            'vendor.api.auth' => VendorApiAuth::class,
            'vendor.api.active' => VendorApiActive::class,
            'vendor.token.auth' => VendorTokenAuth::class,
            'vendor.dual.auth' => VendorDualAuth::class,
            'detect.country' => DetectCountry::class,
            'guest.cart.token' => GuestCartToken::class,
            'auth.marketer' => MarketerAuth::class,
            'marketer.api.auth' => MarketerApiAuth::class,
            'marketer.api.active' => MarketerApiActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
