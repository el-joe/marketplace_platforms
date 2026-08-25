<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    private function admin() { return Auth::guard('vendor_api')->user(); }

    public function index(): JsonResponse
    {
        $notifications = $this->admin()->notifications()
            ->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $notifications->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => class_basename($n->type),
                'data'       => $n->data,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $notifications->currentPage(), 'last_page' => $notifications->lastPage()],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['count' => $this->admin()->unreadNotifications()->count()]]);
    }

    public function markAllRead(): JsonResponse
    {
        $this->admin()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['success' => true, 'message' => 'All notifications marked as read.']);
    }

    public function markRead(string $id): JsonResponse
    {
        $this->admin()->notifications()->findOrFail($id)->markAsRead();
        return response()->json(['success' => true, 'message' => 'Notification marked as read.']);
    }
}
