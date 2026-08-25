<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Delivery\Api\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        $query = Notification::where('notifiable_type', DeliveryAgent::class)
            ->where('notifiable_id', $agent->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(20);

        return ApiResponse::paginated($paginator, NotificationResource::class);
    }

    public function markRead(string $id): JsonResponse
    {
        $agent = auth('delivery_api')->user();

        $notification = Notification::where('id', $id)
            ->where('notifiable_type', DeliveryAgent::class)
            ->where('notifiable_id', $agent->id)
            ->first();

        if (!$notification) {
            return ApiResponse::error(__('delivery.messages.notifications.not_found'), [], 404);
        }

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResponse::success(new NotificationResource($notification), __('delivery.messages.notifications.marked_read'));
    }

    public function markAllRead(): JsonResponse
    {
        $agent = auth('delivery_api')->user();

        Notification::where('notifiable_type', DeliveryAgent::class)
            ->where('notifiable_id', $agent->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success([], __('delivery.messages.notifications.all_marked_read'));
    }

    public function unreadCount(): JsonResponse
    {
        $agent = auth('delivery_api')->user();

        $count = Notification::where('notifiable_type', DeliveryAgent::class)
            ->where('notifiable_id', $agent->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['unread_count' => $count]);
    }
}
