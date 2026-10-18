/**
 * Admin live-stream studio - WebRTC publisher, signalling protocol v2.
 *
 * Signal envelope (Reverb event ".signal" on channel "stream.{key}"):
 *   { type, payload, target, from }
 *   type: offer | answer | ice-candidate | hello | bye | media-state | ended
 * Admin id = "admin". Viewers = "viewer-xxxx". This script ignores every message
 * whose target !== "admin". One RTCPeerConnection per viewer (mesh).
 */
(function () {
  'use strict';

  const META = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
  const STREAM_KEY = META('stream-key');
  const STREAM_ID = META('stream-id');
  const CSRF = META('csrf-token');
  const I18N = window.LIVE_STUDIO_I18N || {};
  const t = (key, vars) => {
    let s = I18N[key] ?? key;
    if (vars) Object.keys(vars).forEach((k) => { s = s.replace(':' + k, vars[k]); });
    return s;
  };
  const $ = (id) => document.getElementById(id);
  const show = (el, on) => el && el.classList.toggle('hidden', !on);

  // ─── State ────────────────────────────────────────────────────────────────
  let isLive = META('stream-status') === 'live';
  let isEnded = META('stream-status') === 'ended';
  let mode = 'both';              // UI mode: both | video | audio | screen
  let audioTrack = null;
  let videoTrack = null;
  let rawStreams = [];            // every acquired MediaStream (for cleanup)
  let mixCtx = null;              // AudioContext used for screen+mic mixing
  let micMuted = false;
  let camOff = false;
  let previewActive = false;
  let acquiring = false;
  const peers = new Map();        // peerId -> { pc, pendingIce, makingOffer, senders, disconnectTimer }

  // ─── Errors ───────────────────────────────────────────────────────────────
  function showError(msg) {
    const el = $('studio-error');
    if (!el) return;
    el.textContent = msg || '';
    show(el, !!msg);
  }

  function mediaErrorMessage(err) {
    if (!window.isSecureContext) return t('err_insecure');
    switch (err && err.name) {
      case 'NotAllowedError':
      case 'SecurityError': return t('err_not_allowed');
      case 'NotFoundError':
      case 'DevicesNotFoundError': return t('err_not_found');
      case 'NotReadableError':
      case 'TrackStartError': return t('err_not_readable');
      case 'OverconstrainedError': return t('err_overconstrained');
      default: return t('err_generic') + (err && err.message ? ': ' + err.message : '');
    }
  }

  // ─── Realtime (bundled window.Echo) ───────────────────────────────────────
  function setConn(state) {
    const dot = $('conn-dot');
    const text = $('conn-text');
    if (!dot || !text) return;
    const map = {
      ok: ['bg-green-500', t('conn_connected')],
      connecting: ['bg-yellow-400 animate-pulse', t('conn_connecting')],
      down: ['bg-red-500', t('conn_disconnected')],
      missing: ['bg-red-500', t('conn_unavailable')],
    };
    const [cls, label] = map[state] || map.down;
    dot.className = 'w-2 h-2 rounded-full ' + cls;
    text.textContent = label;
  }

  function bindConnection() {
    const conn = window.Echo?.connector?.pusher?.connection;
    if (!conn) { setConn('down'); return; }
    const apply = (s) => {
      if (s === 'connected') setConn('ok');
      else if (s === 'connecting' || s === 'initialized') setConn('connecting');
      else setConn('down');
    };
    apply(conn.state);
    conn.bind('state_change', (st) => apply(st.current));
  }

  function subscribeChannel() {
    const channel = window.Echo.channel(`stream.${STREAM_KEY}`);
    channel.listen('.signal', (data) => handleSignal(data));
    channel.listen('.like', (data) => {
      const n = Number(data.likes_count ?? data.likes ?? 0);
      if ($('likes-count')) $('likes-count').textContent = n;
      if ($('stat-likes')) $('stat-likes').textContent = n.toLocaleString();
    });
    channel.listen('.comment', (data) => {
      appendComment(data);
      const el = $('stat-comments');
      if (el) el.textContent = (parseInt(el.textContent.replace(/,/g, ''), 10) || 0) + 1;
    });
    bindConnection();
  }

  // ─── Signalling transport (ordered) ───────────────────────────────────────
  let sendChain = Promise.resolve();
  function sendSignal(target, type, payload) {
    sendChain = sendChain.then(() => api(`/live-streams/${STREAM_ID}/signal`, { type, payload: payload || {}, target }))
      .catch((e) => console.warn('[studio] signal failed', type, e.message));
    return sendChain;
  }

  async function api(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body || {}),
    });
    let json = null;
    try { json = await res.json(); } catch (_) { /* non-JSON */ }
    if (!res.ok || (json && json.success === false)) {
      throw new Error((json && json.message) || `${t('err_request')} (${res.status})`);
    }
    return json || {};
  }

  // ─── ICE servers + diagnostics ────────────────────────────────────────────
  function buildIceServers() {
    const servers = [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }];
    const turn = META('turn-url');
    const turns = META('turns-url');
    const user = META('turn-user');
    const cred = META('turn-cred');
    const urls = [turn, turns].filter(Boolean);
    if (urls.length && user && cred) servers.push({ urls, username: user, credential: cred });
    return { servers, turnUrls: urls };
  }
  const ICE = buildIceServers();

  function runIceDiagnostics() {
    const el = $('ice-diag');
    if (!el || typeof RTCPeerConnection === 'undefined') return;
    if (!ICE.turnUrls.length) { el.textContent = t('diag_stun_only'); return; }
    const base = t('diag_turn', { urls: ICE.turnUrls.join(', ') });
    el.textContent = base;
    let relay = false;
    const pc = new RTCPeerConnection({ iceServers: ICE.servers });
    pc.createDataChannel('probe');
    pc.onicecandidate = (e) => {
      if (e.candidate && / typ relay /.test(e.candidate.candidate)) relay = true;
      if (!e.candidate) finish();
    };
    const finish = () => {
      el.textContent = base + ' - ' + (relay ? t('diag_relay') : t('diag_no_relay'));
      try { pc.close(); } catch (_) { /* noop */ }
    };
    pc.createOffer().then((o) => pc.setLocalDescription(o)).catch(() => finish());
    setTimeout(() => { if (pc.signalingState !== 'closed') finish(); }, 6000);
  }

  // ─── Devices ──────────────────────────────────────────────────────────────
  async function refreshDevices() {
    if (!navigator.mediaDevices?.enumerateDevices) return;
    let list = [];
    try { list = await navigator.mediaDevices.enumerateDevices(); } catch (_) { return; }
    fill($('sel-camera'), list.filter((d) => d.kind === 'videoinput'), 'Camera');
    fill($('sel-mic'), list.filter((d) => d.kind === 'audioinput'), 'Microphone');
  }

  function fill(select, devices, fallbackLabel) {
    if (!select) return;
    const prev = select.value;
    select.innerHTML = '';
    const def = document.createElement('option');
    def.value = '';
    def.textContent = devices.length ? t('default_device') : t('no_devices');
    select.appendChild(def);
    devices.forEach((d, i) => {
      if (!d.deviceId || d.deviceId === 'default') return;
      const o = document.createElement('option');
      o.value = d.deviceId;
      o.textContent = d.label || `${fallbackLabel} ${i + 1}`;
      select.appendChild(o);
    });
    if ([...select.options].some((o) => o.value === prev)) select.value = prev;
  }

  // ─── Capture ──────────────────────────────────────────────────────────────
  function stopCapture() {
    rawStreams.forEach((s) => s.getTracks().forEach((tr) => tr.stop()));
    rawStreams = [];
    if (mixCtx) { mixCtx.close().catch(() => {}); mixCtx = null; }
    audioTrack = null;
    videoTrack = null;
  }

  const deviceConstraint = (id) => (id ? { deviceId: { exact: id } } : true);

  async function acquire(selMode) {
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      const e = new Error('insecure'); e.name = 'SecurityError'; throw e;
    }
    const camId = $('sel-camera')?.value;
    const micId = $('sel-mic')?.value;
    const out = { audio: null, video: null, streams: [], ctx: null };

    if (selMode === 'screen') {
      if (!navigator.mediaDevices.getDisplayMedia) throw new Error(t('err_unsupported'));
      let screen;
      try {
        screen = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: true });
      } catch (e) {
        if (e.name === 'NotAllowedError') throw Object.assign(new Error(t('err_screen')), { name: 'ScreenCancelled' });
        throw e;
      }
      out.streams.push(screen);
      out.video = screen.getVideoTracks()[0] || null;
      let mic = null;
      try {
        mic = await navigator.mediaDevices.getUserMedia({ audio: deviceConstraint(micId) });
        out.streams.push(mic);
      } catch (_) { /* mic optional for screen share */ }
      const sources = [screen, mic].filter((s) => s && s.getAudioTracks().length);
      if (sources.length > 1) {
        out.ctx = new (window.AudioContext || window.webkitAudioContext)();
        const dest = out.ctx.createMediaStreamDestination();
        sources.forEach((s) => out.ctx.createMediaStreamSource(s).connect(dest));
        out.audio = dest.stream.getAudioTracks()[0];
      } else if (sources.length === 1) {
        out.audio = sources[0].getAudioTracks()[0];
      }
      return out;
    }

    const wantVideo = selMode === 'both' || selMode === 'video';
    const wantAudio = selMode === 'both' || selMode === 'audio';
    const stream = await navigator.mediaDevices.getUserMedia({
      video: wantVideo ? deviceConstraint(camId) : false,
      audio: wantAudio ? (micId ? { deviceId: { exact: micId }, echoCancellation: true, noiseSuppression: true } : { echoCancellation: true, noiseSuppression: true }) : false,
    });
    out.streams.push(stream);
    out.video = stream.getVideoTracks()[0] || null;
    out.audio = stream.getAudioTracks()[0] || null;
    return out;
  }

  async function startPreview() {
    if (acquiring) return;
    acquiring = true;
    showError('');
    $('btn-start-camera').disabled = true;
    try {
      const sel = $('sel-mode').value;
      const got = await acquire(sel);
      stopCapture();                     // release old devices only after new ones succeeded
      mode = sel;
      rawStreams = got.streams;
      mixCtx = got.ctx;
      audioTrack = got.audio;
      videoTrack = got.video;
      if (audioTrack) audioTrack.enabled = !micMuted;
      if (videoTrack && mode !== 'screen') videoTrack.enabled = !camOff;
      if (videoTrack) videoTrack.addEventListener('ended', () => { if (mode === 'screen') stopPreview(); });
      previewActive = true;
      renderPreview();
      await refreshDevices();            // labels are available after permission
      syncAllPeers();
      publishMediaMode();
    } catch (err) {
      showError(err.name === 'ScreenCancelled' ? err.message : mediaErrorMessage(err));
      console.warn('[studio] capture failed', err);
    } finally {
      acquiring = false;
      $('btn-start-camera').disabled = false;
    }
  }

  function stopPreview() {
    stopCapture();
    previewActive = false;
    renderPreview();
    syncAllPeers();
  }

  function currentMediaMode() {
    if (videoTrack && audioTrack) return 'both';
    if (videoTrack) return 'video';
    if (audioTrack) return 'audio';
    return null;
  }

  function publishMediaMode() {
    const m = currentMediaMode();
    if (!m) return;
    api(`/live-streams/${STREAM_ID}/media`, { mode: m }).catch((e) => showError(e.message));
  }

  // ─── Preview rendering ────────────────────────────────────────────────────
  let meterRaf = 0;
  let meterCtx = null;

  function renderPreview() {
    const video = $('local-video');
    const hasVideo = previewActive && !!videoTrack;
    const audioOnly = previewActive && !videoTrack && !!audioTrack;
    video.srcObject = hasVideo ? new MediaStream([videoTrack]) : null;
    show($('no-preview'), !previewActive);
    show($('audio-meter'), audioOnly);
    show($('audio-only-badge'), audioOnly);
    show($('btn-stop'), previewActive);
    show($('btn-mute'), previewActive && !!audioTrack);
    show($('btn-camera-toggle'), previewActive && !!videoTrack && mode !== 'screen');
    $('btn-start-camera').textContent = t(previewActive ? 'apply_mode' : 'start_preview');
    updateToggleLabels();
    stopMeter();
    if (previewActive && audioTrack && audioOnly) startMeter();
  }

  function updateToggleLabels() {
    $('btn-mute').textContent = t(micMuted ? 'unmute' : 'mute');
    $('btn-camera-toggle').textContent = t(camOff ? 'camera_on' : 'camera_off');
  }

  function startMeter() {
    const canvas = $('audio-meter');
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!canvas || !AC) return;
    meterCtx = new AC();
    const analyser = meterCtx.createAnalyser();
    analyser.fftSize = 256;
    meterCtx.createMediaStreamSource(new MediaStream([audioTrack])).connect(analyser);
    const data = new Uint8Array(analyser.frequencyBinCount);
    const g = canvas.getContext('2d');
    const draw = () => {
      meterRaf = requestAnimationFrame(draw);
      analyser.getByteFrequencyData(data);
      g.fillStyle = '#111827';
      g.fillRect(0, 0, canvas.width, canvas.height);
      const bars = 48;
      const w = canvas.width / bars;
      for (let i = 0; i < bars; i++) {
        const v = micMuted ? 0 : data[Math.floor((i / bars) * data.length * 0.6)] / 255;
        const h = Math.max(4, v * canvas.height * 0.7);
        g.fillStyle = v > 0.75 ? '#ef4444' : '#22c55e';
        g.fillRect(i * w + 2, (canvas.height - h) / 2, w - 4, h);
      }
    };
    draw();
  }

  function stopMeter() {
    cancelAnimationFrame(meterRaf);
    if (meterCtx) { meterCtx.close().catch(() => {}); meterCtx = null; }
  }

  // ─── Peer connections ─────────────────────────────────────────────────────
  function createPeer(peerId) {
    closePeer(peerId, false);
    const pc = new RTCPeerConnection({ iceServers: ICE.servers });
    const peer = { pc, pendingIce: [], makingOffer: false, senders: { audio: null, video: null }, stream: new MediaStream(), disconnectTimer: null };
    peers.set(peerId, peer);

    pc.onicecandidate = ({ candidate }) => {
      if (candidate) sendSignal(peerId, 'ice-candidate', candidate.toJSON());
    };

    pc.onnegotiationneeded = async () => {
      if (peer.makingOffer || pc.signalingState !== 'stable') return;
      try {
        peer.makingOffer = true;
        await pc.setLocalDescription(await pc.createOffer());
        await sendSignal(peerId, 'offer', { type: pc.localDescription.type, sdp: pc.localDescription.sdp });
      } catch (e) {
        console.warn('[studio] negotiation failed', e);
      } finally {
        peer.makingOffer = false;
      }
    };

    pc.onconnectionstatechange = () => {
      clearTimeout(peer.disconnectTimer);
      const st = pc.connectionState;
      if (st === 'failed' || st === 'closed') closePeer(peerId, true);
      else if (st === 'disconnected') peer.disconnectTimer = setTimeout(() => {
        if (pc.connectionState === 'disconnected') closePeer(peerId, true);
      }, 8000);
      updateViewerCount();
    };

    syncPeer(peer);
    updateViewerCount();
    return peer;
  }

  function closePeer(peerId, count) {
    const peer = peers.get(peerId);
    if (!peer) return;
    clearTimeout(peer.disconnectTimer);
    peers.delete(peerId);
    peer.pc.onicecandidate = peer.pc.onnegotiationneeded = peer.pc.onconnectionstatechange = null;
    try { peer.pc.close(); } catch (_) { /* noop */ }
    if (count) updateViewerCount();
  }

  function closeAllPeers() {
    [...peers.keys()].forEach((id) => closePeer(id, false));
    updateViewerCount();
  }

  /**
   * Make a peer's senders reflect the current tracks. replaceTrack where possible;
   * if it fails (or the sender is dead) drop the sender and add a fresh one (renegotiates).
   * All tracks share ONE MediaStream so the viewer receives audio+video in a single stream.
   */
  function syncPeer(peer) {
    [['audio', audioTrack], ['video', videoTrack]].forEach(([kind, track]) => {
      const sender = peer.senders[kind];
      if (sender) {
        if (sender.track === track) return;
        sender.replaceTrack(track || null).catch((e) => {
          console.warn('[studio] replaceTrack failed, renegotiating', kind, e);
          try { peer.pc.removeTrack(sender); } catch (_) { /* noop */ }
          peer.senders[kind] = null;
          if (track) peer.senders[kind] = peer.pc.addTrack(track, peer.stream);
        });
      } else if (track) {
        peer.senders[kind] = peer.pc.addTrack(track, peer.stream);   // fires negotiationneeded
      }
    });
  }

  function syncAllPeers() {
    peers.forEach((peer) => syncPeer(peer));
  }

  function updateViewerCount() {
    let n = 0;
    peers.forEach((p) => { if (p.pc.connectionState === 'connected') n++; });
    if ($('viewer-num')) $('viewer-num').textContent = n;
    show($('viewer-count'), isLive);
  }

  /** SDP must end with CRLF; proxies/middleware may trim it. */
  function fixSdp(d) {
    if (!d || typeof d.sdp !== 'string') return d;
    const sdp = d.sdp.replace(/\r?\n/g, '\r\n').replace(/(\r\n)*$/, '\r\n');
    return { type: d.type, sdp };
  }

  // ─── Incoming signals ─────────────────────────────────────────────────────
  async function handleSignal(msg) {
    if (!msg || msg.target !== 'admin' || !msg.from || msg.from === 'admin') return;
    if (!isLive) return;
    const from = msg.from;
    const payload = msg.payload || {};
    try {
      switch (msg.type) {
        case 'hello': {
          createPeer(from);
          sendSignal(from, 'media-state', mediaStatePayload());
          break;
        }
        case 'offer': {
          const peer = peers.get(from) || createPeer(from);
          const pc = peer.pc;
          // Admin is the polite side: an incoming offer wins (implicit rollback).
          await pc.setRemoteDescription(fixSdp(payload));
          await flushIce(peer);
          await pc.setLocalDescription(await pc.createAnswer());
          await sendSignal(from, 'answer', { type: pc.localDescription.type, sdp: pc.localDescription.sdp });
          break;
        }
        case 'answer': {
          const peer = peers.get(from);
          if (!peer || peer.pc.signalingState !== 'have-local-offer') return;
          await peer.pc.setRemoteDescription(fixSdp(payload));
          await flushIce(peer);
          break;
        }
        case 'ice-candidate': {
          const peer = peers.get(from);
          if (!peer) return;
          if (peer.pc.remoteDescription) await peer.pc.addIceCandidate(payload).catch(() => {});
          else peer.pendingIce.push(payload);
          break;
        }
        case 'bye':
          closePeer(from, true);
          break;
        default:
      }
    } catch (e) {
      console.warn('[studio] signal handling failed', msg.type, e);
    }
  }

  async function flushIce(peer) {
    const queued = peer.pendingIce.splice(0);
    for (const c of queued) await peer.pc.addIceCandidate(c).catch(() => {});
  }

  function mediaStatePayload() {
    const m = currentMediaMode();
    return { mode: m, audio: !!audioTrack, video: !!videoTrack };
  }

  // ─── Go live / End ────────────────────────────────────────────────────────
  async function goLive() {
    const btn = $('btn-go-live');
    btn.disabled = true;
    const label = btn.querySelector('.label');
    const old = label.textContent;
    label.textContent = t('starting');
    try {
      await api(`/live-streams/${STREAM_ID}/go-live`);
      isLive = true;
      renderLiveState();
      publishMediaMode();
    } catch (e) {
      showError(e.message);
      label.textContent = old;
      btn.disabled = false;
    }
  }

  async function endStream() {
    if (!confirm(t('confirm_end'))) return;
    const btn = $('btn-end-stream');
    btn.disabled = true;
    try {
      await api(`/live-streams/${STREAM_ID}/end`);
    } catch (e) {
      showError(e.message);
      btn.disabled = false;
      return;
    }
    isLive = false;
    isEnded = true;
    closeAllPeers();
    stopPreview();
    renderLiveState();
  }

  function renderLiveState() {
    show($('btn-go-live'), !isLive && !isEnded);
    show($('btn-end-stream'), isLive);
    show($('live-indicator'), isLive);
    show($('viewer-count'), isLive);
    const badge = $('status-badge');
    if (badge) {
      const st = isLive ? 'live' : isEnded ? 'ended' : 'scheduled';
      const colors = { live: 'bg-green-100 text-green-700', scheduled: 'bg-yellow-100 text-yellow-700', ended: 'bg-gray-100 text-gray-600' };
      badge.className = `inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold ${colors[st]}`;
      badge.textContent = t('state_' + st);
    }
    updateViewerCount();
  }

  // ─── Comments ─────────────────────────────────────────────────────────────
  function appendComment(data) {
    const list = $('comments-list');
    if (!list) return;
    const author = String(data.author || 'G');
    const div = document.createElement('div');
    div.className = 'comment-item flex gap-2';
    div.dataset.id = data.id;
    const avatar = document.createElement('div');
    avatar.className = 'w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-600 shrink-0';
    avatar.textContent = author.charAt(0).toUpperCase();
    const body = document.createElement('div');
    body.className = 'flex-1 min-w-0';
    const name = document.createElement('span');
    name.className = 'text-xs font-semibold text-gray-700';
    name.textContent = author;
    const text = document.createElement('p');
    text.className = 'text-xs text-gray-600 mt-0.5 leading-snug';
    text.textContent = data.body || '';
    body.append(name, text);
    const del = document.createElement('button');
    del.className = 'delete-comment text-gray-300 hover:text-red-500 text-xs shrink-0';
    del.dataset.id = data.id;
    del.textContent = '✕';
    div.append(avatar, body, del);
    list.appendChild(div);
    list.scrollTop = list.scrollHeight;
    const cnt = $('comment-count');
    if (cnt) cnt.textContent = list.querySelectorAll('.comment-item').length;
  }

  $('comments-list')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('.delete-comment');
    if (!btn) return;
    await fetch(`/live-streams/${STREAM_ID}/comments/${btn.dataset.id}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
    });
    btn.closest('.comment-item').remove();
  });

  // ─── Wiring ───────────────────────────────────────────────────────────────
  function wire() {
    $('btn-start-camera').addEventListener('click', startPreview);
    $('btn-stop').addEventListener('click', stopPreview);
    $('btn-mute').addEventListener('click', () => {
      micMuted = !micMuted;
      if (audioTrack) audioTrack.enabled = !micMuted;
      updateToggleLabels();
    });
    $('btn-camera-toggle').addEventListener('click', () => {
      camOff = !camOff;
      if (videoTrack) videoTrack.enabled = !camOff;
      updateToggleLabels();
    });
    ['sel-mode', 'sel-camera', 'sel-mic'].forEach((id) => {
      $(id).addEventListener('change', () => { if (previewActive) startPreview(); });
    });
    $('btn-go-live')?.addEventListener('click', goLive);
    $('btn-end-stream')?.addEventListener('click', endStream);
    navigator.mediaDevices?.addEventListener?.('devicechange', refreshDevices);
  }

  function init() {
    wire();
    renderLiveState();
    refreshDevices();
    runIceDiagnostics();
    if (!window.isSecureContext) showError(t('err_insecure'));

    // The Vite bundle creates window.Echo; give it a moment, then fail loudly.
    let tries = 0;
    const wait = setInterval(() => {
      if (window.Echo) {
        clearInterval(wait);
        subscribeChannel();
        // Admin page reloaded while live: re-announce so viewers re-hello.
        if (isLive) api(`/live-streams/${STREAM_ID}/go-live`).catch((e) => showError(e.message));
      } else if (++tries > 20) {
        clearInterval(wait);
        setConn('missing');
        show($('echo-missing-banner'), true);
      }
    }, 150);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
