/**
 * Delivery Agent Panel — app entry point
 * mobile-first, delivery.noon.loc
 */

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

import '../shared/echo-setup.js';

// CSRF token for AJAX
window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// ── Location tracking ─────────────────────────────────────────────────────────

window.DeliveryLocation = {
    watchId: null,

    start(updateUrl) {
        if (!navigator.geolocation) return;

        this.watchId = navigator.geolocation.watchPosition(
            (pos) => this._send(updateUrl, pos.coords.latitude, pos.coords.longitude),
            null,
            { enableHighAccuracy: true, maximumAge: 15000 }
        );
    },

    stop() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
    },

    _send(url, lat, lng) {
        fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ latitude: lat, longitude: lng }),
        }).catch(() => {});
    },
};

// ── Availability toggle ───────────────────────────────────────────────────────

window.toggleAvailability = function(url, current) {
    const newState = !current;
    fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ is_available: newState }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Let Alpine.js handle the reactive update
            window.dispatchEvent(new CustomEvent('availability-changed', {
                detail: { is_available: data.is_available }
            }));
        }
    });
};

// ── OTP auto-submit ───────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const otpInputs = document.querySelectorAll('.otp-digit');
    if (!otpInputs.length) return;

    otpInputs.forEach((input, i) => {
        input.addEventListener('input', e => {
            const v = e.target.value.replace(/\D/g, '');
            e.target.value = v.slice(0, 1);

            if (v && i < otpInputs.length - 1) {
                otpInputs[i + 1].focus();
            }

            // Auto-submit when all filled
            const allFilled = [...otpInputs].every(el => el.value.length === 1);
            if (allFilled) {
                const combined = [...otpInputs].map(el => el.value).join('');
                const hiddenInput = document.getElementById('otp-combined');
                if (hiddenInput) {
                    hiddenInput.value = combined;
                    document.getElementById('deliver-form')?.dispatchEvent(new Event('otp-ready'));
                }
            }
        });

        input.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !input.value && i > 0) {
                otpInputs[i - 1].focus();
            }
        });

        input.addEventListener('paste', e => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            [...otpInputs].forEach((el, idx) => {
                el.value = pasted[idx] || '';
            });
            if (pasted.length >= otpInputs.length) {
                otpInputs[otpInputs.length - 1].focus();
                const combined = pasted.slice(0, otpInputs.length);
                const hiddenInput = document.getElementById('otp-combined');
                if (hiddenInput) {
                    hiddenInput.value = combined;
                    document.getElementById('deliver-form')?.dispatchEvent(new Event('otp-ready'));
                }
            }
        });
    });
});
