<?php

use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Customer\LiveStreamController as PublicLiveStreamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes — /api/public/v1/...
| No auth guard — these endpoints are fully public / unauthenticated.
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    // ── Live Streams (public — no auth) ──────────────────────────────────────
    Route::prefix('streams')->name('public.streams.')->group(function () {
        Route::get('/',                   [PublicLiveStreamController::class, 'index'])->name('index');
        Route::get('/{stream}',           [PublicLiveStreamController::class, 'show'])->name('show');

        // Mutation endpoints — throttled to prevent abuse
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/{stream}/comments', [PublicLiveStreamController::class, 'comment'])->name('comment');
            Route::post('/{stream}/like',     [PublicLiveStreamController::class, 'like'])->name('like');
        });

        // Signal — high frequency during WebRTC negotiation; generous limit
        Route::middleware('throttle:120,1')->group(function () {
            Route::post('/{stream}/signal', [PublicLiveStreamController::class, 'signal'])->name('signal');
        });
    });


    // ── Public settings (consumed by Flutter apps) ──────────────────────────────
    Route::get('settings', [PublicSettingsController::class, 'index'])
        ->name('public.settings')
        ->middleware('throttle:60,1');
});
