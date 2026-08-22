<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use App\Models\VendorAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();

        $query = Notification::where('notifiable_type', VendorAdmin::class)
            ->where('notifiable_id', $admin->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate((int) ($request->per_page ?? 20));

        $unreadCount = Notification::where('notifiable_type', VendorAdmin::class)
            ->where('notifiable_id', $admin->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success([
            'items' => NotificationResource::collection($paginator)->resolve(),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();

        $count = Notification::where('notifiable_type', VendorAdmin::class)
            ->where('notifiable_id', $admin->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['unread_count' => $count]);
    }

    public function markRead(string $id): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();

        $notification = Notification::where('id', $id)
            ->where('notifiable_type', VendorAdmin::class)
            ->where('notifiable_id', $admin->id)
            ->first();

        if (!$notification) {
            return ApiResponse::error('Notification not found.', [], 404);
        }

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResponse::success(new NotificationResource($notification), 'Marked as read.');
    }

    public function markAllRead(): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();

        Notification::where('notifiable_type', VendorAdmin::class)
            ->where('notifiable_id', $admin->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success([], 'All notifications marked as read.');
    }
}
