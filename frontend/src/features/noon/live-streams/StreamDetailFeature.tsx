'use client';

import { useEffect, useRef, useState, useCallback } from 'react';
import { useLocale } from 'next-intl';
import Image from 'next/image';
import { getStream, postComment, postLike, postSignal } from './api';
import type { LiveStreamDetail, StreamComment } from './types';

const buildIceServers = (): RTCIceServer[] => {
  const servers: RTCIceServer[] = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
  ];

  const turnUrl  = process.env.NEXT_PUBLIC_TURN_URL;
  const turnUser = process.env.NEXT_PUBLIC_TURN_USERNAME;
  const turnPass = process.env.NEXT_PUBLIC_TURN_CREDENTIAL;

  if (turnUrl && turnUser && turnPass) {
    servers.push({
      urls:       [turnUrl, turnUrl.replace('turn:', 'turns:').replace(':3478', ':5349')],
      username:   turnUser,
      credential: turnPass,
    });
  }

  return servers;
};

const ICE_SERVERS = buildIceServers();

function getGuestPeerId(): string {
  let id = sessionStorage.getItem('guest_peer_id');
  if (!id) {
    id = 'viewer-' + Math.random().toString(36).slice(2, 10);
    sessionStorage.setItem('guest_peer_id', id);
  }
  return id;
}

function getGuestToken(): string {
  let t = localStorage.getItem('guest_like_token');
  if (!t) {
    t = Math.random().toString(36).slice(2, 18);
    localStorage.setItem('guest_like_token', t);
  }
  return t;
}

export default function StreamDetailFeature({ streamId }: { streamId: string }) {
  const locale      = useLocale() as 'en' | 'ar';
  const [stream, setStream]       = useState<LiveStreamDetail | null>(null);
  const [loading, setLoading]     = useState(true);
  const [comments, setComments]   = useState<StreamComment[]>([]);
  const [commentBody, setCommentBody] = useState('');
  const [guestName, setGuestName] = useState('Guest');
  const [likesCount, setLikesCount] = useState(0);
  const [likeAnim, setLikeAnim]   = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const videoRef    = useRef<HTMLVideoElement>(null);
  const commentsRef = useRef<HTMLDivElement>(null);
  const pcRef       = useRef<RTCPeerConnection | null>(null);
  const peerId      = useRef(getGuestPeerId());

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

  // ── WebRTC: connect as viewer ──────────────────────────────────────────────
  const startWebRTC = useCallback(async (streamKey: string) => {
    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    pcRef.current = pc;

    pc.addTransceiver('video', { direction: 'recvonly' });
    pc.addTransceiver('audio', { direction: 'recvonly' });

    pc.ontrack = (event) => {
      if (videoRef.current && event.streams[0]) {
        videoRef.current.srcObject = event.streams[0];
      }
    };

    pc.onicecandidate = ({ candidate }) => {
      if (candidate) {
        postSignal(streamId, 'ice-candidate', candidate.toJSON(), peerId.current).catch(() => {});
      }
    };

    if (window.Echo) {
      window.Echo.channel(`stream.${streamKey}`)
        .listen('.signal', (data: any) => {
          if (data.target && data.target !== peerId.current) return;
          if (data.type === 'answer' && pcRef.current) {
            pcRef.current.setRemoteDescription(new RTCSessionDescription(data.payload)).catch(() => {});
          } else if (data.type === 'ice-candidate' && pcRef.current) {
            pcRef.current.addIceCandidate(new RTCIceCandidate(data.payload)).catch(() => {});
          }
        })
        .listen('.comment', (data: any) => {
          setComments(prev => [
            ...prev,
            { id: data.id, author: data.author, body: data.body, created_at: data.created_at },
          ]);
        })
        .listen('.like', (data: any) => {
          setLikesCount(data.likes_count);
        });
    }

    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    await postSignal(streamId, 'offer', offer, peerId.current);
  }, [streamId]);

  useEffect(() => {
    if (stream?.status === 'live' && stream.stream_key) {
      startWebRTC(stream.stream_key);
    }
    return () => {
      pcRef.current?.close();
      if (window.Echo && stream?.stream_key) {
        window.Echo.leaveChannel(`stream.${stream.stream_key}`);
      }
    };
  }, [stream?.status, stream?.stream_key, startWebRTC]);

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
      setComments(prev => [...prev, comment]);
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
      <p className="text-xl font-medium">Stream not found</p>
    </div>
  );

  const title = stream.title[locale] || stream.title.en;

  return (
    <div className="container py-6 space-y-4">

      {/* Title + status badge row */}
      <div className="flex items-center gap-3 flex-wrap">
        <h1 className="text-xl font-bold text-gray-900 flex-1">{title}</h1>
        {stream.status === 'live' && (
          <span className="inline-flex items-center gap-1.5 bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full">
            <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" /> LIVE
          </span>
        )}
        {stream.status === 'scheduled' && (
          <span className="bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full">
            Scheduled {stream.scheduled_at ? new Date(stream.scheduled_at).toLocaleString() : ''}
          </span>
        )}
        {stream.status === 'ended' && (
          <span className="bg-gray-100 text-gray-600 text-xs font-semibold px-3 py-1 rounded-full">
            Stream Ended
          </span>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

        {/* Video column */}
        <div className="lg:col-span-2 space-y-3">
          <div className="relative aspect-video bg-black rounded-2xl overflow-hidden">
            {stream.status === 'live' ? (
              <video
                ref={videoRef}
                autoPlay
                playsInline
                className="w-full h-full object-cover"
                poster={stream.thumbnail_url ?? undefined}
              />
            ) : stream.thumbnail_url ? (
              <Image src={stream.thumbnail_url} alt={title} fill className="object-cover" sizes="100vw" />
            ) : (
              <div className="w-full h-full flex flex-col items-center justify-center text-gray-500 gap-3">
                <span className="text-6xl">📹</span>
                <p className="text-sm">
                  {stream.status === 'scheduled' ? 'Stream starting soon' : 'Stream has ended'}
                </p>
              </div>
            )}

            {stream.status === 'live' && (
              <div className="absolute bottom-3 left-3 right-3 flex items-center justify-between">
                <span className="bg-black/60 text-white text-xs px-2.5 py-1 rounded-full">
                  👁 {stream.total_viewers.toLocaleString()} watching
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

          {stream.status !== 'live' && (
            <div className="flex items-center gap-3">
              <span className="text-sm text-gray-500">❤️ {likesCount.toLocaleString()} likes</span>
              <span className="text-sm text-gray-500">👁 {stream.total_viewers.toLocaleString()} total viewers</span>
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
            💬 {stream.status === 'live' ? 'Live Chat' : 'Comments'} ({comments.length})
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
              <p className="text-xs text-gray-400 text-center mt-8">No comments yet. Be the first!</p>
            )}
          </div>

          {stream.status === 'live' && (
            <div className="border-t border-gray-100 p-3 space-y-2">
              <input
                type="text"
                placeholder="Your name (optional)"
                value={guestName}
                onChange={e => setGuestName(e.target.value)}
                className="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary-400"
              />
              <div className="flex gap-2">
                <input
                  type="text"
                  placeholder="Say something…"
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
                  Send
                </button>
              </div>
            </div>
          )}
        </div>

      </div>
    </div>
  );
}
