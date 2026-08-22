<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\RadioChannel;
use App\Models\RadioListenSession;
use App\Services\RadioSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RadioController extends Controller
{
    public function __construct(private RadioSchedulingService $scheduling) {}

    public function index(): View
    {
        $countryId = session('country_id');

        $channels = RadioChannel::where('is_active', true)
            ->where(fn($q) => $q->whereNull('country_id')->orWhere('country_id', $countryId))
            ->with('country')
            ->orderBy('type')
            ->orderBy('name_en')
            ->get()
            ->map(fn($ch) => $this->enrichChannel($ch));

        return view('storefront.radio.index', compact('channels'));
    }

    public function player(RadioChannel $channel): View
    {
        abort_unless($channel->is_active, 404);

        $channel = $this->enrichChannel($channel);
        $upcoming = $this->scheduling->getUpcomingSchedule($channel, 7);

        return view('storefront.radio.player', compact('channel', 'upcoming'));
    }

    public function trackSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action'           => 'required|in:start,heartbeat,end',
            'radio_channel_id' => 'required|exists:radio_channels,id',
            'session_id'       => 'required|string|max:100',
        ]);

        $customerId = auth('customer')->id();

        if ($data['action'] === 'start') {
            RadioListenSession::create([
                'radio_channel_id' => $data['radio_channel_id'],
                'customer_id'      => $customerId,
                'session_id'       => $data['session_id'],
                'started_at'       => now(),
                'created_at'       => now(),
            ]);
        } elseif ($data['action'] === 'end') {
            $session = RadioListenSession::where('session_id', $data['session_id'])
                ->whereNull('ended_at')
                ->latest('created_at')
                ->first();

            if ($session) {
                $session->update([
                    'ended_at'         => now(),
                    'duration_seconds' => now()->diffInSeconds($session->started_at),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function enrichChannel(RadioChannel $channel): RadioChannel
    {
        $activeSlot = $this->scheduling->getActiveSlot($channel);
        $channel->setAttribute('is_live', $activeSlot !== null);
        $channel->setAttribute('now_playing', $activeSlot?->title);
        $channel->setAttribute('current_stream_url', $channel->current_stream_url);

        return $channel;
    }
}
