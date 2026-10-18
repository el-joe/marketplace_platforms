'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { initEcho } from '@/src/lib/echo';
import { getStream, postSignal, postSignalKeepalive } from './api';
import type { LiveStreamDetail, MediaMode } from './types';

/**
 * Viewer side of the live-stream signalling protocol v2
 * (see docs/plans/live-streams-webrtc.md).
 */

export type ConnectionState =
  | 'idle'
  | 'connecting'
  | 'live'
  | 'reconnecting'
  | 'failed'
  | 'ended'
  | 'unavailable';

const ADMIN = 'admin';
const BACKOFF_MS = [1000, 2000, 4000, 8000, 8000];
const DISCONNECT_GRACE_MS = 5000;
const POLL_MS = 15000;
const CONNECT_TIMEOUT_MS = 15000;
const BROADCAST_TYPES = ['hello', 'media-state', 'ended'];

const buildIceServers = (): RTCIceServer[] => {
  const servers: RTCIceServer[] = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
  ];
  const turnUrl  = process.env.NEXT_PUBLIC_TURN_URL;
  const turnUser = process.env.NEXT_PUBLIC_TURN_USERNAME;
  const turnPass = process.env.NEXT_PUBLIC_TURN_CREDENTIAL;
  const turnTls  = process.env.NEXT_PUBLIC_TURN_TLS_URL;
  if (turnUrl && turnUser && turnPass) {
    servers.push({
      urls: turnTls ? [turnUrl, turnTls] : [turnUrl],
      username: turnUser,
      credential: turnPass,
    });
  }
  return servers;
};

const ICE_SERVERS = buildIceServers();

/** SDP must end with CRLF; middleware/proxies may trim it. */
function fixSdp(d: RTCSessionDescriptionInit): RTCSessionDescriptionInit {
  if (!d || typeof d.sdp !== 'string') return d;
  return { type: d.type, sdp: d.sdp.replace(/\r?\n/g, '\r\n').replace(/(\r\n)*$/, '\r\n') };
}

export function getGuestPeerId(): string {
  if (typeof window === 'undefined') return '';
  try {
    let id = sessionStorage.getItem('guest_peer_id');
    if (!id) {
      id = 'viewer-' + Math.random().toString(36).slice(2, 10);
      sessionStorage.setItem('guest_peer_id', id);
    }
    return id;
  } catch {
    return 'viewer-' + Math.random().toString(36).slice(2, 10);
  }
}

/** Makes sure window.Echo exists. Returns false when realtime is not configured. */
export function ensureEcho(): boolean {
  if (typeof window === 'undefined') return false;
  if (window.Echo) return true;
  if (!process.env.NEXT_PUBLIC_REVERB_APP_KEY || !process.env.NEXT_PUBLIC_REVERB_HOST) return false;
  try {
    initEcho();
  } catch {
    return false;
  }
  return !!window.Echo;
}

interface SignalMessage {
  type: string;
  payload: any;
  target?: string | null;
  from?: string | null;
}

interface Options {
  streamId: string;
  streamKey: string | null;
  enabled: boolean;
  initialMode?: MediaMode | null;
  /** Called with fresh data from the fallback status poll. */
  onStreamUpdate?: (s: LiveStreamDetail) => void;
}

