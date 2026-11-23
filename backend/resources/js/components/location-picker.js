/**
 * Latitude/longitude picker for <x-location-picker> (client item #19 — distance-based delivery origins).
 * resources/js/components/location-picker.js
 *
 * - Google Maps (only when the page passes a key): draggable pin, click to place; the script is injected the
 *   first time a picker scrolls into view, same lazy pattern as the shipping-zones distance preview.
 * - Always: Arabic-digit tolerant inputs, "use my current location" (browser geolocation), "open in Google Maps".
 * - Optional AJAX save: a <form data-location-form data-url="…" data-method="PUT|POST"> around the picker is
 *   submitted as JSON; 422 errors are shown under [data-error-for="field"], [data-lp-status] badges toggle.
 */
const DIGITS = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9', '٫': '.', '٬': '', '−': '-' };

let mapsLoading = null;

function toNumber(raw) {
    const text = String(raw ?? '').replace(/[٠-٩٫٬−]/g, (ch) => DIGITS[ch]).trim();
    if (text === '') return null;
    const num = Number(text);
    return Number.isFinite(num) ? num : NaN;
}

function notify(message, ok = true) {
    if (window.Toast) {
        window.Toast[ok ? 'success' : 'error'](message);
    } else if (window.Toastify) {
        window.Toastify({ text: message, backgroundColor: ok ? '#22c55e' : '#ef4444', duration: 3500, gravity: 'top', position: 'center' }).showToast();
    }
}

function loadMaps(key) {
    if (window.google && window.google.maps) return Promise.resolve();
    if (mapsLoading) return mapsLoading;
    mapsLoading = new Promise((resolve, reject) => {
        window.__locationPickerMapsReady = resolve;
        const script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&callback=__locationPickerMapsReady';
        script.async = true;
        script.onerror = reject;
        document.head.appendChild(script);
    });
    return mapsLoading;
}

function initPicker(root) {
    const latInput = root.querySelector('[data-lp-lat]');
    const lngInput = root.querySelector('[data-lp-lng]');
    const openLink = root.querySelector('[data-lp-open]');
    const locateBtn = root.querySelector('[data-lp-locate]');
    const locateLabel = root.querySelector('[data-lp-locate-label]');
    const mapEl = root.querySelector('[data-lp-map]');
    const i18n = JSON.parse(root.dataset.i18n || '{}');
    const editable = !latInput.disabled;
    let map = null;
    let marker = null;

    const current = () => {
        const lat = toNumber(latInput.value);
        const lng = toNumber(lngInput.value);
        const valid = lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
        return valid ? { lat, lng } : null;
    };

    const syncLink = () => {
        const pos = current();
        openLink.classList.toggle('hidden', !pos);
        if (pos) openLink.href = 'https://www.google.com/maps/search/?api=1&query=' + pos.lat + ',' + pos.lng;
    };

    const syncMarker = () => {
        if (!map) return;
        const pos = current();
        if (!pos) {
            marker?.setMap(null);
            return;
        }
        if (!marker) {
            marker = new google.maps.Marker({ map, position: pos, draggable: editable });
            marker.addListener('dragend', (e) => setPosition(e.latLng.lat(), e.latLng.lng(), false));
        } else {
            marker.setMap(map);
            marker.setPosition(pos);
        }
    };

    function setPosition(lat, lng, pan = true) {
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
        latInput.dispatchEvent(new Event('change', { bubbles: true }));
        syncLink();
        syncMarker();
        if (pan && map) {
            map.panTo({ lat, lng });
            if (map.getZoom() < 15) map.setZoom(16);
        }
    }

    [latInput, lngInput].forEach((input) => {
        input.addEventListener('input', () => { syncLink(); syncMarker(); });
        // Arabic keyboard digits → ASCII so plain (non-AJAX) forms such as the warehouse form validate too.
        input.addEventListener('change', () => {
            const num = toNumber(input.value);
            if (num !== null && !isNaN(num) && String(num) !== input.value) input.value = String(num);
        });
    });

    locateBtn?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            notify(i18n.geolocation_unsupported, false);
            return;
        }
        locateBtn.disabled = true;
        locateLabel.textContent = i18n.locating;
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                setPosition(coords.latitude, coords.longitude);
                locateBtn.disabled = false;
                locateLabel.textContent = i18n.use_current_location;
            },
            () => {
                notify(i18n.geolocation_failed, false);
                locateBtn.disabled = false;
                locateLabel.textContent = i18n.use_current_location;
            },
            { enableHighAccuracy: true, timeout: 15000 },
        );
    });

    root.querySelector('[data-lp-clear]')?.addEventListener('click', () => {
        latInput.value = '';
        lngInput.value = '';
        syncLink();
        syncMarker();
    });

    syncLink();

    const key = root.dataset.mapsKey;
    if (!mapEl || !key) return;

    const drawMap = () => {
        const pos = current();
        const center = pos || { lat: Number(root.dataset.defaultLat), lng: Number(root.dataset.defaultLng) };
        map = new google.maps.Map(mapEl, { center, zoom: pos ? 16 : 11, mapTypeControl: false, streetViewControl: false });
        if (editable) map.addListener('click', (e) => setPosition(e.latLng.lat(), e.latLng.lng(), false));
        syncMarker();
    };

    // Lazy: only load Google Maps once the picker is actually visible (tabs, collapsed cards).
    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        observer.disconnect();
        loadMaps(key).then(drawMap).catch(() => {});
    });
    observer.observe(mapEl);
}

function initForm(form) {
    const saveBtn = form.querySelector('[type="submit"]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        form.querySelectorAll('[data-error-for]').forEach((el) => { el.textContent = ''; el.classList.add('hidden'); });

        const payload = Object.fromEntries(new FormData(form).entries());
        delete payload._token;
        delete payload._method;

        const label = saveBtn?.textContent;
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = form.dataset.savingLabel || label;
        }

        try {
            const res = await fetch(form.dataset.url, {
                method: form.dataset.method || 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));

            if (res.status === 422 && json.errors) {
                Object.entries(json.errors).forEach(([field, messages]) => {
                    const el = form.querySelector('[data-error-for="' + field + '"]');
                    if (el) {
                        el.textContent = messages[0];
                        el.classList.remove('hidden');
                    }
                });
                notify(json.message || form.dataset.failedLabel, false);
                return;
            }
            if (!res.ok) throw new Error(json.message || form.dataset.failedLabel);

            const hasCoordinates = !!(json.data && json.data.has_coordinates);
            form.querySelectorAll('[data-lp-status]').forEach((el) => {
                el.classList.toggle('hidden', (el.dataset.lpStatus === 'set') !== hasCoordinates);
            });
            notify(json.message);
        } catch (error) {
            notify(error.message || form.dataset.failedLabel, false);
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = label;
            }
        }
    });
}

function boot() {
    document.querySelectorAll('[data-location-picker]').forEach(initPicker);
    document.querySelectorAll('form[data-location-form]').forEach(initForm);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
