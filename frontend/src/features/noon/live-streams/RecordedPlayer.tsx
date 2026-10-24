'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import Image from 'next/image';
import type { RecordedPlayback } from './types';

interface Props {
  playback: RecordedPlayback;
  /** true while the channel is on air: playback follows the schedule and can't be scrubbed */
  onAir: boolean;
  title: string;
  thumbnail: string | null;
  labels: { onAir: string; replay: string; tapToPlay: string; playFailed: string };
  /** called when the file finishes so the page can flip to "ended" */
  onFinished: () => void;
}

/**
 * Plays an uploaded audio/video file in sync with the schedule: on air it starts at the
 * server-computed offset (and re-syncs if the browser drifts), as a replay it starts at 0.
 */
export default function RecordedPlayer({ playback, onAir, title, thumbnail, labels, onFinished }: Props) {
  const mediaRef = useRef<HTMLMediaElement | null>(null);
  const loadedAt = useRef(Date.now());
  const [blocked, setBlocked] = useState(false);
  const isVideo = playback.type === 'video';

  const target = useCallback(() => {
    if (!onAir) return 0;
    const elapsed = (Date.now() - loadedAt.current) / 1000;
    return Math.min(playback.offset_seconds + elapsed, playback.duration_seconds);
  }, [onAir, playback.offset_seconds, playback.duration_seconds]);

  const start = useCallback(() => {
    const el = mediaRef.current;
    if (!el) return;
    if (onAir) el.currentTime = target();
    el.play().then(() => setBlocked(false)).catch(() => setBlocked(true));
  }, [onAir, target]);

  useEffect(() => {
    loadedAt.current = Date.now();
    const el = mediaRef.current;
    if (!el) return;
    // Replays wait for the viewer (native controls); only an on-air channel starts by itself.
    if (!onAir) return;
    const onMeta = () => start();
    if (el.readyState >= 1) start();
    else el.addEventListener('loadedmetadata', onMeta, { once: true });
    return () => el.removeEventListener('loadedmetadata', onMeta);
  }, [playback.url, onAir, start]);

  // Keep on-air playback aligned with the schedule (seeks, tab throttling, buffering).
  useEffect(() => {
    if (!onAir) return;
    const timer = setInterval(() => {
      const el = mediaRef.current;
      if (el && !el.paused && Math.abs(el.currentTime - target()) > 4) el.currentTime = target();
    }, 5000);
    return () => clearInterval(timer);
  }, [onAir, target]);

  const common = {
    ref: mediaRef as never,
    src: playback.url,
    preload: 'auto' as const,
    controls: !onAir,
    onEnded: onFinished,
    onPlay: () => setBlocked(false),
  };

  return (
    <div className="absolute inset-0 bg-black">
      {isVideo ? (
        <video {...common} playsInline className="w-full h-full object-contain bg-black" poster={thumbnail ?? undefined} />
      ) : (
        <>
          <audio {...common} className={onAir ? 'hidden' : 'absolute bottom-3 inset-x-3 z-10 w-[calc(100%-1.5rem)]'} />
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-4 px-6 text-white bg-gradient-to-br from-gray-900 via-gray-800 to-primary-900">
            {thumbnail && <Image src={thumbnail} alt="" fill className="object-cover opacity-20 blur-sm" sizes="100vw" />}
            <div className="relative z-[1] flex flex-col items-center gap-4">
              <span className="inline-flex items-center gap-1.5 bg-white/15 text-xs font-semibold px-3 py-1 rounded-full">
                📻 {onAir ? labels.onAir : labels.replay}
              </span>
              <div className="flex items-end gap-1 h-12" aria-hidden>
                {[0.5, 0.9, 0.6, 1, 0.7, 0.95, 0.55].map((h, i) => (
                  <span key={i} className={`w-1.5 rounded-full bg-white/80 ${onAir && !blocked ? 'animate-pulse' : 'opacity-40'}`}
                    style={{ height: `${h * 100}%`, animationDelay: `${i * 120}ms`, animationDuration: '900ms' }} />
                ))}
              </div>
              <p className="text-center text-sm font-semibold line-clamp-2">{title}</p>
            </div>
          </div>
        </>
      )}
      {blocked && (
        <button onClick={start} className="absolute inset-0 z-20 flex items-center justify-center bg-black/50 text-white" aria-label={labels.tapToPlay}>
          <span className="bg-white text-gray-900 font-semibold text-sm px-5 py-2.5 rounded-full shadow">▶ {labels.tapToPlay}</span>
        </button>
      )}
    </div>
  );
}
