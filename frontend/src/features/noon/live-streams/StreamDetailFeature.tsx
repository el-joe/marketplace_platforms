'use client';

import { useEffect, useRef, useState, useCallback } from 'react';
import { useTranslations } from 'next-intl';
import useLocale from '@/src/hooks/use-locale';
import Image from 'next/image';
import Link from 'next/link';
import { getStream, postComment, postLike } from './api';
import { ensureEcho, useLiveStreamViewer, type ConnectionState } from './useLiveStreamViewer';
import type { LiveStreamDetail, StreamComment } from './types';
import { getImageURL } from '@/src/helpers/get-image-url';
import RecordedPlayer from './RecordedPlayer';

function getGuestToken(): string {
  if (typeof window === 'undefined') return '';
  let t = localStorage.getItem('guest_like_token');
  if (!t) {
    t = Math.random().toString(36).slice(2, 18);
    localStorage.setItem('guest_like_token', t);
  }
  return t;
}

type Viewer = ReturnType<typeof useLiveStreamViewer>;
type T = ReturnType<typeof useTranslations>;

function ConnectionOverlay({
  connection, hasVideo, onReconnect, t,
}: { connection: ConnectionState; hasVideo: boolean; onReconnect: () => void; t: T }) {
  if (connection === 'unavailable') {
    return (
      <div className="absolute inset-x-3 top-3 z-10 rounded-lg bg-amber-100 text-amber-900 text-xs px-3 py-2">
        {t('realtimeUnavailable')}
      </div>
    );
  }
  if (connection === 'failed') {
    return (
      <div className="absolute inset-0 z-10 bg-black/70 flex flex-col items-center justify-center gap-3 text-white text-center px-6">
        <p className="text-sm">{t('connectionFailed')}</p>
        <button onClick={onReconnect} className="bg-white text-gray-900 text-sm font-semibold px-4 py-2 rounded-full">
          {t('reconnect')}
        </button>
      </div>
    );
  }
  const label =
    connection === 'live' ? null
    : connection === 'reconnecting' ? t('reconnecting')
    : hasVideo ? t('connecting') : null;
  if (!label) return null;
  return (
    <div className="absolute top-3 start-3 z-10 inline-flex items-center gap-2 bg-black/70 text-white text-xs px-3 py-1.5 rounded-full">
      <span className="w-3 h-3 rounded-full border-2 border-white/40 border-t-white animate-spin" />
      {label}
    </div>
  );
}

function VolumeIcon({ muted, volume }: { muted: boolean; volume: number }) {
  const off = muted || volume === 0;
  return (
    <svg viewBox="0 0 24 24" className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      <path d="M11 5 6 9H3v6h3l5 4V5z" fill="currentColor" />
      {off ? (
        <>
          <line x1="17" y1="9" x2="23" y2="15" />
          <line x1="23" y1="9" x2="17" y2="15" />
        </>
      ) : (
        <>
          <path d="M15.5 8.5a5 5 0 0 1 0 7" />
          {volume > 0.5 && <path d="M18.5 5.5a9 9 0 0 1 0 13" />}
        </>
      )}
    </svg>
  );
}

function PlayerControls({
  viewer, t, onFullscreen, showFullscreen, className = '',
}: { viewer: Viewer; t: T; onFullscreen?: () => void; showFullscreen?: boolean; className?: string }) {
  const silent = viewer.muted || viewer.volume === 0;
  const toggle = () => (viewer.muted ? viewer.listen() : viewer.setMuted(true));
  const shown = silent ? 0 : viewer.volume;
  return (
    <div className={`flex items-center gap-2 rounded-full bg-black/60 backdrop-blur px-2 py-1.5 text-white ${className}`} dir="ltr">
      <button
        type="button"
        onClick={toggle}
        aria-label={silent ? t('unmute') : t('mute')}
        title={silent ? t('unmute') : t('mute')}
        className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors"
      >
        <VolumeIcon muted={viewer.muted} volume={viewer.volume} />
      </button>
      <input
        type="range" min={0} max={1} step={0.05}
        value={shown}
        onChange={e => viewer.setVolume(parseFloat(e.target.value))}
        aria-label={t('volume')}
        className="w-20 sm:w-28 accent-white cursor-pointer"
      />
      {showFullscreen && (
        <button
          type="button"
          onClick={onFullscreen}
          aria-label={t('fullscreen')}
          title={t('fullscreen')}
          className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors"
        >
          <svg viewBox="0 0 24 24" className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
            <path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3" />
          </svg>
        </button>
      )}
    </div>
  );
}

