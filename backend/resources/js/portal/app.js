/**
 * Portal JS — portal.noon.loc
 * Loaded by @vite(['resources/css/app.css', 'resources/js/portal/app.js'])
 */
import Alpine from 'alpinejs';

// ── Alpine global setup ──────────────────────────────────────────────────
window.Alpine = Alpine;

// Language switcher store
Alpine.store('locale', {
    current: document.documentElement.lang || 'ar',
    toggle() {
        const next = this.current === 'ar' ? 'en' : 'ar';
        window.location.href = `/language/${next}`;
    },
});

Alpine.start();

/* ---------- Reverb / Echo + notification bell ---------- */
import '../shared/echo-setup.js';

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
