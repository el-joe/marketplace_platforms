<?php

use App\Http\Controllers\Api\PublicSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes — /api/public/v1/...
| No auth guard — these endpoints are fully public / unauthenticated.
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    // ── Public settings (consumed by Flutter apps) ──────────────────────────────
    Route::get('settings', [PublicSettingsController::class, 'index'])
        ->name('public.settings')
        ->middleware('throttle:60,1');
});
