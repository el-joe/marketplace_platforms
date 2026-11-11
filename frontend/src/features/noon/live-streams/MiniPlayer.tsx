'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import useLocale from '@/src/hooks/use-locale';
import { Link } from '@/i18n/navigation';
import { getStream } from './api';
import type { LiveStreamDetail } from './types';
import LocalDateTime from "@/src/components/shared/local-date-time";

const fmt = (sec: number) => {
  const s = Math.max(0, Math.floor(sec));
  const h = Math.floor(s / 3600);
  const m = String(Math.floor((s % 3600) / 60)).padStart(h ? 2 : 1, '0');
  return `${h ? `${h}:` : ''}${m}:${String(s % 60).padStart(2, '0')}`;
};

interface Props {
  streamId: string;
  onClose: () => void;
}

/**
 * Small floating player for scheduled audio/video channels.
 * synced: true live — LIVE badge, same moment for everyone, only volume/mute.
 * free:   on-demand — play/pause, seek bar and time.
 */
export default function MiniPlayer({ streamId, onClose }: Props) {
  const t = useTranslations('liveStreams');
  const locale = useLocale() as 'en' | 'ar';
  const mediaRef = useRef<HTMLMediaElement | null>(null);
  const loadedAt = useRef(0);
  const [stream, setStream] = useState<LiveStreamDetail | null>(null);
  const [minimized, setMinimized] = useState(false);
  const [playing, setPlaying] = useState(false);
  const [blocked, setBlocked] = useState(false);
  const [muted, setMuted] = useState(false);
  const [volume, setVolume] = useState(1);
  const [time, setTime] = useState(0);
  const [duration, setDuration] = useState(0);

  const playback = stream?.playback ?? null;
  const isLive = stream?.status === 'live';
  const synced = (stream?.playback_mode ?? 'synced') === 'synced';
  const follow = isLive && synced; // playback position is dictated by the schedule
  const ended = !!stream && stream.status === 'ended' && !playback;

  const refresh = useCallback(() => {
    getStream(streamId).then(setStream).catch(() => {});
  }, [streamId]);

  useEffect(() => { refresh(); }, [refresh]);

  // Pick up scheduled → live → ended transitions.
  useEffect(() => {
    if (!stream || stream.status === 'ended') return;
    const timer = setInterval(refresh, 15000);
    return () => clearInterval(timer);
  }, [stream, refresh]);

  const target = useCallback(() => {
    if (!playback) return 0;
    const elapsed = (Date.now() - loadedAt.current) / 1000;
    return Math.min(playback.offset_seconds + elapsed, playback.duration_seconds);
  }, [playback]);

  const start = useCallback(() => {
    const el = mediaRef.current;
    if (!el) return;
    if (isLive) el.currentTime = target();
    el.play().then(() => setBlocked(false)).catch(() => setBlocked(true));
  }, [isLive, target]);

  // Autoplay from the right spot once the file is ready (live channels only; replays wait for the viewer).
  useEffect(() => {
    loadedAt.current = Date.now();
    const el = mediaRef.current;
    if (!el || !isLive) return;
    const onMeta = () => start();
    if (el.readyState >= 1) start();
    else el.addEventListener('loadedmetadata', onMeta, { once: true });
    return () => el.removeEventListener('loadedmetadata', onMeta);
  }, [playback?.url, isLive, start]);

  // Synced channels must stay on the schedule even after buffering/tab throttling.
  useEffect(() => {
    if (!follow) return;
    const timer = setInterval(() => {
      const el = mediaRef.current;
      if (el && !el.paused && Math.abs(el.currentTime - target()) > 4) el.currentTime = target();
    }, 5000);
    return () => clearInterval(timer);
  }, [follow, target]);

  useEffect(() => {
    const el = mediaRef.current;
    if (el) { el.volume = volume; el.muted = muted; }
  }, [volume, muted, playback?.url]);

  const title = stream ? (stream.title[locale] || stream.title.en) : '';
  const isVideo = playback?.type === 'video';

  const mediaProps = playback && {
    src: playback.url,
    preload: 'auto' as const,
    onPlay: () => { setPlaying(true); setBlocked(false); },
    onPause: () => setPlaying(false),
    onTimeUpdate: (e: React.SyntheticEvent<HTMLMediaElement>) => setTime(e.currentTarget.currentTime),
    onLoadedMetadata: (e: React.SyntheticEvent<HTMLMediaElement>) => setDuration(e.currentTarget.duration || playback.duration_seconds),
    onEnded: refresh,
  };

  const togglePlay = () => {
    const el = mediaRef.current;
    if (!el) return;
    if (el.paused) el.play().catch(() => setBlocked(true));
    else el.pause();
  };

  const iconBtn = 'w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors shrink-0';

  return (
    <div
      dir="ltr"
      className="fixed bottom-20 start-3 md:bottom-6 md:start-6 z-50 w-64 rounded-2xl overflow-hidden shadow-2xl bg-gray-900 text-white"
      role="region"
      aria-label={title}
    >
      <div className="flex items-center gap-2 px-3 py-2">
        {follow && (
          <span className="inline-flex items-center gap-1 bg-red-600 text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0">
            <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" /> {t('radioLiveBadge')}
          </span>
        )}
        <Link href={`/live-streams/${streamId}`} className="flex-1 min-w-0 text-xs font-semibold truncate hover:underline">
          {isVideo ? '🎬' : '📻'} {title}
        </Link>
        <button type="button" onClick={() => setMinimized(m => !m)} className={iconBtn}
          aria-label={minimized ? t('radioMiniExpand') : t('radioMiniMinimize')}>
          {minimized ? '▴' : '▾'}
        </button>
        <button type="button" onClick={onClose} className={iconBtn} aria-label={t('radioMiniClose')}>✕</button>
      </div>

      {/* The media element stays mounted while minimized so playback never stops. */}
      <div className={minimized ? 'h-0 overflow-hidden' : ''}>
        <div className="relative">
          {mediaProps && (isVideo
            ? <video ref={mediaRef as React.Ref<HTMLVideoElement>} {...mediaProps} playsInline className="w-full aspect-video bg-black object-contain" poster={stream?.thumbnail_url ?? undefined} />
            : <audio ref={mediaRef as React.Ref<HTMLAudioElement>} {...mediaProps} className="hidden" />)}
          {blocked && (
            <button type="button" onClick={start} className="absolute inset-0 z-10 flex items-center justify-center bg-black/60">
              <span className="bg-white text-gray-900 text-xs font-semibold px-4 py-2 rounded-full">▶ {t('radioTapToPlay')}</span>
            </button>
          )}
        </div>

        {ended && <p className="px-3 pb-3 text-xs text-gray-300">{t('radioMiniEnded')}</p>}
        {stream && !playback && !ended && (
          <p className="px-3 pb-3 text-xs text-gray-300">
            {t('statusScheduled')} {stream.scheduled_at ? <LocalDateTime value={stream.scheduled_at} /> : ''}
          </p>
        )}

        {playback && (
          <div className="px-2 pb-2 space-y-1">
            {!follow && (
              <div className="flex items-center gap-2 px-1">
                <span className="text-[10px] tabular-nums text-gray-300 w-9 text-end">{fmt(time)}</span>
                <input
                  type="range" min={0} max={duration || playback.duration_seconds} step={1} value={Math.min(time, duration || playback.duration_seconds)}
                  onChange={e => { const el = mediaRef.current; if (el) el.currentTime = Number(e.target.value); setTime(Number(e.target.value)); }}
                  aria-label={t('radioMiniSeek')}
                  className="flex-1 accent-white cursor-pointer"
                />
                <span className="text-[10px] tabular-nums text-gray-300 w-9">{fmt(duration || playback.duration_seconds)}</span>
              </div>
            )}
            <div className="flex items-center gap-1">
              {!follow && (
                <button type="button" onClick={togglePlay} className={iconBtn} aria-label={playing ? t('radioPause') : t('radioPlay')}>
                  {playing ? '⏸' : '▶'}
                </button>
              )}
              <button type="button" onClick={() => setMuted(m => !m)} className={iconBtn} aria-label={muted ? t('unmute') : t('mute')}>
                {muted || volume === 0 ? '🔇' : '🔊'}
              </button>
              <input
                type="range" min={0} max={1} step={0.05} value={muted ? 0 : volume}
                onChange={e => { setVolume(parseFloat(e.target.value)); setMuted(false); }}
                aria-label={t('volume')}
                className="flex-1 accent-white cursor-pointer"
              />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
