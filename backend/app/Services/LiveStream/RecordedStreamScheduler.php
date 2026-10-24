<?php

namespace App\Services\LiveStream;

use App\Enums\LiveStreamStatus;
use App\Models\LiveStream;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Lifecycle of scheduled audio/video channels: scheduled → live at scheduled_at → ended at
 * scheduled_at + duration. Idempotent; run by the minute scheduler and lazily by the public API.
 */
class RecordedStreamScheduler
{
    /** @return array{started: int, ended: int} */
    public function sync(?Carbon $now = null): array
    {
        $now ??= now();

        $ended = LiveStream::recorded()
            ->whereIn('status', [LiveStreamStatus::Scheduled->value, LiveStreamStatus::Live->value])
            ->whereNotNull('scheduled_end_at')
            ->where('scheduled_end_at', '<=', $now)
            ->get()
            ->each(fn (LiveStream $s) => $s->update([
                'status' => LiveStreamStatus::Ended,
                'started_at' => $s->started_at ?? $s->scheduled_at,
                'ended_at' => $s->scheduled_end_at,
            ]))->count();

        $started = LiveStream::recorded()
            ->where('status', LiveStreamStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->where('scheduled_end_at', '>', $now)
            ->get()
            ->each(fn (LiveStream $s) => $s->update([
                'status' => LiveStreamStatus::Live,
                'started_at' => $s->scheduled_at,
            ]))->count();

        return ['started' => $started, 'ended' => $ended];
    }

    /** Sets schedule end from the duration (call after the file/schedule changed). */
    public static function endFor(?Carbon $scheduledAt, ?int $durationSeconds): ?Carbon
    {
        return $scheduledAt && $durationSeconds ? $scheduledAt->copy()->addSeconds($durationSeconds) : null;
    }

    /** Where playback should be right now (seconds from the file's start), clamped to the duration. */
    public static function offsetSeconds(LiveStream $stream, ?Carbon $now = null): int
    {
        if (! $stream->scheduled_at || ! $stream->isLive()) {
            return 0;
        }

        $offset = (int) max(0, ($now ?? now())->diffInSeconds($stream->scheduled_at, false) * -1);

        return min($offset, (int) ($stream->duration_seconds ?? $offset));
    }

    /** Real duration of a stored media file via ffprobe; null when ffprobe is unavailable. */
    public static function probeDuration(string $absolutePath): ?int
    {
        try {
            $process = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $absolutePath]);
            $process->setTimeout(30)->run();

            if ($process->isSuccessful() && is_numeric(trim($process->getOutput()))) {
                return (int) ceil((float) trim($process->getOutput()));
            }
        } catch (\Throwable) {
            // ffprobe missing — the browser-measured duration is used instead.
        }

        return null;
    }

    public static function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return preg_match('#^https?://#i', $url) ? $url : url($url);
    }
}