function AudioCard({
  thumbnail, title, connection, viewer, t,
}: { thumbnail: string | null; title: string; connection: ConnectionState; viewer: Viewer; t: T }) {
  const playing = connection === 'live' && !viewer.muted;
  const status =
    connection === 'live' ? (viewer.stream ? t('connLive') : t('waitingForBroadcaster'))
    : connection === 'reconnecting' ? t('reconnecting')
    : connection === 'connecting' ? t('connecting')
    : '';
  return (
    <div className="absolute inset-0 flex flex-col items-center justify-center gap-4 px-6 text-white bg-gradient-to-br from-gray-900 via-gray-800 to-primary-900">
      {thumbnail && (
        <Image src={thumbnail} alt="" fill className="object-cover opacity-20 blur-sm" sizes="100vw" />
      )}
      <div className="relative z-[1] flex flex-col items-center gap-4 w-full max-w-sm">
        <span className="inline-flex items-center gap-1.5 bg-white/15 text-xs font-semibold px-3 py-1 rounded-full">
          🎧 {t('audioOnly')}
        </span>
        <div className="flex items-end gap-1 h-12" aria-hidden>
          {[0.5, 0.9, 0.6, 1, 0.7, 0.95, 0.55].map((h, i) => (
            <span
              key={i}
              className={`w-1.5 rounded-full bg-white/80 ${playing ? 'animate-pulse' : 'opacity-40'}`}
              style={{ height: `${h * 100}%`, animationDelay: `${i * 120}ms`, animationDuration: '900ms' }}
            />
          ))}
        </div>
        <p className="text-center text-sm font-semibold line-clamp-2">{title}</p>
        {status && <p className="text-xs text-gray-300">{status}</p>}

        {viewer.needsGesture ? (
          <button
            onClick={viewer.listen}
            disabled={!viewer.stream}
            className="bg-white text-gray-900 font-semibold text-sm px-5 py-2.5 rounded-full shadow disabled:opacity-60"
          >
            🔊 {t('tapToListen')}
          </button>
        ) : (
          <PlayerControls viewer={viewer} t={t} className="w-full max-w-xs justify-center" />
        )}
      </div>
    </div>
  );
}

