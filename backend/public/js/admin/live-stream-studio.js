(function () {
  'use strict';

  const META = (name) => document.querySelector(`meta[name="${name}"]`)?.content;
  const STREAM_KEY = META('stream-key');
  const STREAM_ID  = META('stream-id');
  const CSRF       = META('csrf-token');

  let localStream     = null;
  let screenStream    = null;
  let peerConnections = {};   // peerId → RTCPeerConnection
  let isLive          = META('stream-status') === 'live';
  let audioMuted      = false;
  let channel         = null;

  const $          = (id) => document.getElementById(id);
  const localVideo = $('local-video');
  const noPreview  = $('no-preview');

  // ─── Reverb channel ────────────────────────────────────────────────────────

  function subscribeChannel() {
    if (!window.Echo) return;
    channel = window.Echo.channel(`stream.${STREAM_KEY}`);

    channel.listen('.signal', (data) => {
      if (data.target && data.target !== 'admin') return;
      handleIncomingSignal(data.from, data.type, data.payload);
    });

    channel.listen('.like', (data) => {
      $('likes-count').textContent = data.likes_count;
      $('stat-likes').textContent  = data.likes_count.toLocaleString();
    });

    channel.listen('.comment', (data) => {
      appendComment(data);
      const c = parseInt($('stat-comments').textContent || '0', 10);
      $('stat-comments').textContent = c + 1;
    });
  }

  // ─── Camera / screen capture ───────────────────────────────────────────────

  $('btn-start-camera').addEventListener('click', async () => {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      localVideo.srcObject = localStream;
      noPreview.classList.add('hidden');
      $('btn-mute').classList.remove('hidden');
      $('btn-stop').classList.remove('hidden');
      addStreamsToAllPeers();
    } catch (err) {
      alert('Camera access denied: ' + err.message);
    }
  });

  $('btn-share-screen').addEventListener('click', async () => {
    try {
      screenStream = await navigator.mediaDevices.getDisplayMedia({
        video: { cursor: 'always' },
        audio: true,
      });
      const micStream = localStream || await navigator.mediaDevices.getUserMedia({ audio: true });
      const ctx  = new AudioContext();
      const dest = ctx.createMediaStreamDestination();
      if (micStream.getAudioTracks().length)   ctx.createMediaStreamSource(micStream).connect(dest);
      if (screenStream.getAudioTracks().length) ctx.createMediaStreamSource(screenStream).connect(dest);

      const combined = new MediaStream([
        ...screenStream.getVideoTracks(),
        ...dest.stream.getAudioTracks(),
      ]);
      localStream = combined;
      localVideo.srcObject = combined;
      noPreview.classList.add('hidden');
      $('btn-stop').classList.remove('hidden');
      $('btn-mute').classList.remove('hidden');
      addStreamsToAllPeers();

      screenStream.getVideoTracks()[0].addEventListener('ended', stopStream);
    } catch (err) {
      if (err.name !== 'NotAllowedError') alert('Screen share failed: ' + err.message);
    }
  });

  $('btn-mute').addEventListener('click', () => {
    audioMuted = !audioMuted;
    localStream?.getAudioTracks().forEach(t => t.enabled = !audioMuted);
    $('btn-mute').textContent = audioMuted ? '🔊 Unmute' : '🔇 Mute Audio';
  });

  $('btn-stop').addEventListener('click', stopStream);

  function stopStream() {
    localStream?.getTracks().forEach(t => t.stop());
    screenStream?.getTracks().forEach(t => t.stop());
    localStream = null;
    localVideo.srcObject = null;
    noPreview.classList.remove('hidden');
    $('btn-stop').classList.add('hidden');
    $('btn-mute').classList.add('hidden');
  }

  function addStreamsToAllPeers() {
    if (!localStream) return;
    Object.values(peerConnections).forEach(pc => {
      localStream.getTracks().forEach(track => {
        const sender = pc.getSenders().find(s => s.track?.kind === track.kind);
        if (sender) sender.replaceTrack(track);
        else        pc.addTrack(track, localStream);
      });
    });
  }

  // ─── Go Live ──────────────────────────────────────────────────────────────

  const btnGoLive = $('btn-go-live');
  if (btnGoLive) {
    btnGoLive.addEventListener('click', async () => {
      btnGoLive.disabled = true;
      btnGoLive.textContent = 'Starting…';
      const res = await fetch(`/admin/live-streams/${STREAM_ID}/go-live`, {
        method:  'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      }).then(r => r.json());

      if (res.success) {
        isLive = true;
        btnGoLive.replaceWith(createEndButton());
        $('live-indicator')?.classList.remove('hidden');
        $('viewer-count')?.classList.remove('hidden');
        subscribeChannel();
        updateStatusBadge('live');
      } else {
        alert(res.message);
        btnGoLive.disabled = false;
        btnGoLive.textContent = 'Go Live Now';
      }
    });
  }

  function createEndButton() {
    const btn = document.createElement('button');
    btn.id        = 'btn-end-stream';
    btn.className = 'px-5 py-2 bg-gray-700 text-white rounded-lg text-sm font-medium hover:bg-gray-800';
    btn.textContent = 'End Stream';
    btn.addEventListener('click', endStream);
    return btn;
  }

  const btnEnd = $('btn-end-stream');
  if (btnEnd) btnEnd.addEventListener('click', endStream);

  async function endStream() {
    if (!confirm('End this live stream?')) return;
    await fetch(`/admin/live-streams/${STREAM_ID}/end`, {
      method:  'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    isLive = false;
    stopStream();
    Object.values(peerConnections).forEach(pc => pc.close());
    peerConnections = {};
    updateStatusBadge('ended');
    $('live-indicator')?.classList.add('hidden');
    $('btn-end-stream')?.remove();
  }

  // ─── WebRTC ───────────────────────────────────────────────────────────────

  // TURN config is injected into the page via a <meta> tag from show.blade.php
  // See: <meta name="turn-url"> <meta name="turn-user"> <meta name="turn-cred">
  const buildIceServers = () => {
    const servers = [
      { urls: 'stun:stun.l.google.com:19302' },
      { urls: 'stun:stun1.l.google.com:19302' },
    ];
    const turnUrl  = META('turn-url');
    const turnUser = META('turn-user');
    const turnCred = META('turn-cred');
    if (turnUrl && turnUser && turnCred) {
      servers.push({
        urls:       [turnUrl, turnUrl.replace('turn:', 'turns:').replace(':3478', ':5349')],
        username:   turnUser,
        credential: turnCred,
      });
    }
    return servers;
  };
  const ICE_SERVERS = buildIceServers();

  function createPeerConnection(peerId) {
    if (peerConnections[peerId]) return peerConnections[peerId];
    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    peerConnections[peerId] = pc;

    localStream?.getTracks().forEach(t => pc.addTrack(t, localStream));

    pc.onicecandidate = ({ candidate }) => {
      if (candidate) sendSignal(peerId, 'ice-candidate', candidate.toJSON());
    };

    pc.onconnectionstatechange = () => {
      if (['disconnected', 'failed', 'closed'].includes(pc.connectionState)) {
        pc.close();
        delete peerConnections[peerId];
        updateViewerCount();
      }
    };

    updateViewerCount();
    return pc;
  }

  async function handleIncomingSignal(fromPeerId, type, payload) {
    if (type === 'offer') {
      const pc = createPeerConnection(fromPeerId);
      await pc.setRemoteDescription(new RTCSessionDescription(payload));
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      sendSignal(fromPeerId, 'answer', answer);
    } else if (type === 'ice-candidate') {
      const pc = peerConnections[fromPeerId];
      if (pc) await pc.addIceCandidate(new RTCIceCandidate(payload)).catch(() => {});
    }
  }

  function sendSignal(targetPeerId, type, payload) {
    fetch(`/admin/live-streams/${STREAM_ID}/signal`, {
      method:  'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify({ type, payload, to: targetPeerId }),
    });
  }

  function updateViewerCount() {
    const count = Object.keys(peerConnections).length;
    const el = $('viewer-num');
    if (el) el.textContent = count;
    $('viewer-count')?.classList.toggle('hidden', !isLive);
  }

  function updateStatusBadge(status) {
    const badge = $('status-badge');
    if (!badge) return;
    const labels = { live: '🔴 Live', scheduled: 'Scheduled', ended: 'Ended' };
    const colors  = {
      live:      'bg-green-100 text-green-700',
      scheduled: 'bg-yellow-100 text-yellow-700',
      ended:     'bg-gray-100 text-gray-600',
    };
    badge.className  = `inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold ${colors[status]}`;
    badge.textContent = labels[status];
  }

  // ─── Comments ─────────────────────────────────────────────────────────────

  function appendComment(data) {
    const list = $('comments-list');
    if (!list) return;
    const div = document.createElement('div');
    div.className  = 'comment-item flex gap-2';
    div.dataset.id = data.id;
    div.innerHTML  = `
      <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-600 shrink-0">
        ${(data.author || 'G').charAt(0).toUpperCase()}
      </div>
      <div class="flex-1 min-w-0">
        <span class="text-xs font-semibold text-gray-700">${data.author}</span>
        <p class="text-xs text-gray-600 mt-0.5 leading-snug">${data.body}</p>
      </div>
      <button class="delete-comment text-gray-300 hover:text-red-500 text-xs shrink-0" data-id="${data.id}">✕</button>
    `;
    list.appendChild(div);
    list.scrollTop = list.scrollHeight;
    const cnt = $('comment-count');
    if (cnt) cnt.textContent = list.querySelectorAll('.comment-item').length;
  }

  $('comments-list')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('.delete-comment');
    if (!btn) return;
    await fetch(`/admin/live-streams/${STREAM_ID}/comments/${btn.dataset.id}`, {
      method:  'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF },
    });
    btn.closest('.comment-item').remove();
  });

  // ─── Init ─────────────────────────────────────────────────────────────────

  if (isLive) {
    subscribeChannel();
    $('viewer-count')?.classList.remove('hidden');
  }

})();
