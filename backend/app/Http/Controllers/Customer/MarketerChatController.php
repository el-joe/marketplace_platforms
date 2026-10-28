<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MarketerConversation;
use App\Models\MarketerProfile;
use App\Services\Marketer\MarketerChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketerChatController extends Controller
{
    public function __construct(private readonly MarketerChatService $chat) {}

    private function owned(string $id): MarketerConversation
    {
        return MarketerConversation::where('customer_id', auth('customer')->id())
            ->with(['marketer:id,name', 'marketer.marketerProfile.avatarFile'])
            ->findOrFail($id);
    }

    private function present(MarketerConversation $c): array
    {
        return [
            'id' => $c->id,
            'marketer' => [
                'id' => $c->marketer_id,
                'name' => $c->marketer?->name,
                'slug' => $c->marketer?->marketerProfile?->profile_slug,
                'avatar_url' => $c->marketer?->marketerProfile?->avatarFile?->url,
            ],
            'last_message' => $c->relationLoaded('latestMessage') && $c->latestMessage
                ? MarketerChatService::presentMessage($c->latestMessage) : null,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'has_unread' => (bool) $c->customer_has_unread,
        ];
    }

    /** GET chats */
    public function index(): JsonResponse
    {
        $page = MarketerConversation::where('customer_id', auth('customer')->id())
            ->whereNotNull('last_message_at')
            ->with(['marketer:id,name', 'marketer.marketerProfile.avatarFile', 'latestMessage'])
            ->orderByDesc('last_message_at')->paginate(20);

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn ($c) => $this->present($c))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** GET chats/unread-count */
    public function unreadCount(): JsonResponse
    {
        return ApiResponse::success([
            'count' => MarketerConversation::where('customer_id', auth('customer')->id())
                ->where('customer_has_unread', true)->count(),
        ]);
    }

    /**
     * POST chats  {marketer_slug}
     * Opens (or returns) the customer's profile-level conversation with a marketer.
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate(['marketer_slug' => ['required', 'string', 'max:255']]);

        $profile = MarketerProfile::where('profile_slug', $data['marketer_slug'])->with('marketer')->first();
        $status = $profile?->marketer?->global_status;
        if (! $profile?->marketer || ($status?->value ?? $status) !== 'active') {
            return ApiResponse::error('Marketer not found', [], 404);
        }

        $conversation = $this->chat->findOrCreate($profile->marketer, (string) auth('customer')->id());

        return ApiResponse::success($this->present($this->owned($conversation->id)));
    }

    /** GET chats/{id}  — marks the marketer's messages as read. */
    public function show(string $country, string $id): JsonResponse
    {
        $conversation = $this->owned($id);
        $this->chat->markRead($conversation, 'customer');

        return ApiResponse::success($this->present($conversation) + [
            'messages' => $conversation->messages()->get()->map(fn ($m) => MarketerChatService::presentMessage($m))->values(),
        ]);
    }

    /** POST chats/{id}/messages  {body} */
    public function sendMessage(Request $request, string $country, string $id): JsonResponse
    {
        $conversation = $this->owned($id);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = $this->chat->post($conversation, 'customer', (string) auth('customer')->id(), $data['body'], $request->header('X-Socket-ID'));

        return ApiResponse::success(MarketerChatService::presentMessage($message), 'Sent', 201);
    }

    /** POST chats/{id}/read */
    public function markRead(string $country, string $id): JsonResponse
    {
        return ApiResponse::success(['marked' => $this->chat->markRead($this->owned($id), 'customer')]);
    }
}
