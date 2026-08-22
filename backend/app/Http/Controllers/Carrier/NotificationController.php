<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Http\Resources\Carrier\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * GET /api/carrier/v1/notifications
     *
     * Returns notifications addressed directly to this supervisor.
     * When the supervisor has receives_all_notifications=1, the notification
     * sender is expected to have already stored broadcast copies under this
     * supervisor's notifiable_id — this endpoint simply reads what exists.
     *
     * Query params:
     *   unread_only  bool   — filter to unread only
     *   page         int    — pagination
     */
    public function index(Request $request): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();

        $request->validate([
            'unread_only' => ['sometimes', 'boolean'],
        ]);

        $query = Notification::where('notifiable_type', 'App\\Models\\ShippingCompanySupervisor')
            ->where('notifiable_id', $supervisor->id)
            ->latest('created_at');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(20);

        return ApiResponse::paginated($paginator, NotificationResource::class);
    }

    /**
     * PUT /api/carrier/v1/notifications/{id}/read
     */
    public function markRead(string $id): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();

        $notification = Notification::where('notifiable_type', 'App\\Models\\ShippingCompanySupervisor')
            ->where('notifiable_id', $supervisor->id)
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return ApiResponse::error(__('carrier.api.notification_not_found'), [], 404);
        }

        if ($notification->read_at === null) {
            DB::table('notifications')
                ->where('id', $id)
                ->update(['read_at' => now()]);
        }

        return ApiResponse::success(null, __('carrier.api.notification_marked_read'));
    }

    /**
     * PUT /api/carrier/v1/notifications/read-all
     */
    public function markAllRead(): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();

        DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\ShippingCompanySupervisor')
            ->where('notifiable_id', $supervisor->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(null, __('carrier.api.all_notifications_marked_read'));
    }

    /**
     * GET /api/carrier/v1/notifications/unread-count
     */
    public function unreadCount(): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();

        $count = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\ShippingCompanySupervisor')
            ->where('notifiable_id', $supervisor->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['unread_count' => $count]);
    }
}
