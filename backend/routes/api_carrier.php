<?php

use App\Http\Controllers\Carrier\Api\AgentController;
use App\Http\Controllers\Carrier\Api\AssignmentController;
use App\Http\Controllers\Carrier\Api\AuthController;
use App\Http\Controllers\Carrier\Api\DashboardController;
use App\Http\Controllers\Carrier\Api\NotificationController;
use App\Http\Controllers\Carrier\Api\ReportController;
use App\Http\Controllers\Carrier\Api\SupervisorController;
use App\Http\Controllers\Carrier\Api\ZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    Route::prefix('auth')->name('carrier.api.auth.')->group(function (): void {
        Route::post('login',         [AuthController::class, 'login'])->name('login')
             ->middleware('throttle:10,1');
        Route::post('refresh-token', [AuthController::class, 'refresh'])->name('refresh');
    });

    Route::middleware(['carrier.api.auth', 'carrier.api.active'])
         ->group(function (): void {

        Route::prefix('auth')->name('carrier.api.auth.')->group(function (): void {
            Route::post('logout',        [AuthController::class, 'logout'])->name('logout');
            Route::get('me',             [AuthController::class, 'me'])->name('me');
            Route::post('device-token',  [AuthController::class, 'registerDeviceToken'])->name('device-token.store');
            Route::delete('device-token',[AuthController::class, 'removeDeviceToken'])->name('device-token.destroy');
        });

        Route::get('dashboard', [DashboardController::class, 'index'])->name('carrier.api.dashboard');
        Route::get('zones', [ZoneController::class, 'index'])->name('carrier.api.zones.index');

        Route::prefix('notifications')->name('carrier.api.notifications.')->group(function (): void {
            Route::get('/',             [NotificationController::class, 'index'])->name('index');
            Route::get('unread-count',  [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::put('read-all',      [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::put('{id}/read',     [NotificationController::class, 'markRead'])->name('read');
        });

        Route::prefix('agents')->name('carrier.api.agents.')->group(function (): void {
            Route::get('/',                     [AgentController::class, 'index'])->name('index');
            Route::post('/',                    [AgentController::class, 'store'])->name('store');
            Route::get('{id}',                  [AgentController::class, 'show'])->name('show');
            Route::put('{id}',                  [AgentController::class, 'update'])->name('update');
            Route::patch('{id}/zone',           [AgentController::class, 'assignZone'])->name('assign-zone');
            Route::patch('{id}/suspend',        [AgentController::class, 'suspend'])->name('suspend');
            Route::patch('{id}/activate',       [AgentController::class, 'activate'])->name('activate');
            Route::post('{id}/reset-password',  [AgentController::class, 'resetPassword'])->name('reset-password');
        });

        Route::prefix('assignments')->name('carrier.api.assignments.')->group(function (): void {
            Route::get('unassigned',            [AssignmentController::class, 'unassigned'])->name('unassigned');
            Route::post('{shipment}/assign',    [AssignmentController::class, 'assign'])->name('assign');
            Route::get('/',                     [AssignmentController::class, 'index'])->name('index');
            Route::get('{assignment}',          [AssignmentController::class, 'show'])->name('show');
            Route::post('{assignment}/reassign',[AssignmentController::class, 'reassign'])->name('reassign');
        });

        Route::prefix('reports')->name('carrier.api.reports.')->group(function (): void {
            Route::get('orders',         [ReportController::class, 'orders'])->name('orders');
            Route::get('earnings',       [ReportController::class, 'earnings'])->name('earnings');
            Route::get('payouts',        [ReportController::class, 'payouts'])->name('payouts');
            Route::get('cod-settlements',[ReportController::class, 'codSettlements'])->name('cod-settlements');
            Route::get('performance',    [ReportController::class, 'performance'])->name('performance');
            Route::get('performance/trend', [ReportController::class, 'performanceTrend'])->name('performance.trend');
            Route::get('claims',         [ReportController::class, 'claims'])->name('claims');
            Route::get('claims/{claim}', [ReportController::class, 'claimShow'])->name('claims.show');
        });

        Route::prefix('supervisors')->name('carrier.api.supervisors.')->group(function (): void {
            Route::get('/',         [SupervisorController::class, 'index'])->name('index');
            Route::post('/',        [SupervisorController::class, 'store'])->name('store');
            Route::put('{id}',      [SupervisorController::class, 'update'])->name('update');
            Route::delete('{id}',   [SupervisorController::class, 'destroy'])->name('destroy');
        });
    });
});
