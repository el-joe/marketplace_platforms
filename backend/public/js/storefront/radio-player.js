/**
 * Alpine.js component for the storefront radio player.
 * Loaded on the player page via @push('scripts').
 * Uses HLS.js for .m3u8 streams; native <audio> for direct audio files.
 */
function radioPlayer(channelId, streamUrl, type, channelName, isLive) {
    return {
        channelId,
        streamUrl,
        type,
        nowPlaying: channelName,
        isLive,

        playing:   false,
        volume:    1,
        hls:       null,
        sessionId: null,
        heartbeatTimer: null,

        init() {
            this.sessionId = crypto.randomUUID();
            this._initMedia();
        },

        _mediaEl() {
            return this.type === 'video'
                ? this.$refs.videoEl
                : this.$refs.audioEl;
        },

        _initMedia() {
            if (!this.streamUrl) return;

            const el = this._mediaEl();
            const isHls = this.streamUrl.includes('.m3u8');

            if (isHls && window.Hls && Hls.isSupported()) {
                this.hls = new Hls();
                this.hls.loadSource(this.streamUrl);
                this.hls.attachMedia(el);
            } else {
                el.src = this.streamUrl;
            }
        },

        async togglePlay() {
            const el = this._mediaEl();
            if (!el) return;

            if (this.playing) {
                el.pause();
            } else {
                try { await el.play(); } catch (_) {}
            }
        },

        onPlay() {
            this.playing = true;
            this._startSession();
            this._startHeartbeat();
        },

        onPause() {
            this.playing = false;
            this._stopHeartbeat();
            this._endSession();
        },

        setVolume(val) {
            const el = this._mediaEl();
            if (el) el.volume = parseFloat(val);
        },

        _startSession() {
            fetch('/radio/session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    action:            'start',
                    radio_channel_id:  this.channelId,
                    session_id:        this.sessionId,
                }),
            }).catch(() => {});
        },

        _startHeartbeat() {
            this.heartbeatTimer = setInterval(() => {
                fetch('/radio/session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        action:           'heartbeat',
                        radio_channel_id: this.channelId,
                        session_id:       this.sessionId,
                    }),
                }).catch(() => {});
            }, 30_000);
        },

        _stopHeartbeat() {
            if (this.heartbeatTimer) {
                clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = null;
            }
        },

        _endSession() {
            const payload = JSON.stringify({
                action:           'end',
                radio_channel_id: this.channelId,
                session_id:       this.sessionId,
            });

            // Use sendBeacon so the request survives page unload
            const url = '/radio/session';
            if (navigator.sendBeacon) {
                const blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
            } else {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: payload,
                    keepalive: true,
                }).catch(() => {});
            }
        },
    };
}