export default function StreamDetailFeature({ streamId }: { streamId: string }) {
  const locale      = useLocale() as 'en' | 'ar';
  const t           = useTranslations('liveStreams');
  const [stream, setStream]       = useState<LiveStreamDetail | null>(null);
  const [loading, setLoading]     = useState(true);
  const [comments, setComments]   = useState<StreamComment[]>([]);
  const [commentBody, setCommentBody] = useState('');
  const [guestName, setGuestName] = useState('Guest');
  const [likesCount, setLikesCount] = useState(0);
  const [likeAnim, setLikeAnim]   = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const commentsRef = useRef<HTMLDivElement>(null);
  const playerRef = useRef<HTMLDivElement>(null);
  const toggleFullscreen = useCallback(() => {
    const el = playerRef.current;
    if (!el) return;
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    else el.requestFullscreen?.().catch(() => {});
  }, []);

  // ── Load stream data ───────────────────────────────────────────────────────
  useEffect(() => {
    getStream(streamId)
      .then(data => {
        setStream(data);
        setComments(data.comments);
        setLikesCount(data.likes_count);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [streamId]);

  const isLive = stream?.status === 'live';
  const isRecorded = stream?.source === 'recorded';
  const streamKey = stream?.stream_key ?? null;

  const handleStreamUpdate = useCallback((fresh: LiveStreamDetail) => {
    setStream(prev => (prev ? { ...prev, ...fresh, comments: prev.comments } : fresh));
  }, []);

  const viewer = useLiveStreamViewer({
    streamId,
    streamKey,
    enabled: isLive && !isRecorded,
    initialMode: stream?.media_mode ?? null,
    onStreamUpdate: handleStreamUpdate,
  });
  // A scheduled page must notice when the admin goes live (the viewer hook only polls while live).
  useEffect(() => {
    if (stream?.status !== 'scheduled') return;
    const timer = setInterval(() => {
      getStream(streamId).then(handleStreamUpdate).catch(() => {});
    }, 15000);
    return () => clearInterval(timer);
  }, [stream?.status, streamId, handleStreamUpdate]);

  // An on-air radio channel ends by itself: re-check often, and right when the file finishes.
  const refreshStream = useCallback(() => {
    getStream(streamId).then(handleStreamUpdate).catch(() => {});
  }, [streamId, handleStreamUpdate]);
  useEffect(() => {
    if (!isRecorded || !isLive) return;
    const timer = setInterval(refreshStream, 15000);
    return () => clearInterval(timer);
  }, [isRecorded, isLive, refreshStream]);

  const { connection, hasVideo } = viewer;
  const viewerEnded = connection === 'ended';
  const showLive = isLive && !viewerEnded;

  // ── Realtime comments / likes (same public channel; no leaveChannel here) ──
  useEffect(() => {
    if (!isLive || !streamKey || !ensureEcho()) return;
    const channel = window.Echo.channel(`stream.${streamKey}`);
    const onComment = (data: any) =>
      setComments(prev =>
        prev.some(c => c.id === data.id)
          ? prev
          : [...prev, { id: data.id, author: data.author, body: data.body, created_at: data.created_at }],
      );
    const onLike = (data: any) => setLikesCount(data.likes_count);
    channel.listen('.comment', onComment).listen('.like', onLike);
    return () => {
      channel.stopListening('.comment', onComment);
      channel.stopListening('.like', onLike);
    };
  }, [isLive, streamKey]);

  // ── Auto-scroll comments ───────────────────────────────────────────────────
  useEffect(() => {
    if (commentsRef.current) {
      commentsRef.current.scrollTop = commentsRef.current.scrollHeight;
    }
  }, [comments]);

  // ── Like ──────────────────────────────────────────────────────────────────
  const handleLike = async () => {
    setLikeAnim(true);
    setTimeout(() => setLikeAnim(false), 600);
    try {
      const newCount = await postLike(streamId, getGuestToken());
      setLikesCount(newCount);
    } catch {}
  };

  // ── Comment ───────────────────────────────────────────────────────────────
  const handleComment = async () => {
    if (!commentBody.trim() || submitting) return;
    setSubmitting(true);
    try {
      const comment = await postComment(streamId, commentBody.trim(), guestName);
      setComments(prev => (prev.some(c => c.id === comment.id) ? prev : [...prev, comment]));
      setCommentBody('');
    } catch {}
    setSubmitting(false);
  };

  // ── Loading / not-found states ────────────────────────────────────────────
  if (loading) return (
    <div className="container py-12 flex items-center justify-center">
      <div className="animate-pulse flex flex-col items-center gap-3 w-full">
        <div className="w-full max-w-3xl aspect-video bg-gray-200 rounded-2xl" />
        <div className="w-64 h-5 bg-gray-200 rounded" />
      </div>
    </div>
  );

  if (!stream) return (
    <div className="container py-24 text-center text-gray-400">
      <p className="text-xl font-medium">{t('notFound')}</p>
    </div>
  );

  const title = stream.title[locale] || stream.title.en;
  const thumbUrl = stream.thumbnail_url ? getImageURL(stream.thumbnail_url) : null;

  return (
    <div className="container py-6 space-y-4">

      {/* Title + status badge row */}
      <div className="flex items-center gap-3 flex-wrap">
        <h1 className="text-xl font-bold text-gray-900 flex-1">{title}</h1>
        {isRecorded && (
          <span className="bg-primary-100 text-primary-700 text-xs font-semibold px-3 py-1 rounded-full">
            {stream.media_type === 'video' ? '🎬' : '📻'} {t('radioChannel')}
          </span>
        )}
        {showLive && (
          <span className="inline-flex items-center gap-1.5 bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full">
            <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" /> {t('statusLive')}
          </span>
        )}
        {stream.status === 'scheduled' && (
          <span className="bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full">
            {t('statusScheduled')} {stream.scheduled_at ? new Date(stream.scheduled_at).toLocaleString() : ''}
          </span>
        )}
        {(stream.status === 'ended' || viewerEnded) && (
          <span className="bg-gray-100 text-gray-600 text-xs font-semibold px-3 py-1 rounded-full">
            {t('streamEnded')}
          </span>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

        {/* Video column */}
        <div className="lg:col-span-2 space-y-3">
          <div ref={playerRef} className="relative aspect-video bg-black rounded-2xl overflow-hidden">
            {isRecorded && stream.playback && (
              <RecordedPlayer
                key={`${stream.playback.url}-${isLive ? 'live' : 'replay'}`}
                playback={stream.playback}
                onAir={isLive}
                title={title}
                thumbnail={thumbUrl}
                labels={{ onAir: t('radioNowPlaying'), replay: t('radioReplay'), tapToPlay: t('radioTapToPlay'), playFailed: t('radioPlayFailed') }}
                onFinished={refreshStream}
              />
            )}
            {isLive && !isRecorded && !viewerEnded && (
              <>
                <video
                  ref={viewer.mediaRef}
                  autoPlay
                  muted
                  playsInline
                  className={hasVideo ? 'w-full h-full object-contain bg-black' : 'absolute h-px w-px opacity-0 pointer-events-none'}
                  poster={thumbUrl ?? undefined}
                />
                {!hasVideo && (
                  <AudioCard
                    thumbnail={thumbUrl}
                    title={title}
                    connection={connection}
                    viewer={viewer}
                    t={t}
                  />
                )}
                <ConnectionOverlay connection={connection} hasVideo={hasVideo} onReconnect={viewer.reconnect} t={t} />
                {hasVideo && connection === 'live' && (
                  viewer.needsGesture && viewer.muted ? (
                    <button
                      onClick={viewer.listen}
                      className="absolute top-3 end-3 z-10 inline-flex items-center gap-1.5 bg-white text-gray-900 text-xs font-semibold px-3 py-1.5 rounded-full shadow animate-pulse"
                    >
                      🔇 {t('tapToListen')}
                    </button>
                  ) : null
                )}
                {hasVideo && connection === 'live' && (
                  <PlayerControls
                    viewer={viewer}
                    t={t}
                    showFullscreen
                    onFullscreen={toggleFullscreen}
                    className="absolute bottom-14 end-3 z-10"
                  />
                )}
              </>
            )}
            {viewerEnded && (
              <div className="w-full h-full flex flex-col items-center justify-center gap-3 text-center px-6 bg-gray-900 text-white">
                <span className="text-5xl">📴</span>
                <p className="text-lg font-bold">{t('streamEnded')}</p>
                <p className="text-sm text-gray-300">{t('streamEndedBody')}</p>
                <Link href="/live-streams" className="mt-1 bg-white text-gray-900 text-sm font-semibold px-4 py-2 rounded-full">
                  {t('backToStreams')}
                </Link>
              </div>
            )}
            {!isLive && !viewerEnded && !(isRecorded && stream.playback) && (thumbUrl ? (
              <Image src={thumbUrl} alt={title} fill className="object-cover" sizes="100vw" />
            ) : (
              <div className="w-full h-full flex flex-col items-center justify-center text-gray-500 gap-3">
                <span className="text-6xl">📹</span>
                <p className="text-sm">
                  {stream.status === 'scheduled' ? t('startingSoon') : t('hasEnded')}
                </p>
              </div>
            ))}

            {showLive && (
              <div className="absolute bottom-3 left-3 right-3 flex items-center justify-between">
                <span className="bg-black/60 text-white text-xs px-2.5 py-1 rounded-full">
                  👁 {stream.total_viewers.toLocaleString()} {t('watching')}
                </span>
                <button
                  onClick={handleLike}
                  className={`flex items-center gap-1.5 bg-black/60 hover:bg-red-600 text-white text-sm font-semibold px-3 py-1.5 rounded-full transition-all ${likeAnim ? 'scale-125' : 'scale-100'}`}
                >
                  ❤️ {likesCount.toLocaleString()}
                </button>
              </div>
            )}
          </div>

          {!showLive && (
            <div className="flex items-center gap-3">
              <span className="text-sm text-gray-500">❤️ {likesCount.toLocaleString()} {t('likes')}</span>
              <span className="text-sm text-gray-500">👁 {stream.total_viewers.toLocaleString()} {t('totalViewers')}</span>
            </div>
          )}

          {(stream.description.en || stream.description.ar) && (
            <div className="bg-white rounded-xl border border-gray-200 p-4">
              <p className="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                {stream.description[locale] || stream.description.en}
              </p>
            </div>
          )}
        </div>

        {/* Chat / Comments column */}
        <div
          className="bg-white rounded-xl border border-gray-200 flex flex-col overflow-hidden"
          style={{ height: '520px' }}
        >
          <div className="px-4 py-3 border-b border-gray-100 font-semibold text-gray-800 text-sm">
            💬 {showLive ? t('liveChat') : t('comments')} ({comments.length})
          </div>

          <div ref={commentsRef} className="flex-1 overflow-y-auto p-3 space-y-2">
            {comments.map(c => (
              <div key={c.id} className="flex gap-2">
                <div className="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-600 shrink-0">
                  {c.author.charAt(0).toUpperCase()}
                </div>
                <div>
                  <span className="text-xs font-semibold text-gray-700">{c.author}</span>
                  <p className="text-xs text-gray-600 mt-0.5 leading-snug">{c.body}</p>
                </div>
              </div>
            ))}
            {comments.length === 0 && (
              <p className="text-xs text-gray-400 text-center mt-8">{t('noCommentsYet')}</p>
            )}
          </div>

          {showLive && (
            <div className="border-t border-gray-100 p-3 space-y-2">
              <input
                type="text"
                placeholder={t('guestNamePlaceholder')}
                value={guestName}
                onChange={e => setGuestName(e.target.value)}
                className="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary-400"
              />
              <div className="flex gap-2">
                <input
                  type="text"
                  placeholder={t('commentPlaceholder')}
                  value={commentBody}
                  onChange={e => setCommentBody(e.target.value)}
                  onKeyDown={e => e.key === 'Enter' && handleComment()}
                  className="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary-400"
                />
                <button
                  onClick={handleComment}
                  disabled={submitting}
                  className="px-3 py-1.5 bg-primary-600 text-white rounded-lg text-xs font-medium hover:bg-primary-700 disabled:opacity-50"
                >
                  {t('send')}
                </button>
              </div>
            </div>
          )}
        </div>

      </div>
    </div>
  );
}
