<?php

use App\Http\Controllers\Carrier\AgentController;
use App\Http\Controllers\Carrier\AuthController;
use App\Http\Controllers\Carrier\CompanyController;
use App\Http\Controllers\Carrier\DashboardController;
use App\Http\Controllers\Carrier\FallbackStatusController;
use App\Http\Controllers\Carrier\LiveMapController;
use App\Http\Controllers\Carrier\NotificationController;
use App\Http\Controllers\Carrier\ShipmentController;
use App\Http\Controllers\Carrier\SupervisorController;
use App\Http\Controllers\Carrier\SupportTicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Carrier API Routes — /api/carrier/v1/...
| Guard: shipping_supervisor_api (JWT via tymon/jwt-auth)
| Authenticated middleware stack: carrier.api.auth → carrier.api.active
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    // ── Auth (public) ─────────────────────────────────────────────────────────
    Route::prefix('auth')->name('carrier.auth.')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->name('login')
            ->middleware('throttle:10,1');
    });

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware(['carrier.api.auth', 'carrier.api.active'])->group(function (): void {

        Route::prefix('auth')->name('carrier.auth.')->group(function (): void {
            Route::post('logout',        [AuthController::class, 'logout'])->name('logout');
            Route::post('refresh-token', [AuthController::class, 'refresh'])->name('refresh');
            Route::get('me',             [AuthController::class, 'me'])->name('me');
            Route::post('device-token',   [AuthController::class, 'registerDeviceToken'])->name('device-token.store');
            Route::delete('device-token', [AuthController::class, 'removeDeviceToken'])->name('device-token.destroy');
        });

        // ── Dashboard (no extra permission — authenticated only) ──────────────
        Route::get('dashboard', [DashboardController::class, 'index'])->name('carrier.dashboard');

        // ── Company Profile ───────────────────────────────────────────────────
        // // VERIFY: PUT /company and PUT /company/served-areas have no explicit
        // permission gate in the schema. See controller comments for details.
        Route::prefix('company')->name('carrier.company.')->group(function (): void {
            Route::get('/',            [CompanyController::class, 'show'])->name('show');
            Route::put('/',            [CompanyController::class, 'update'])->name('update');
            Route::get('served-areas', [CompanyController::class, 'servedAreas'])->name('served-areas.show');
            Route::put('served-areas', [CompanyController::class, 'updateServedAreas'])->name('served-areas.update');
        });

        // ── Live Map (requires view_orders OR view_reports — checked in controller) ──
        Route::get('agents/live-map', [LiveMapController::class, 'index'])->name('carrier.agents.live-map');

        // ── Notifications ─────────────────────────────────────────────────────
        Route::prefix('notifications')->name('carrier.notifications.')->group(function (): void {
            Route::get('/',              [NotificationController::class, 'index'])->name('index');
            // unread-count and read-all must be declared before {id}/read to avoid route ambiguity
            Route::get('unread-count',   [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::put('read-all',       [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::put('{id}/read',      [NotificationController::class, 'markRead'])->name('read');
        });

        // ── Fallback Visibility (read-only; any authenticated supervisor) ─────
        Route::get('fallback-status', [FallbackStatusController::class, 'index'])->name('carrier.fallback-status');

        // ── Agent Roster (requires manage_agents permission) ──────────────────
        Route::middleware('carrier.permission:manage_agents')
            ->prefix('agents')
            ->name('carrier.agents.')
            ->group(function (): void {
                Route::get('/',         [AgentController::class, 'index'])->name('index');
                Route::post('/',        [AgentController::class, 'store'])->name('store');
                Route::get('{id}',      [AgentController::class, 'show'])->name('show');
                Route::put('{id}',      [AgentController::class, 'update'])->name('update');
                Route::delete('{id}',   [AgentController::class, 'destroy'])->name('destroy');
                Route::get('{id}/documents', [AgentController::class, 'documents'])->name('documents');
            });

        // ── Agent Performance & Assignment View (requires view_orders) ────────
        Route::middleware('carrier.permission:view_orders')
            ->prefix('agents')
            ->name('carrier.agents.')
            ->group(function (): void {
                Route::get('{id}/assignments', [AgentController::class, 'assignments'])->name('assignments');
            });

        Route::middleware('carrier.permission:view_reports')
            ->prefix('agents')
            ->name('carrier.agents.')
            ->group(function (): void {
                Route::get('{id}/performance', [AgentController::class, 'performance'])->name('performance');
            });

        // ── Assignment Reassignment (requires assign_agents permission) ───────
        // Supervisors may only reassign between agents belonging to their own company.
        Route::middleware('carrier.permission:assign_agents')
            ->name('carrier.assignments.')
            ->group(function (): void {
                Route::post('assignments/{id}/reassign', [AgentController::class, 'reassign'])->name('reassign');
            });

        // ── Shipments (requires view_orders permission) ────────────────────────
        // Scoped to the carriers linked to the supervisor's company via
        // shipping_carriers.shipping_company_id.
        Route::middleware('carrier.permission:view_orders')
            ->prefix('shipments')
            ->name('carrier.shipments.')
            ->group(function (): void {
                Route::get('unassigned', [ShipmentController::class, 'unassigned'])->name('unassigned');
                Route::get('{id}', [ShipmentController::class, 'show'])->name('show');
            });

        // ── Assign agent to a shipment (requires assign_agents permission) ────
        Route::middleware('carrier.permission:assign_agents')
            ->prefix('shipments')
            ->name('carrier.shipments.')
            ->group(function (): void {
                Route::post('{id}/assign-agent', [ShipmentController::class, 'assignAgent'])->name('assign-agent');
            });

        // ── Support Tickets (any authenticated supervisor) ────────────────────
        Route::prefix('support/tickets')->name('carrier.support.tickets.')->group(function (): void {
            Route::get('/',                          [SupportTicketController::class, 'index'])->name('index');
            Route::post('/',                         [SupportTicketController::class, 'store'])->name('store');
            Route::get('{ticketNumber}',             [SupportTicketController::class, 'show'])->name('show');
            Route::post('{ticketNumber}/messages',   [SupportTicketController::class, 'addMessage'])->name('messages.store');
            Route::put('{ticketNumber}/rate',        [SupportTicketController::class, 'rate'])->name('rate');
        });

        // ── Supervisor Management (requires manage_agents permission) ─────────
        // // VERIFY: manage_agents is used as the owner-gate for supervisor
        // management (consistent with the web portal). The schema has no distinct
        // "manage_supervisors" permission. If one is added later, swap the
        // middleware argument here and in each route name.
        Route::middleware('carrier.permission:manage_agents')
            ->prefix('supervisors')
            ->name('carrier.supervisors.')
            ->group(function (): void {
                Route::get('/',        [SupervisorController::class, 'index'])->name('index');
                Route::post('/',       [SupervisorController::class, 'store'])->name('store');
                Route::put('{id}',     [SupervisorController::class, 'update'])->name('update');
                Route::delete('{id}',  [SupervisorController::class, 'destroy'])->name('destroy');
            });
    });
});
