<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    private function user()
    {
        return Auth::guard('marketer_api')->user();
    }

    public function index(): JsonResponse
    {
        $page = $this->user()->notifications()->latest()->paginate(20);
        $page->getCollection()->transform(fn ($n) => [
            'id'         => $n->id,
            'type'       => $n->data['type'] ?? class_basename($n->type),
            'title'      => $n->data['title'] ?? $n->data['title_ar'] ?? null,
            'body'       => $n->data['body'] ?? $n->data['message'] ?? null,
            'data'       => $n->data,
            'read_at'    => $n->read_at,
            'created_at' => $n->created_at,
        ]);

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['count' => $this->user()->unreadNotifications()->count()]]);
    }

    public function markAllRead(): JsonResponse
    {
        $this->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true, 'data' => ['count' => 0]]);
    }

    public function markRead(string $id): JsonResponse
    {
        $this->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['success' => true, 'data' => ['id' => $id]]);
    }
}
