<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedInquiry;
use App\Models\Marketer;
use App\Models\MarketerConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    private function owned(string $id): MarketerConversation
    {
        return MarketerConversation::where('marketer_id', $this->marketer()->id)->findOrFail($id);
    }

    private function msg($m): array
    {
        return ['id' => $m->id, 'sender_type' => $m->sender_type, 'body' => $m->body, 'read_at' => $m->read_at, 'created_at' => $m->created_at];
    }

    private function present(MarketerConversation $c): array
    {
        return [
            'id' => $c->id,
            'customer' => ['id' => $c->customer_id, 'name' => $c->customer?->name],
            'classified_listing_id' => $c->classified_listing_id,
            'last_message' => $c->relationLoaded('latestMessage') && $c->latestMessage ? $this->msg($c->latestMessage) : null,
            'last_message_at' => $c->last_message_at,
            'has_unread' => (bool) $c->marketer_has_unread,
        ];
    }

    public function index(): JsonResponse
    {
        $page = MarketerConversation::where('marketer_id', $this->marketer()->id)
            ->with(['customer', 'latestMessage'])
            ->orderByDesc('last_message_at')->orderByDesc('created_at')->paginate(20);
        $page->getCollection()->transform(fn ($c) => $this->present($c));

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function show(string $id): JsonResponse
    {
        $c = $this->owned($id)->load('customer');
        $c->messages()->where('sender_type', 'customer')->whereNull('read_at')->update(['read_at' => now()]);
        if ($c->marketer_has_unread) {
            $c->forceFill(['marketer_has_unread' => false])->saveQuietly();
        }

        return response()->json(['success' => true, 'data' => $this->present($c) + [
            'messages' => $c->messages()->get()->map(fn ($m) => $this->msg($m))->values(),
        ]]);
    }

    /**
     * Security: a conversation can only start from an inquiry on one of the
     * marketer's own listings. customer_id is accepted only when a matching
     * inquiry exists (never an arbitrary customer).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inquiry_id' => ['required_without:customer_id', 'nullable', 'uuid'],
            'customer_id' => ['required_without:inquiry_id', 'nullable', 'uuid'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);
        $marketer = $this->marketer();

        $q = ClassifiedInquiry::whereHas('listing', fn ($l) => $l->withTrashed()
            ->where('seller_type', Marketer::class)->where('seller_id', $marketer->id));
        $inquiry = ! empty($data['inquiry_id'])
            ? $q->findOrFail($data['inquiry_id'])
            : $q->where('customer_id', $data['customer_id'])->latest()->firstOrFail();

        $conv = MarketerConversation::firstOrCreate(
            ['marketer_id' => $marketer->id, 'customer_id' => $inquiry->customer_id,
             'classified_listing_id' => $inquiry->classified_listing_id],
            ['classified_inquiry_id' => $inquiry->id]
        );
        if (! empty($data['body'])) {
            $this->post($conv, $data['body']);
        }

        return response()->json(['success' => true, 'data' => $this->present($conv->load('customer'))], 201);
    }

    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $conv = $this->owned($id);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $m = $this->post($conv, $data['body']);

        return response()->json(['success' => true, 'data' => $this->msg($m)], 201);
    }

    public function markRead(string $id): JsonResponse
    {
        $c = $this->owned($id);
        $n = $c->messages()->where('sender_type', 'customer')->whereNull('read_at')->update(['read_at' => now()]);
        $c->forceFill(['marketer_has_unread' => false])->saveQuietly();

        return response()->json(['success' => true, 'data' => ['marked' => $n]]);
    }

    private function post(MarketerConversation $conv, string $body)
    {
        return DB::transaction(function () use ($conv, $body) {
            $m = $conv->messages()->create(['sender_type' => 'marketer', 'sender_id' => $this->marketer()->id, 'body' => $body]);
            $conv->forceFill(['last_message_at' => now(), 'customer_has_unread' => true, 'marketer_has_unread' => false])->save();

            return $m;
        });
    }
}
