<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LiveStreamMediaMode;
use App\Enums\LiveStreamStatus;
use App\Events\StreamSignal;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\LiveStreamComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LiveStreamController extends Controller
{
    public function index(): View
    {
        $streams = LiveStream::orderByDesc('scheduled_at')->paginate(20);

        return view('admin.live-streams.index', compact('streams'));
    }

    public function create(): View
    {
        return view('admin.live-streams.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_en' => 'required|string|max:200',
            'title_ar' => 'required|string|max:200',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'scheduled_at' => 'nullable|date',
            'thumbnail' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')
                ->store('live-streams/thumbnails', 'public');
        }

        unset($data['thumbnail']);
        $data['created_by_admin_id'] = auth('admin')->id();

        LiveStream::create($data);

        return redirect()->route('admin.live-streams.index')
            ->with('success', 'Live stream scheduled.');
    }

    public function show(LiveStream $liveStream): View
    {
        $comments = $liveStream->comments()->latest()->limit(100)->get();

        return view('admin.live-streams.show', compact('liveStream', 'comments'));
    }

    public function edit(LiveStream $liveStream): View
    {
        return view('admin.live-streams.form', compact('liveStream'));
    }

    public function update(Request $request, LiveStream $liveStream): RedirectResponse
    {
        $data = $request->validate([
            'title_en' => 'required|string|max:200',
            'title_ar' => 'required|string|max:200',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'scheduled_at' => 'nullable|date',
            'thumbnail' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('thumbnail')) {
            if ($liveStream->thumbnail_path) {
                Storage::disk('public')->delete($liveStream->thumbnail_path);
            }
            $data['thumbnail_path'] = $request->file('thumbnail')
                ->store('live-streams/thumbnails', 'public');
        }

        unset($data['thumbnail']);
        $liveStream->update($data);

        return redirect()->route('admin.live-streams.show', $liveStream)
            ->with('success', 'Stream updated.');
    }

    // ── Stream lifecycle ──────────────────────────────────────────────────────

    public function goLive(LiveStream $liveStream): JsonResponse
    {
        if ($liveStream->status === LiveStreamStatus::Ended) {
            return response()->json([
                'success' => false,
                'message' => 'Stream has already ended.',
            ], 422);
        }

        $alreadyLive = $liveStream->isLive();

        $liveStream->update([
            'status' => LiveStreamStatus::Live,
            'started_at' => $alreadyLive ? ($liveStream->started_at ?? now()) : now(),
        ]);

        // "admin-ready": tells connected viewers to (re)send hello / re-negotiate.
        $this->broadcastSignal($liveStream, 'hello', [], null);

        return response()->json([
            'success' => true,
            'stream_key' => $liveStream->stream_key,
            'media_mode' => $liveStream->media_mode,
        ]);
    }

    public function endStream(LiveStream $liveStream): JsonResponse
    {
        // Idempotent: a second call on an ended stream is a successful no-op.
        if ($liveStream->status === LiveStreamStatus::Ended) {
            return response()->json(['success' => true]);
        }

        $wasLive = $liveStream->isLive();

        $liveStream->update([
            'status' => LiveStreamStatus::Ended,
            'ended_at' => now(),
            'media_mode' => null,
        ]);

        if ($wasLive) {
            $this->broadcastSignal($liveStream, 'media-state', ['mode' => null, 'audio' => false, 'video' => false], null);
            $this->broadcastSignal($liveStream, 'ended', [], null);
        }

        return response()->json(['success' => true]);
    }

    // ── Media mode ────────────────────────────────────────────────────────────

    public function media(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::enum(LiveStreamMediaMode::class)],
        ]);

        $mode = LiveStreamMediaMode::from($data['mode']);
        $liveStream->update(['media_mode' => $mode->value]);

        if ($liveStream->isLive()) {
            $this->broadcastSignal($liveStream, 'media-state', $mode->state(), null);
        }

        return response()->json(['success' => true, 'media_mode' => $mode->value, 'state' => $mode->state()]);
    }

    // ── WebRTC Signalling ─────────────────────────────────────────────────────

    public const SIGNAL_TYPES = ['offer', 'answer', 'ice-candidate', 'hello', 'bye', 'media-state', 'ended'];

    public const SIGNAL_MAX_BYTES = 65536;

    public function signal(Request $request, LiveStream $liveStream): JsonResponse
    {
        if (! $liveStream->isLive() || ! $liveStream->stream_key) {
            return response()->json(['success' => false, 'message' => 'Stream is not live.'], 422);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(self::SIGNAL_TYPES)],
            'payload' => ['nullable', 'array'],
            'target' => ['nullable', 'string', 'regex:/^[A-Za-z0-9\-]{1,40}$/'],
            'to' => ['nullable', 'string', 'regex:/^[A-Za-z0-9\-]{1,40}$/'],
        ]);

        $payload = $data['payload'] ?? [];

        if (strlen((string) json_encode($payload)) > self::SIGNAL_MAX_BYTES) {
            return response()->json(['success' => false, 'message' => 'Payload too large.'], 422);
        }

        if (in_array($data['type'], ['offer', 'answer', 'ice-candidate'], true) && $payload === []) {
            return response()->json(['success' => false, 'message' => 'Payload required.'], 422);
        }

        $this->broadcastSignal($liveStream, $data['type'], $payload, $data['target'] ?? $data['to'] ?? null, true);

        return response()->json(['success' => true]);
    }

    private function broadcastSignal(LiveStream $liveStream, string $type, array $payload, ?string $target, bool $toOthers = false): void
    {
        if (! $liveStream->stream_key) {
            return;
        }

        $pending = broadcast(new StreamSignal($liveStream->stream_key, $type, $payload, $target, 'admin'));

        if ($toOthers) {
            $pending->toOthers();
        }
    }

    // ── Comments (admin read + delete) ───────────────────────────────────────

    public function comments(LiveStream $liveStream): JsonResponse
    {
        $comments = $liveStream->comments()
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'author' => $c->guest_name ?? 'Customer',
                'body' => $c->body,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $comments]);
    }

    public function deleteComment(LiveStream $liveStream, LiveStreamComment $comment): JsonResponse
    {
        $comment->delete();

        return response()->json(['success' => true]);
    }

    public function destroy(LiveStream $liveStream): RedirectResponse
    {
        $liveStream->delete();

        return redirect()->route('admin.live-streams.index')
            ->with('success', 'Stream deleted.');
    }
}
