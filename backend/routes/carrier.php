<?php

use App\Http\Controllers\CarrierPortal\AgentController;
use App\Http\Controllers\CarrierPortal\AssignmentController;
use App\Http\Controllers\CarrierPortal\AuthController;
use App\Http\Controllers\CarrierPortal\DashboardController;
use App\Http\Controllers\CarrierPortal\ReportController;
use App\Http\Controllers\CarrierPortal\SupervisorController;
use App\Http\Controllers\CarrierPortal\ZoneController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// ── Locale switcher (carrier subdomain) ──────────────────────────────────
Route::middleware('web')
    ->post('/locale/switch', function (\Illuminate\Http\Request $request) {
        $locale = $request->input('locale');
        abort_unless(in_array($locale, config('app.available_locales', ['ar', 'en'])), 422);
        $request->session()->put([
            'locale'          => $locale,
            'locale_override' => $locale,
            'dir'             => $locale === 'ar' ? 'rtl' : 'ltr',
        ]);
        \Carbon\Carbon::setLocale($locale);
        \Illuminate\Support\Facades\App::setLocale($locale);
        return back();
    })->name('carrier.locale.switch');

Route::name('carrier.')
    ->group(function () {

        Broadcast::routes(['middleware' => ['web', 'auth.carrier']]);

        // ── Guest ──────────────────────────────────────────────────────────────
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');

        // ── Authenticated ──────────────────────────────────────────────────────
        Route::middleware(['auth.carrier'])->group(function () {

            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            // ── Notifications ───────────────────────────────────────────────────
            Route::prefix('notifications')->name('notifications.')
                ->controller(NotificationController::class)
                ->group(function () {
                    Route::get('/',              'index')->name('index');
                    Route::get('/recent',        'recent')->name('recent');
                    Route::get('/unread-count',  'unreadCount')->name('unread-count');
                    Route::post('/mark-all-read','markAllRead')->name('mark-all-read');
                    Route::post('/{id}/read',    'markRead')->name('mark-read');
                });

            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // Agents (unlimited — no cap)
            Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
            Route::get('/agents/create', [AgentController::class, 'create'])->name('agents.create');
            Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
            Route::patch('/agents/{id}/suspend', [AgentController::class, 'suspend'])->name('agents.suspend');
            Route::patch('/agents/{id}/activate', [AgentController::class, 'activate'])->name('agents.activate');
            Route::get('/agents/{id}', [AgentController::class, 'show'])->name('agents.show');
            Route::patch('/agents/{id}/zone', [AgentController::class, 'assignZone'])->name('agents.assign-zone');
            Route::post('/agents/{id}/reset-password', [AgentController::class, 'resetPassword'])->name('agents.reset-password');
            Route::put('/agents/{id}', [AgentController::class, 'update'])->name('agents.update');

            // Zones (scoped to supervisor's country, for agent zone dropdown)
            Route::get('/zones', [ZoneController::class, 'index'])->name('zones.index');

            // Supervisors (owner-level only, gated inside controller)
            Route::resource('supervisors', SupervisorController::class)
                ->except(['show']);

            // Assignments — unassigned before {assignment} to avoid route collision
            Route::get('/assignments/unassigned', [AssignmentController::class, 'unassigned'])->name('assignments.unassigned');
            Route::post('/assignments/{shipment}/assign', [AssignmentController::class, 'assign'])->name('assignments.assign');
            Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
            Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
            Route::post('/assignments/{assignment}/reassign', [AssignmentController::class, 'reassign'])->name('assignments.reassign');

            // Reports
            Route::get('/reports', fn () => redirect()->route('carrier.reports.orders'))
                ->name('reports.index');
            Route::get('/reports/orders', [ReportController::class, 'orders'])->name('reports.orders');
            Route::get('/reports/orders/export', [ReportController::class, 'ordersExport'])->name('reports.orders.export');
            Route::get('/reports/earnings', [ReportController::class, 'earnings'])->name('reports.earnings');
            Route::get('/reports/earnings/export', [ReportController::class, 'earningsExport'])->name('reports.earnings.export');
            Route::get('/reports/payouts', [ReportController::class, 'payouts'])->name('reports.payouts');
            Route::get('/reports/cod-settlements', [ReportController::class, 'codSettlements'])->name('reports.cod-settlements');
            Route::get('/reports/performance', [ReportController::class, 'performance'])->name('reports.performance');
            Route::get('/reports/performance/trend', [ReportController::class, 'performanceTrend'])->name('reports.performance.trend');
            Route::get('/reports/claims', [ReportController::class, 'claims'])->name('reports.claims');
            Route::get('/reports/claims/{claim}', [ReportController::class, 'claimShow'])->name('reports.claims.show');
        });
    });
