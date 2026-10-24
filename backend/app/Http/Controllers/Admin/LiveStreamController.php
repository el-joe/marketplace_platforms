<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LiveStreamMediaMode;
use App\Enums\LiveStreamStatus;
use App\Events\StreamSignal;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\LiveStreamComment;
use App\Services\LiveStream\RecordedStreamScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
        $recorded = $request->input('source') === LiveStream::SOURCE_RECORDED;

        $data = $request->validate([
            'source' => ['nullable', Rule::in([LiveStream::SOURCE_LIVE, LiveStream::SOURCE_RECORDED])],
            'title_en' => 'required|string|max:200',
            'title_ar' => 'required|string|max:200',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'scheduled_at' => [$recorded ? 'required' : 'nullable', 'date'],
            'thumbnail' => 'nullable|image|max:2048',
            ...$this->mediaRules($recorded, true),
        ]);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')
                ->store('live-streams/thumbnails', 'public');
        }

        unset($data['thumbnail'], $data['media'], $data['duration_seconds']);
        $data['source'] = $recorded ? LiveStream::SOURCE_RECORDED : LiveStream::SOURCE_LIVE;
        $data['created_by_admin_id'] = auth('admin')->id();

        if ($recorded) {
            $media = $this->storeMedia($request->file('media'), (int) $request->input('duration_seconds'));
            if ($media === null) {
                return back()->withInput()->withErrors(['media' => __('admin.live_streams.duration_unreadable')]);
            }
            $data = [...$data, ...$media, 'scheduled_end_at' => RecordedStreamScheduler::endFor(Carbon::parse($data['scheduled_at']), $media['duration_seconds'])];
        }

        $stream = LiveStream::create($data);

        if ($recorded) {
            app(RecordedStreamScheduler::class)->sync();
        }

        return redirect()->route('admin.live-streams.index')
            ->with('success', $recorded ? __('admin.live_streams.channel_scheduled') : 'Live stream scheduled.');
    }

    public function show(LiveStream $liveStream): View
    {
        $comments = $liveStream->comments()->latest()->limit(100)->get();

        if ($liveStream->isRecorded()) {
            app(RecordedStreamScheduler::class)->sync();
            $liveStream->refresh();

            return view('admin.live-streams.show-recorded', compact('liveStream', 'comments'));
        }

        return view('admin.live-streams.show', compact('liveStream', 'comments'));
    }

    public function edit(LiveStream $liveStream): View
    {
        return view('admin.live-streams.form', compact('liveStream'));
    }

    public function update(Request $request, LiveStream $liveStream): RedirectResponse
    {
        // A recorded channel can be re-timed / re-uploaded only while it is still scheduled.
        $editSchedule = $liveStream->isRecorded() && $liveStream->status === LiveStreamStatus::Scheduled;

        $data = $request->validate([
            'title_en' => 'required|string|max:200',
            'title_ar' => 'required|string|max:200',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'scheduled_at' => [$editSchedule ? 'required' : 'nullable', 'date'],
            'thumbnail' => 'nullable|image|max:2048',
            ...$this->mediaRules($editSchedule, false),
        ]);

        if ($request->hasFile('thumbnail')) {
            if ($liveStream->thumbnail_path) {
                Storage::disk('public')->delete($liveStream->thumbnail_path);
            }
            $data['thumbnail_path'] = $request->file('thumbnail')
                ->store('live-streams/thumbnails', 'public');
        }

        unset($data['thumbnail'], $data['media'], $data['duration_seconds']);

        if ($liveStream->isRecorded()) {
            if (! $editSchedule) {
                unset($data['scheduled_at']);
            } else {
                $duration = $liveStream->duration_seconds;

                if ($request->hasFile('media')) {
                    $media = $this->storeMedia($request->file('media'), (int) $request->input('duration_seconds'));
                    if ($media === null) {
                        return back()->withInput()->withErrors(['media' => __('admin.live_streams.duration_unreadable')]);
                    }
                    if ($liveStream->media_path) {
                        Storage::disk('public')->delete($liveStream->media_path);
                    }
                    $data = [...$data, ...$media];
                    $duration = $media['duration_seconds'];
                }

                $data['scheduled_end_at'] = RecordedStreamScheduler::endFor(Carbon::parse($data['scheduled_at']), $duration);
            }
        }

        $liveStream->update($data);

        if ($liveStream->isRecorded()) {
            app(RecordedStreamScheduler::class)->sync();
        }

        return redirect()->route('admin.live-streams.show', $liveStream)
            ->with('success', 'Stream updated.');
    }

    /** @return array<string, mixed> */
    private function mediaRules(bool $recorded, bool $required): array
    {
        return [
            'media' => [$recorded && $required ? 'required' : 'nullable', 'file', 'mimes:mp3,wav,ogg,oga,m4a,aac,mp4,webm,mov,m4v,mpeg', 'max:512000'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
        ];
    }

    /**
     * Stores the uploaded file and works out its type and duration (ffprobe first, the
     * browser-measured value as fallback). Null when no duration can be determined.
     *
     * @return array{media_path: string, media_type: string, duration_seconds: int}|null
     */
    private function storeMedia(UploadedFile $file, int $clientDuration): ?array
    {
        $path = $file->store('live-streams/media', 'public');
        $duration = RecordedStreamScheduler::probeDuration(Storage::disk('public')->path($path)) ?? ($clientDuration > 0 ? $clientDuration : null);

        if ($duration === null) {
            Storage::disk('public')->delete($path);

            return null;
        }

        $mime = (string) $file->getMimeType();
        $isVideo = str_starts_with($mime, 'video/') || in_array(strtolower($file->getClientOriginalExtension()), ['mp4', 'webm', 'mov', 'm4v'], true);

        return ['media_path' => $path, 'media_type' => $isVideo ? 'video' : 'audio', 'duration_seconds' => $duration];
    }

    // ── Stream lifecycle ──────────────────────────────────────────────────────

    public function goLive(LiveStream $liveStream): JsonResponse
    {
        if ($liveStream->isRecorded()) {
            return response()->json(['success' => false, 'message' => 'Radio channels go live automatically at their scheduled time.'], 422);
        }

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
        if ($liveStream->isRecorded()) {
            return response()->json(['success' => false, 'message' => 'Not applicable to radio channels.'], 422);
        }

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
