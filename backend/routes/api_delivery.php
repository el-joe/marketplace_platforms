<?php

use App\Http\Controllers\Delivery\Api\AssignmentController;
use App\Http\Controllers\Delivery\Api\AuthController;
use App\Http\Controllers\Delivery\Api\EarningsController;
use App\Http\Controllers\Delivery\Api\LocationController;
use App\Http\Controllers\Delivery\Api\NotificationController;
use App\Http\Controllers\Delivery\Api\ProfileController;
use App\Http\Controllers\Delivery\Api\ShiftController;
use App\Http\Controllers\Delivery\Api\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // ── Auth (public) ─────────────────────────────────────────────────────
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('refresh-token', [AuthController::class, 'refresh']);
    });

    // ── Protected ─────────────────────────────────────────────────────────
    Route::middleware(['delivery.api.auth', 'delivery.api.active'])->group(function (): void {

        // Auth
        Route::prefix('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('device-token', [AuthController::class, 'registerDeviceToken']);
            Route::delete('device-token', [AuthController::class, 'removeDeviceToken']);
        });

        // Profile
        Route::prefix('profile')->group(function (): void {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::put('password', [ProfileController::class, 'updatePassword']);
            Route::get('documents', [ProfileController::class, 'documents']);
            Route::post('documents/{type}/reupload', [ProfileController::class, 'reuploadDocument']);
        });

        // Shift management
        Route::prefix('shift')->group(function (): void {
            Route::post('start', [ShiftController::class, 'start']);
            Route::post('end', [ShiftController::class, 'end']);
            Route::put('availability', [ShiftController::class, 'setAvailability']);
        });

        // Location tracking
        Route::put('location', [LocationController::class, 'update']);

        // Assignments — delivery lifecycle
        Route::prefix('assignments')->group(function (): void {
            Route::get('/', [AssignmentController::class, 'index']);
            Route::get('{assignment}', [AssignmentController::class, 'show']);
            Route::post('{assignment}/accept', [AssignmentController::class, 'accept']);
            Route::post('{assignment}/picked-up', [AssignmentController::class, 'pickedUp']);
            Route::post('{assignment}/verify-otp', [AssignmentController::class, 'verifyOtp']);
            Route::post('{assignment}/deliver', [AssignmentController::class, 'deliver']);
            Route::post('{assignment}/fail', [AssignmentController::class, 'fail']);
        });

        // Earnings
        Route::get('earnings', [EarningsController::class, 'index']);

        // Support tickets
        Route::prefix('support/tickets')->group(function (): void {
            Route::get('/', [SupportTicketController::class, 'index']);
            Route::post('/', [SupportTicketController::class, 'store']);
            Route::get('{ticketNumber}', [SupportTicketController::class, 'show']);
            Route::post('{ticketNumber}/messages', [SupportTicketController::class, 'addMessage']);
            Route::put('{ticketNumber}/rate', [SupportTicketController::class, 'rate']);
        });

        // Notifications
        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::put('read-all', [NotificationController::class, 'markAllRead']);
            Route::put('{id}/read', [NotificationController::class, 'markRead']);
        });
    });
});
