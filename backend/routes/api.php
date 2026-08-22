<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Three top-level API namespaces:
|
|   /api/v1/{country}/...   → Customer website + mobile (Sanctum, ability: customer)
|   /api/v1/vendor/...      → Vendor mobile app (Sanctum, ability: vendor)
|   /api/v1/delivery/...    → Delivery agent app (Sanctum, ability: delivery)
|
| Controllers, middleware, and form requests are added in Stage 2/3.
| This file is intentionally a skeleton so the routing layer wires up
| immediately and follows the structure defined in the API prompt library.
|
*/

// ═══════════════════════════════════════════════════════════════════════
// Shared / Internal Lookups
// ═══════════════════════════════════════════════════════════════════════
Route::get('cities', function (\Illuminate\Http\Request $request) {
    $request->validate(['country_id' => 'required|uuid']);

    return \App\Models\City::where('country_id', $request->country_id)
        ->where('is_active', true)
        ->orderBy('name_en')
        ->get(['id', 'name_en', 'name_ar']);
});

// ═══════════════════════════════════════════════════════════════════════
// Marketer Referral Tracking
// ═══════════════════════════════════════════════════════════════════════
Route::get('r/{code}', [\App\Http\Controllers\Api\ReferralTrackingController::class, 'track'])
    ->name('api.referral.track');

// ═══════════════════════════════════════════════════════════════════════
// Customer / Website / App
// ═══════════════════════════════════════════════════════════════════════
Route::prefix('v1/{country}')
    // ->middleware('country.detect') // added in Stage 2 with DetectCountry middleware
    ->group(function (): void {
        // Public auth, catalog, cart endpoints — wired in Stage 3
        // Route::post('auth/login', [CustomerAuthController::class, 'login']);
        // Route::get('products', [ProductController::class, 'index']);
    
        Route::middleware('auth:sanctum')->group(function (): void {
            // Authenticated customer endpoints — wired in Stage 3
        });
    });

// ═══════════════════════════════════════════════════════════════════════
// Vendor Mobile App
// ═══════════════════════════════════════════════════════════════════════
Route::prefix('v1/vendor')->group(function (): void {
    // Route::post('auth/login', [VendorAuthController::class, 'login'])
    //     ->middleware('throttle:5,15');

    Route::middleware(['auth:sanctum', 'ability:vendor'])->group(function (): void {
        // Vendor endpoints — wired in Stage 3
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Delivery Agent App
// ═══════════════════════════════════════════════════════════════════════
Route::prefix('v1/delivery')->group(function (): void {
    // Route::post('auth/login', [DeliveryAuthController::class, 'login'])
    //     ->middleware('throttle:5,15');

    Route::middleware(['auth:sanctum', 'ability:delivery'])->group(function (): void {
        // Delivery agent endpoints — wired in Stage 3
    });
});
