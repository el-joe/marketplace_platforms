<?php

namespace App\Http\Controllers\Carrier\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $query = Notification::where('notifiable_type', ShippingCompanySupervisor::class)
            ->where('notifiable_id', $supervisor->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                ],
            ],
        ]);
    }

    public function markRead(string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $notification = Notification::where('id', $id)
            ->where('notifiable_type', ShippingCompanySupervisor::class)
            ->where('notifiable_id', $supervisor->id)
            ->first();

        if (!$notification) {
            return ApiResponse::error('Notification not found.', [], 404);
        }

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResponse::success($notification, 'Marked as read.');
    }

    public function markAllRead(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        Notification::where('notifiable_type', ShippingCompanySupervisor::class)
            ->where('notifiable_id', $supervisor->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success([], 'All notifications marked as read.');
    }

    public function unreadCount(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $count = Notification::where('notifiable_type', ShippingCompanySupervisor::class)
            ->where('notifiable_id', $supervisor->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['unread_count' => $count]);
    }
}
