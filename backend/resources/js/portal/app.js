/**
 * Portal JS — portal.noon.loc
 * Loaded by @vite(['resources/css/app.css', 'resources/js/portal/app.js'])
 */
import Alpine from 'alpinejs';
import Collapse from '@alpinejs/collapse';
// Registers window.notificationBell (used by <x-notification-bell> in the carrier layout).
// ES imports are hoisted, so this runs before Alpine.start() below.
import '../shared/echo-setup.js';

// ── Alpine global setup ──────────────────────────────────────────────────
window.Alpine = Alpine;

Alpine.plugin(Collapse);

// Language switcher store
Alpine.store('locale', {
    current: document.documentElement.lang || 'ar',
    toggle() {
        const next = this.current === 'ar' ? 'en' : 'ar';
        window.location.href = `/language/${next}`;
    },
});

Alpine.start();

/* ---------- Scroll-to-top button ---------- */

// ── Scroll-to-top button ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('scroll-top');
    if (!btn) return;

    window.addEventListener('scroll', () => {
        btn.classList.toggle('opacity-0', window.scrollY < 400);
        btn.classList.toggle('pointer-events-none', window.scrollY < 400);
    });

    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
});
