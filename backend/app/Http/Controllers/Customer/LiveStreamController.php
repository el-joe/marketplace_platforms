<?php

namespace App\Http\Controllers\Customer;

use App\Enums\LiveStreamStatus;
use App\Events\StreamComment;
use App\Events\StreamLike;
use App\Events\StreamSignal;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Services\LiveStream\RecordedStreamScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LiveStreamController extends Controller
{
    // ── GET /streams ──────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        app(RecordedStreamScheduler::class)->sync();

        $streams = LiveStream::orderByDesc('scheduled_at')
            ->get()
            ->map(fn ($s) => $this->cardShape($s));

        return response()->json(['success' => true, 'data' => $streams]);
    }

    // ── GET /streams/{stream} ─────────────────────────────────────────────────

    public function show(Request $request, LiveStream $stream): JsonResponse
    {
        if ($stream->isRecorded()) {
            app(RecordedStreamScheduler::class)->sync();
            $stream->refresh();
        }

        // Deduplicate viewer count per IP per stream per hour (stateless — no sessions available).
        $viewKey = 'stream_view:'.$stream->id.':'.md5($request->ip() ?? 'unknown');
        if (Cache::add($viewKey, 1, now()->addHour())) {
            $stream->increment('total_viewers');
            $stream->total_viewers += 1; // keep in-memory model in sync
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $stream->id,
                'title' => ['en' => $stream->title_en, 'ar' => $stream->title_ar],
                'description' => ['en' => $stream->description_en, 'ar' => $stream->description_ar],
                'thumbnail_url' => $this->thumbnailUrl($stream->thumbnail_path),
                'status' => $stream->status->value,
                'media_mode' => $stream->media_mode,
                ...$this->recordedShape($stream, true),
                'scheduled_at' => $stream->scheduled_at?->toIso8601String(),
                'started_at' => $stream->started_at?->toIso8601String(),
                'ended_at' => $stream->ended_at?->toIso8601String(),
                'total_viewers' => $stream->total_viewers,
                'likes_count' => $stream->likes_count,
                'stream_key' => $stream->isLive() ? $stream->stream_key : null,
                'comments' => $stream->comments()->latest()->limit(50)->get()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'author' => $c->guest_name ?? 'Customer',
                        'body' => $c->body,
                        'created_at' => $c->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    // ── POST /streams/{stream}/comments ───────────────────────────────────────

    public function comment(Request $request, LiveStream $stream): JsonResponse
    {
        if ($stream->status !== LiveStreamStatus::Live) {
            return response()->json(['success' => false, 'message' => 'Stream is not live.'], 422);
        }

        $data = $request->validate([
            'body' => 'required|string|max:500',
            'guest_name' => 'nullable|string|max:100',
        ]);

        $comment = $stream->comments()->create([
            'body' => $data['body'],
            'guest_name' => $data['guest_name'] ?? 'Guest',
            'customer_id' => auth('customer')->id(),
        ]);

        if ($stream->stream_key) {
            broadcast(new StreamComment(
                $stream->stream_key,
                $comment->id,
                $comment->guest_name ?? 'Guest',
                $comment->body,
                $comment->created_at->toIso8601String(),
            ))->toOthers();
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $comment->id,
            'author' => $comment->guest_name ?? 'Guest',
            'body' => $comment->body,
            'created_at' => $comment->created_at->toIso8601String(),
        ]]);
    }

    // ── POST /streams/{stream}/like ────────────────────────────────────────────

    public function like(Request $request, LiveStream $stream): JsonResponse
    {
        $request->validate(['guest_token' => 'nullable|string|max:64']);

        $stream->increment('likes_count');

        if ($stream->stream_key) {
            broadcast(new StreamLike(
                $stream->stream_key,
                $stream->likes_count,
            ))->toOthers();
        }

        return response()->json([
            'success' => true,
            'likes_count' => $stream->likes_count,
        ]);
    }

    // ── POST /streams/{stream}/signal — WebRTC signalling from viewer ─────────

    public function signal(Request $request, LiveStream $stream): JsonResponse
    {
        if (! $stream->isLive()) {
            return response()->json(['success' => false, 'message' => 'Stream is not live.'], 422);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['offer', 'answer', 'ice-candidate', 'hello', 'bye'])],
            'payload' => ['nullable', 'array'],
            'peer_id' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]{6,40}$/'],
        ]);

        $payload = $data['payload'] ?? [];

        if (strlen((string) json_encode($payload)) > 65536) {
            return response()->json(['success' => false, 'message' => 'Payload too large.'], 422);
        }

        if (in_array($data['type'], ['offer', 'answer', 'ice-candidate'], true) && $payload === []) {
            return response()->json(['success' => false, 'message' => 'Payload required.'], 422);
        }

        // Per-peer limiter (in addition to the per-IP route throttle).
        $limiterKey = 'stream-signal:'.$stream->id.':'.$data['peer_id'];
        if (RateLimiter::tooManyAttempts($limiterKey, 300)) {
            return response()->json(['success' => false, 'message' => 'Too many signals.'], 429);
        }
        RateLimiter::hit($limiterKey, 60);

        if (! $stream->stream_key) {
            return response()->json(['success' => false, 'message' => 'Stream has no channel key.'], 422);
        }

        // Viewer -> admin only: target is always forced to 'admin'.
        broadcast(new StreamSignal(
            $stream->stream_key,
            $data['type'],
            $payload,
            'admin',
            $data['peer_id'],
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Absolute URL (with domain) of a thumbnail stored on the public disk. */
    private function thumbnailUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return preg_match('#^https?://#i', $url) ? $url : url($url);
    }

    /**
     * Radio-channel fields. `playback` (file URL + where the broadcast is right now) is only
     * exposed while live, and as a from-the-start replay once ended.
     *
     * @return array<string, mixed>
     */
    private function recordedShape(LiveStream $s, bool $withPlayback): array
    {
        $shape = [
            'source' => $s->source ?? LiveStream::SOURCE_LIVE,
            'media_type' => $s->isRecorded() ? $s->media_type : null,
            'duration_seconds' => $s->isRecorded() ? $s->duration_seconds : null,
            'scheduled_end_at' => $s->isRecorded() ? $s->scheduled_end_at?->toIso8601String() : null,
        ];

        if ($withPlayback) {
            $shape['playback'] = $s->isRecorded() && $s->media_path && $s->status !== LiveStreamStatus::Scheduled ? [
                'url' => RecordedStreamScheduler::mediaUrl($s->media_path),
                'type' => $s->media_type,
                'duration_seconds' => $s->duration_seconds,
                'offset_seconds' => RecordedStreamScheduler::offsetSeconds($s),
                'server_time' => now()->toIso8601String(),
            ] : null;
        }

        return $shape;
    }

    private function cardShape(LiveStream $s): array
    {
        return [
            'id' => $s->id,
            'title' => ['en' => $s->title_en, 'ar' => $s->title_ar],
            'thumbnail_url' => $this->thumbnailUrl($s->thumbnail_path),
            'status' => $s->status->value,
            'media_mode' => $s->media_mode,
            ...$this->recordedShape($s, false),
            'scheduled_at' => $s->scheduled_at?->toIso8601String(),
            'started_at' => $s->started_at?->toIso8601String(),
            'ended_at' => $s->ended_at?->toIso8601String(),
            'total_viewers' => $s->total_viewers,
            'likes_count' => $s->likes_count,
        ];
    }
}
