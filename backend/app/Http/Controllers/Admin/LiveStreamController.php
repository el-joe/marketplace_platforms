<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LiveStreamStatus;
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
            'title_en'       => 'required|string|max:200',
            'title_ar'       => 'required|string|max:200',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'scheduled_at'   => 'nullable|date',
            'thumbnail'      => 'nullable|image|max:2048',
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
            'title_en'       => 'required|string|max:200',
            'title_ar'       => 'required|string|max:200',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'scheduled_at'   => 'nullable|date',
            'thumbnail'      => 'nullable|image|max:2048',
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

        $liveStream->update([
            'status'     => LiveStreamStatus::Live,
            'started_at' => now(),
        ]);

        return response()->json([
            'success'    => true,
            'stream_key' => $liveStream->stream_key,
        ]);
    }

    public function endStream(LiveStream $liveStream): JsonResponse
    {
        $liveStream->update([
            'status'   => LiveStreamStatus::Ended,
            'ended_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    // ── WebRTC Signalling ─────────────────────────────────────────────────────

    public function signal(Request $request, LiveStream $liveStream): JsonResponse
    {
        if (!$liveStream->isLive() || !$liveStream->stream_key) {
            return response()->json(['success' => false, 'message' => 'Stream is not live.'], 422);
        }

        $data = $request->validate([
            'type'    => ['required', Rule::in(['offer', 'answer', 'ice-candidate'])],
            'payload' => 'required|array',
            'to'      => 'nullable|string',
        ]);

        broadcast(new \App\Events\StreamSignal(
            $liveStream->stream_key,
            $data['type'],
            $data['payload'],
            $data['to'] ?? null,
            'admin',
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    // ── Comments (admin read + delete) ───────────────────────────────────────

    public function comments(LiveStream $liveStream): JsonResponse
    {
        $comments = $liveStream->comments()
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'author'     => $c->guest_name ?? 'Customer',
                'body'       => $c->body,
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
