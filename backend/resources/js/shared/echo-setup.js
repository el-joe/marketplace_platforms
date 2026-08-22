/**
 * Shared Laravel Echo / Reverb initialisation.
 *
 * Import this once in each panel's JS entry-point AFTER any global Alpine or
 * jQuery setup so window.Echo is available before Alpine components mount.
 *
 *   import './shared/echo-setup.js';   // in app.js, partner/app.js, portal/app.js, delivery/app.js
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '8080'),
    wssPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '8080'),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    // authEndpoint defaults to /broadcasting/auth on the current origin,
    // which is correct since each subdomain registers its own auth route.
});

/**
 * Alpine component factory for the notification bell.
 * Registered as window.notificationBell() so the Blade component can call it
 * inline without requiring a separate @vite entry per panel.
 *
 * @param {string} echoChannel  e.g. 'admin.1' or 'vendor.42'
 */
window.notificationBell = function (echoChannel) {
    return {
        open: false,
        unreadCount: 0,
        items: [],
        loaded: false,

        init() {
            this.fetchRecent();
            this.subscribeEcho();
        },

        fetchRecent() {
            fetch('/notifications/recent', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(r => r.ok ? r.json() : null)
                .then(json => {
                    if (!json?.data) return;
                    this.unreadCount = json.data.count ?? 0;
                    this.items = json.data.items ?? [];
                    this.loaded = true;
                })
                .catch(() => {});
        },

        subscribeEcho() {
            if (!window.Echo) return;
            window.Echo.private(echoChannel)
                .listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (e) => {
                    this.unreadCount++;
                    this.items.unshift({
                        id:         e.id ?? null,
                        title:      e.title ?? t('shared.notifications.new_notification'),
                        message:    e.message ?? '',
                        url:        e.url ?? '#',
                        read:       false,
                        created_at: new Date().toLocaleString(),
                    });
                    this.toast(e.title ?? t('shared.notifications.new_notification'));
                });
        },

        toast(message) {
            if (window.Toastify) {
                Toastify({
                    text: message,
                    duration: 4000,
                    gravity: 'top',
                    position: 'right',
                    style: { background: '#0284c7' },
                }).showToast();
            }
        },

        markRead(id, url) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            }).catch(() => {});

            const item = this.items.find(i => i.id === id);
            if (item && !item.read) {
                item.read = true;
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            }

            if (url && url !== '#') window.location.href = url;
        },

        markAllRead() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            })
                .then(() => {
                    this.items.forEach(i => { i.read = true; });
                    this.unreadCount = 0;
                })
                .catch(() => {});
        },
    };
};