export function useLiveStreamViewer({ streamId, streamKey, enabled, initialMode = null, onStreamUpdate }: Options) {
  const [connection, setConnection] = useState<ConnectionState>('idle');
  const [stream, setStream]         = useState<MediaStream | null>(null);
  const [mode, setMode]             = useState<MediaMode | null>(initialMode);
  const [videoActive, setVideoActive] = useState(false);
  const [muted, setMutedState]      = useState(true);
  const [volume, setVolumeState]    = useState(1);
  const [playBlocked, setPlayBlocked] = useState(false);

  const mediaRef     = useRef<HTMLVideoElement>(null);
  const peerId       = useRef('');
  const pcRef        = useRef<RTCPeerConnection | null>(null);
  const remoteIce    = useRef<RTCIceCandidateInit[]>([]);
  const remoteTracks = useRef<Map<string, MediaStreamTrack>>(new Map()); // kind -> track
  const localIce     = useRef<RTCIceCandidateInit[] | null>(null); // null = offer already sent
  const attempts     = useRef(0);
  const retryTimer   = useRef<ReturnType<typeof setTimeout> | null>(null);
  const discTimer    = useRef<ReturnType<typeof setTimeout> | null>(null);
  const endedRef     = useRef(false);
  const connectRef   = useRef<() => void>(() => {});
  const updateRef    = useRef(onStreamUpdate);
  updateRef.current  = onStreamUpdate;

  const clearTimers = () => {
    if (retryTimer.current) clearTimeout(retryTimer.current);
    if (discTimer.current) clearTimeout(discTimer.current);
    retryTimer.current = discTimer.current = null;
  };

  const closePc = () => {
    const pc = pcRef.current;
    pcRef.current = null;
    if (pc) {
      pc.ontrack = pc.onicecandidate = pc.onconnectionstatechange = null;
      pc.close();
    }
    remoteIce.current = [];
    localIce.current = [];
    remoteTracks.current.clear();
  };

  useEffect(() => { setMode(initialMode ?? null); }, [initialMode]);

  // ── Playback binding ─────────────────────────────────────────────────────
  const tryPlay = useCallback(() => {
    const el = mediaRef.current;
    if (!el || !el.srcObject) return;
    el.play().then(() => setPlayBlocked(false)).catch((e: unknown) => {
      if ((e as DOMException)?.name === 'NotAllowedError') setPlayBlocked(true);
    });
  }, []);

  useEffect(() => {
    const el = mediaRef.current;
    if (!el) return;
    if (el.srcObject !== stream) el.srcObject = stream;
    el.muted = muted;
    el.volume = volume;
    if (stream) tryPlay();
  }, [stream, muted, volume, tryPlay, mode]);

  // Broadcaster switched mode (e.g. both -> audio): re-attach and restart playback so audio resumes.
  useEffect(() => {
    const el = mediaRef.current;
    if (!el || !el.srcObject) return;
    const current = el.srcObject;
    el.srcObject = null;
    el.srcObject = current;
    el.muted = muted;
    tryPlay();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mode]);

  /** Must be called from a user gesture. Unmutes and starts audio playback. */
  const listen = useCallback(async () => {
    const el = mediaRef.current;
    if (!el) return;
    el.muted = false;
    setMutedState(false);
    if (el.volume === 0) { el.volume = 1; setVolumeState(1); }
    try {
      await el.play();
      setPlayBlocked(false);
    } catch (e) {
      if ((e as DOMException)?.name === 'NotAllowedError') setPlayBlocked(true);
    }
  }, []);

  const setMuted = useCallback((m: boolean) => {
    setMutedState(m);
    if (mediaRef.current) mediaRef.current.muted = m;
  }, []);

  const setVolume = useCallback((v: number) => {
    const val = Math.min(1, Math.max(0, v));
    setVolumeState(val);
    if (mediaRef.current) mediaRef.current.volume = val;
    if (val > 0 && mediaRef.current?.muted) setMuted(false);
  }, [setMuted]);

  // ── Core connection logic ────────────────────────────────────────────────
  useEffect(() => {
    if (!enabled || !streamKey) {
      setConnection(c => (c === 'ended' ? c : 'idle'));
      return;
    }
    if (!ensureEcho()) {
      setConnection('unavailable');
      return;
    }

    peerId.current = getGuestPeerId();
    endedRef.current = false;
    attempts.current = 0;
    const key = streamKey;
    const channelName = `stream.${key}`;
    const me = peerId.current;
    let disposed = false;

    const flushRemoteIce = async (pc: RTCPeerConnection) => {
      const queued = remoteIce.current.splice(0);
      for (const c of queued) await pc.addIceCandidate(c).catch(() => {});
    };

    const flushLocalIce = () => {
      const queued = localIce.current ?? [];
      localIce.current = null;
      queued.forEach(c => postSignal(streamId, 'ice-candidate', c, me).catch(() => {}));
    };

    const markEnded = () => {
      endedRef.current = true;
      clearTimers();
      closePc();
      setStream(null);
      setConnection('ended');
    };

    const scheduleReconnect = () => {
      if (disposed || endedRef.current || retryTimer.current) return;
      if (attempts.current >= BACKOFF_MS.length) {
        closePc();
        setConnection('failed');
        return;
      }
      const delay = BACKOFF_MS[attempts.current++];
      setConnection('reconnecting');
      retryTimer.current = setTimeout(() => {
        retryTimer.current = null;
        connect();
      }, delay);
    };

    const connect = async () => {
      if (disposed || endedRef.current) return;
      clearTimers();
      closePc();
      const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
      pcRef.current = pc;
      setVideoActive(false);

      // The broadcaster is the only offerer: we just say hello and answer its
      // offer (no transceivers here, so there is no offer glare).

      pc.ontrack = (ev) => {
        if (pcRef.current !== pc) return;
        // Audio and video can arrive in separate remote streams; merge them so the
        // element always carries every live track.
        remoteTracks.current.set(ev.track.kind, ev.track);
        setStream(new MediaStream([...remoteTracks.current.values()]));
        if (ev.track.kind === 'video') {
          setVideoActive(!ev.track.muted);
          ev.track.onunmute = () => setVideoActive(true);
          ev.track.onmute   = () => setVideoActive(false);
        }
      };

      pc.onicecandidate = ({ candidate }) => {
        if (!candidate || pcRef.current !== pc) return;
        const init = candidate.toJSON();
        if (localIce.current) localIce.current.push(init);
        else postSignal(streamId, 'ice-candidate', init, me).catch(() => {});
      };

      pc.onconnectionstatechange = () => {
        if (pcRef.current !== pc) return;
        const s = pc.connectionState;
        if (s === 'connected') {
          if (discTimer.current) { clearTimeout(discTimer.current); discTimer.current = null; }
          attempts.current = 0;
          setConnection('live');
        } else if (s === 'disconnected') {
          setConnection('reconnecting');
          if (!discTimer.current) {
            discTimer.current = setTimeout(() => {
              discTimer.current = null;
              if (pcRef.current === pc && pc.connectionState !== 'connected') scheduleReconnect();
            }, DISCONNECT_GRACE_MS);
          }
        } else if (s === 'failed') {
          scheduleReconnect();
        }
      };

      // Give up on this attempt if the broadcaster never connects.
      discTimer.current = setTimeout(() => {
        discTimer.current = null;
        if (pcRef.current === pc && pc.connectionState !== 'connected') scheduleReconnect();
      }, CONNECT_TIMEOUT_MS);

      try {
        await postSignal(streamId, 'hello', {}, me);
      } catch {
        if (pcRef.current === pc) scheduleReconnect();
      }
    };
    connectRef.current = () => {
      attempts.current = 0;
      clearTimers();
      setConnection('connecting');
      connect();
    };

    const onSignal = async (msg: SignalMessage) => {
      if (!msg || msg.from !== ADMIN) return;
      const addressed = msg.target === me || (msg.target == null && BROADCAST_TYPES.includes(msg.type));
      if (!addressed) return;
      const pc = pcRef.current;

      switch (msg.type) {
        case 'hello': // broadcaster (re)started -> fresh negotiation
          if (!endedRef.current) { attempts.current = 0; clearTimers(); setConnection('reconnecting'); connect(); }
          break;
        case 'ended':
          markEnded();
          break;
        case 'media-state': {
          const m = msg.payload?.media_mode ?? msg.payload?.mode ?? null;
          if (m === 'audio' || m === 'video' || m === 'both' || m === null) setMode(m);
          break;
        }
        case 'answer':
          if (pc && pc.signalingState === 'have-local-offer' && msg.payload) {
            try {
              await pc.setRemoteDescription(fixSdp(msg.payload));
              await flushRemoteIce(pc);
            } catch { scheduleReconnect(); }
          }
          break;
        case 'offer':
          if (pc && msg.payload) {
            try {
              if (pc.signalingState !== 'stable') await pc.setLocalDescription({ type: 'rollback' });
              await pc.setRemoteDescription(fixSdp(msg.payload));
              await flushRemoteIce(pc);
              const answer = await pc.createAnswer();
              await pc.setLocalDescription(answer);
              await postSignal(streamId, 'answer', pc.localDescription ?? answer, me);
              if (pcRef.current === pc) flushLocalIce();
            } catch { /* connect timeout will retry */ }
          }
          break;
        case 'ice-candidate':
          if (pc && msg.payload) {
            if (pc.remoteDescription) await pc.addIceCandidate(msg.payload).catch(() => {});
            else remoteIce.current.push(msg.payload);
          }
          break;
      }
    };

    setConnection('connecting');
    const channel = window.Echo.channel(channelName);
    channel.listen('.signal', onSignal);

    // Wait for the subscription so `hello` cannot race the admin's reply.
    let started = false;
    const start = () => { if (!started && !disposed) { started = true; connect(); } };
    try {
      (channel as unknown as { subscribed?: (cb: () => void) => void }).subscribed?.(start);
    } catch { /* ignore */ }
    const startFallback = setTimeout(start, 2500);

    // Fallback status poll
    const poll = setInterval(async () => {
      try {
        const s = await getStream(streamId);
        if (disposed) return;
        updateRef.current?.(s);
        if (s.status === 'ended') markEnded();
        else if (s.media_mode !== undefined) setMode(s.media_mode);
      } catch { /* ignore */ }
    }, POLL_MS);

    const onPageHide = () => postSignalKeepalive(streamId, 'bye', {}, me);
    window.addEventListener('pagehide', onPageHide);

    return () => {
      disposed = true;
      clearTimeout(startFallback);
      clearInterval(poll);
      clearTimers();
      window.removeEventListener('pagehide', onPageHide);
      if (pcRef.current) postSignalKeepalive(streamId, 'bye', {}, me);
      closePc();
      try {
        channel.stopListening('.signal', onSignal);
        window.Echo?.leaveChannel(channelName);
      } catch { /* ignore */ }
      connectRef.current = () => {};
      setStream(null);
    };
  }, [enabled, streamKey, streamId]);

  const reconnect = useCallback(() => connectRef.current(), []);

  const hasVideo = mode ? mode !== 'audio' : videoActive;

  return {
    mediaRef,
    stream,
    mode,
    hasVideo,
    connection,
    muted,
    volume,
    setMuted,
    setVolume,
    /** true when the browser blocked playback and a tap is required */
    needsGesture: muted || playBlocked,
    listen,
    reconnect,
  };
}
