<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedInquiry;
use App\Models\Marketer;
use App\Models\MarketerConversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConversationController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    private function owned(string $id): MarketerConversation
    {
        return MarketerConversation::where('marketer_id', $this->marketer()->id)->findOrFail($id);
    }

    private function list()
    {
        return MarketerConversation::where('marketer_id', $this->marketer()->id)
            ->with(['customer', 'latestMessage'])
            ->orderByDesc('last_message_at')->orderByDesc('created_at')->get();
    }

    public function index(): View
    {
        return view('marketer.conversations.index', ['conversations' => $this->list(), 'active' => null, 'messages' => collect()]);
    }

    public function show(string $conversation): View
    {
        $active = $this->owned($conversation);
        $active->messages()->where('sender_type', 'customer')->whereNull('read_at')->update(['read_at' => now()]);
        if ($active->marketer_has_unread) {
            $active->forceFill(['marketer_has_unread' => false])->saveQuietly();
        }
        $active->load(['customer', 'classifiedListing']);

        return view('marketer.conversations.index', [
            'conversations' => $this->list(), 'active' => $active, 'messages' => $active->messages()->get(),
        ]);
    }

    public function sendMessage(Request $request, string $conversation): RedirectResponse
    {
        $conv = $this->owned($conversation);
        $data = $request->validate(['body' => 'required|string|max:5000']);
        $this->post($conv, $data['body']);

        return redirect()->route('marketer.conversations.show', $conv->id);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'inquiry_id' => 'required|uuid',
            'body' => 'nullable|string|max:5000',
        ]);
        $marketer = $this->marketer();

        // Customer must be tied to an inquiry on one of this marketer's own listings.
        $inquiry = ClassifiedInquiry::whereHas('listing', fn ($q) => $q->withTrashed()
            ->where('seller_type', Marketer::class)->where('seller_id', $marketer->id))
            ->findOrFail($data['inquiry_id']);

        $conv = MarketerConversation::firstOrCreate(
            ['marketer_id' => $marketer->id, 'customer_id' => $inquiry->customer_id,
             'classified_listing_id' => $inquiry->classified_listing_id],
            ['classified_inquiry_id' => $inquiry->id]
        );

        if (! empty($data['body'])) {
            $this->post($conv, $data['body']);
        }

        return redirect()->route('marketer.conversations.show', $conv->id);
    }

    private function post(MarketerConversation $conv, string $body): void
    {
        DB::transaction(function () use ($conv, $body) {
            $conv->messages()->create([
                'sender_type' => 'marketer', 'sender_id' => $this->marketer()->id, 'body' => $body,
            ]);
            $conv->forceFill(['last_message_at' => now(), 'customer_has_unread' => true, 'marketer_has_unread' => false])->save();
        });
    }
}
