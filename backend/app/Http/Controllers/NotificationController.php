<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shared notification controller used by every panel.
 *
 * No route defaults needed — the controller auto-detects the authenticated guard
 * since each subdomain's middleware ensures only one guard is active per request.
 *
 * Register in each panel's route file:
 *
 *   Route::prefix('notifications')->name('notifications.')
 *       ->controller(NotificationController::class)->group(function () {
 *           Route::get('/',              'index')->name('index');
 *           Route::get('/recent',        'recent')->name('recent');
 *           Route::get('/unread-count',  'unreadCount')->name('unread-count');
 *           Route::get('/unread',        'unread')->name('unread');
 *           Route::post('/mark-all-read','markAllRead')->name('mark-all-read');
 *           Route::post('/{id}/read',    'markRead')->name('mark-read');
 *       });
 */
class NotificationController extends Controller
{
    /** Ordered list of panel guards — only one is authenticated per subdomain request. */
    private const GUARDS = [
        'admin'               => 'layouts.admin',
        'vendor'              => 'layouts.partner',
        'shipping_supervisor' => 'layouts.carrier',
        'travel_agency'       => 'layouts.travel-agency',
        'delivery'            => 'layouts.delivery',
    ];

    private function notifiable(): ?object
    {
        foreach (array_keys(self::GUARDS) as $guard) {
            if ($user = auth($guard)->user()) {
                return $user;
            }
        }
        return null;
    }

    private function layout(): string
    {
        foreach (self::GUARDS as $guard => $layout) {
            if (auth($guard)->check()) {
                return $layout;
            }
        }
        return 'layouts.admin';
    }

    public function index(): \Illuminate\View\View
    {
        $user = $this->notifiable();
        $layout = $this->layout();

        $notifications = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(function ($n) {
                $data = is_string($n->data) ? json_decode($n->data, true) : (array) $n->data;
                return (object) [
                    'id' => $n->id,
                    'title' => $data['title'] ?? 'Notification',
                    'message' => $data['message'] ?? '',
                    'url' => $data['url'] ?? '#',
                    'read' => !is_null($n->read_at),
                    'created_at' => $n->created_at,
                ];
            });

        return view('notifications.index', compact('notifications', 'layout'));
    }

    /** Combined count + recent 10 for the bell dropdown. */
    public function recent(): JsonResponse
    {
        $user = $this->notifiable();

        $count = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->count();

        $items = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => $this->formatRow($n));

        return response()->json(['data' => ['count' => $count, 'items' => $items]]);
    }

    /** Kept for backward-compat with admin layout.js polling. */
    public function unreadCount(): JsonResponse
    {
        $user = $this->notifiable();

        if (!$user) {
            return response()->json(['data' => ['count' => 0]]);
        }

        $count = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => ['count' => $count]]);
    }

    /** Kept for backward-compat with admin layout.js dropdown. */
    public function unread(): JsonResponse
    {
        $user = $this->notifiable();

        $items = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($n) => $this->formatRow($n));

        return response()->json(['data' => ['items' => $items]]);
    }

    public function markRead(string $id): JsonResponse
    {
        $user = $this->notifiable();

        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        $user = $this->notifiable();

        DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function formatRow(object $n): array
    {
        $data = is_string($n->data) ? json_decode($n->data, true) : (array) $n->data;
        return [
            'id' => $n->id,
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? '',
            'url' => $data['url'] ?? '#',
            'read' => !is_null($n->read_at),
            'created_at' => $n->created_at,
        ];
    }
}
