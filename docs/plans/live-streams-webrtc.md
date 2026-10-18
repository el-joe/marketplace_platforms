# Live Streams (WebRTC) — Audit, Plan and Fix

Date: 2026-10-18. Scope: admin "Live Streams" studio, public API, Next.js `/live-streams` pages, VPS (Ubuntu + Apache2 + PHP + MySQL + Node).

## 1. Audit findings

### Admin discoverability
- "Live Streams" (`admin.live-streams.index`, permission `pages.view`) sits in the **Marketing** nav group (NavigationService ~L402) while "Radio Channels" sits in the content/CMS group (~L781). Users looking next to Radio never see it.
- There is no hint on the Radio page or dashboard that live streaming exists.

### WebRTC defects (admin studio `public/js/admin/live-stream-studio.js`, viewer `StreamDetailFeature.tsx`)
1. **No renegotiation.** The viewer sends the only offer. If the admin starts camera/mic *after* a viewer joined (or before "Go live"), the tracks are added with `addTrack` but nobody re-offers → viewers see/hear nothing. Admin never handles `answer`, never creates offers.
2. **ICE candidates lost.** Candidates that arrive before `setRemoteDescription` are dropped (errors swallowed) on both sides.
3. **Signal leakage / cross-talk.** Signals go on one public channel; viewers accept messages with `target == null` (other viewers' ICE) and admin does not filter by `from`.
4. **No audio-only / video-only / both choice.** Studio hard-codes `getUserMedia({video:true,audio:true})`; fails entirely when no camera exists (audio-only radio-style streams impossible); no device picker; mute exists, camera-off does not; viewers have no audio-only UI.
5. **No end / restart handling.** Ending the stream or reloading the admin page leaves viewers frozen (no `ended` event, no reconnect, no `failed` handling).
6. **Admin Echo is re-created from CDN** (`LaravelEcho` global, hard-coded port 443/TLS, `wsHost` from `options.host`) although the Vite bundle already creates `window.Echo` (`resources/js/shared/echo-setup.js`) — breaks on dev/http and may not even load.
7. **No throttling** on the public `POST /streams/{id}/signal` (ICE spam = one HTTP request per candidate); `peer_id` not validated beyond length; unbounded SDP payload size.
8. **TURN**: `turns:` URL derived by string replace; credentials are static (acceptable, documented); no time-limited credential option.
9. Media state is not persisted: late joiners cannot know whether the stream is audio-only.

### Infrastructure gaps (docs)
- `docs/VPS_DEPLOYMENT.md` and `stream_instructions.txt` are nginx-oriented; the real server is **Apache2**. Missing: Apache modules, `ws.` vhost proxying `wss` to Reverb, Permissions-Policy header, Reverb as a service, firewall/TURN, HTTPS requirement for `getUserMedia`, verification steps.

## 2. Target design

Topology: admin browser = single broadcaster; viewers connect P2P (mesh, one `RTCPeerConnection` per viewer, fine up to ~30–50 concurrent viewers; beyond that an SFU such as LiveKit is recommended — documented, out of scope). Signalling over Reverb channel `stream.{stream_key}` + REST endpoints.

### Signalling protocol (v2)
Message on event `.signal`: `{ type, payload, target, from }`.
- `type` ∈ `offer | answer | ice-candidate | hello | bye | media-state | ended`.
- Peer ids: admin = `admin`; viewers = `viewer-xxxx`.
- Viewer → admin: `hello` (join), `offer`, `answer` (to an admin offer), `ice-candidate`, `bye`. `target = 'admin'` always set.
- Admin → viewer: `offer` (on negotiationneeded), `answer` (to viewer offer), `ice-candidate`, `media-state`, `ended`. `target = <viewer id>`, except `media-state`/`ended`/`admin-ready` (`type: hello`, `from: admin`, target null = everyone).
- Receivers ignore everything not addressed to them (`target === me`, or broadcast types from `admin`). Admin ignores anything with `target !== 'admin'`.
- ICE candidates are queued until the remote description is set.
- Admin creates the peer connection when it receives `hello`/`offer` from a viewer and adds its current tracks; adding/removing tracks later triggers `onnegotiationneeded` → admin offer → viewer answer (perfect-negotiation-lite: admin is the polite offerer; viewer only answers once connected).
- Viewer auto-reconnect: on `failed`/`disconnected` > 5 s or on admin `hello` (admin page reload) → close, new pc, re-`hello`+offer with backoff (1,2,4,8 s, max 5 tries); a manual "Reconnect" button after that.
- `ended`: viewer shows "stream ended"; also a 15 s status poll as a fallback.

### Media modes
- Admin chooses **Camera + mic**, **Camera only**, **Microphone only (audio)**, **Screen share** (+mic mix) in the studio; device pickers for camera/mic (enumerateDevices), live preview, audio level meter, mute/unmute, camera on/off without renegotiation (`track.enabled`), switch mode while live (replaceTrack / add/remove with renegotiation).
- Mode persisted: new column `live_streams.media_mode` (`audio|video|both`, nullable) updated via admin endpoint `POST /live-streams/{id}/media` and exposed in the public `GET /streams/{id}` (`media_mode`) and in `media-state` broadcasts.
- Viewer UI: video element for video/both; audio-only → poster/waveform card with the stream thumbnail and an "Audio only" badge, plus an explicit **Tap to listen** button (autoplay policy) and mute/volume control.

### Backend
- `signal` endpoints: validated types (+`hello`,`bye`), payload size cap, `throttle:240,1` per IP, `target` honoured, viewer endpoint requires `peer_id` pattern `^[A-Za-z0-9\-]{6,40}$`.
- `endStream` broadcasts `ended`; `goLive` broadcasts `hello` (admin-ready).
- Admin show page uses the bundled `window.Echo`; Reverb host/port/scheme come from config (works on https/443 and dev http/8080). TURN meta tags keep working; add `turns:` explicit config key (`TURN_TLS_URL`).
- New migration (additive only): `add_media_mode_to_live_streams`.
- Navigation: move "Live Streams" next to "Radio Channels" (same group, labelled with icons), keep permission `pages.view`; add a short link on the Radio index page.
- Tests: feature tests for signal validation/throttle/target, media endpoint, public detail `media_mode`, nav contains both items.

### Frontend (Next.js)
- Rewrite viewer WebRTC hook (`useLiveStreamViewer`) implementing protocol v2 (queued ICE, answer-to-admin-offer, reconnect, ended, media-state), audio-only UI, listen button, connection-state badge ("Connecting / Live / Reconnecting"), stream-ended screen, status polling fallback; `initEcho()` guaranteed before subscribing; env validation with a visible error when `NEXT_PUBLIC_REVERB_*` is missing; i18n keys EN/AR; RTL friendly.
- Streams list shows an "Audio" / "Video" badge.

### VPS (Apache2) guide — new `docs/LIVE_STREAMS_VPS_APACHE.md`
Packages, Apache modules (`proxy proxy_http proxy_wstunnel rewrite ssl headers http2`), vhosts for `api.`/`admin.`/frontend/`ws.` (wss → Reverb :8080), `Permissions-Policy: camera=(self), microphone=(self)` and no blocking CSP, certbot, Reverb as a systemd service (not PM2 required), supervisor/queue worker, coturn (UDP 3478/5349 + relay range), UFW, `.env` values for backend/frontend, `php artisan reverb:*` checks, Next.js PM2 + Apache reverse proxy, verification checklist (wscat, trickle-ICE, two-browser test) and troubleshooting table.

## 3. Decisions (defaults applied)
- Keep P2P mesh + Reverb signalling (no new paid service); document LiveKit/SFU as the scale-up path.
- Admin is the only publisher; one stream live at a time per admin session.
- Streams are public to all site visitors (no login) as today.
- Existing `stream_key` public channel kept (no private channel) — keys are random 32-char and only exposed while live.
- Never remove/rename migrations; only add new ones.

## 4. Execution
Three parallel work packages with disjoint files: **A** backend + admin studio, **B** Next.js viewer, **C** VPS doc. Tests for A run locally; B is verified with `tsc --noEmit`/lint where available; WebRTC itself can only be fully verified in two real browsers over HTTPS (checklist in the VPS doc).
