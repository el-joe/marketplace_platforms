/**
 * Classified Listings Map — Leaflet.js
 * Exports: initLocationPicker, initBrowseMap, initDetailMap, loadListingsInBounds
 */

const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const TILE_ATTR = '© OpenStreetMap contributors';

function defaultIcon(color = '#0284c7') {
    return L.divIcon({
        className: '',
        html: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36">
            <path d="M14 0C6.27 0 0 6.27 0 14c0 9.94 14 22 14 22S28 23.94 28 14C28 6.27 21.73 0 14 0z" fill="${color}"/>
            <circle cx="14" cy="14" r="6" fill="white"/>
        </svg>`,
        iconSize: [28, 36],
        iconAnchor: [14, 36],
        popupAnchor: [0, -36],
    });
}

/**
 * initLocationPicker
 * Renders a draggable pin map for the listing create form.
 * @param {string} elementId - DOM id of the map container
 * @param {Function} onPick - callback(lat, lng) called on each pin move
 */
function initLocationPicker(elementId, onPick) {
    const el = document.getElementById(elementId);
    if (!el) return;

    const map = L.map(elementId, { zoomControl: true }).setView([24.7136, 46.6753], 5);
    L.tileLayer(TILE_URL, { attribution: TILE_ATTR }).addTo(map);

    let marker = null;

    function placeMarker(latlng) {
        if (marker) {
            marker.setLatLng(latlng);
        } else {
            marker = L.marker(latlng, { draggable: true, icon: defaultIcon() }).addTo(map);
            marker.on('dragend', (e) => {
                const pos = e.target.getLatLng();
                reverseGeocode(pos.lat, pos.lng);
                onPick(pos.lat, pos.lng);
            });
        }
        onPick(latlng.lat, latlng.lng);
    }

    map.on('click', (e) => {
        placeMarker(e.latlng);
        reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    // Try browser geolocation
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((pos) => {
            const latlng = L.latLng(pos.coords.latitude, pos.coords.longitude);
            map.setView(latlng, 13);
        });
    }
}

async function reverseGeocode(lat, lng) {
    try {
        const resp = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`,
            { headers: { 'Accept-Language': document.documentElement.lang || 'ar' } }
        );
        const data = await resp.json();
        const display = document.getElementById('coords-display');
        if (display && data.display_name) {
            display.textContent = data.display_name;
        }
    } catch (_) { /* ignore */ }
}

/**
 * initBrowseMap
 * Renders a clustered map of all active listings.
 * @param {string} elementId
 * @param {string} dataUrl - URL to fetch GeoJSON pin data
 */
function initBrowseMap(elementId, dataUrl) {
    const el = document.getElementById(elementId);
    if (!el) return;

    const map = L.map(elementId, { zoomControl: true }).setView([24.7136, 46.6753], 5);
    L.tileLayer(TILE_URL, { attribution: TILE_ATTR }).addTo(map);

    let markersLayer = L.layerGroup().addTo(map);

    async function loadListingsInBounds(bounds) {
        try {
            const b = {
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east:  bounds.getEast(),
                west:  bounds.getWest(),
            };
            const url = new URL(dataUrl, window.location.origin);
            url.searchParams.set('bounds[north]', b.north);
            url.searchParams.set('bounds[south]', b.south);
            url.searchParams.set('bounds[east]',  b.east);
            url.searchParams.set('bounds[west]',  b.west);

            const resp = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' },
            });
            const listings = await resp.json();

            markersLayer.clearLayers();

            listings.forEach((l) => {
                const color = l.purpose === 'sale' ? '#10b981' : '#3b82f6';
                const m = L.marker([l.lat, l.lng], { icon: defaultIcon(color) });
                m.bindPopup(`
                    <div style="min-width:180px">
                        <p style="font-weight:600;margin-bottom:4px">${l.title}</p>
                        <p style="color:#0284c7;font-weight:700">${l.price}</p>
                        <a href="${l.url}" style="font-size:12px;color:#0284c7">عرض الإعلان →</a>
                    </div>
                `);
                markersLayer.addLayer(m);
            });
        } catch (_) { /* ignore */ }
    }

    map.on('moveend', () => loadListingsInBounds(map.getBounds()));
    loadListingsInBounds(map.getBounds());
}

/**
 * initDetailMap
 * Single pin on the listing detail page.
 * @param {string} elementId
 * @param {{ lat: number, lng: number, title: string }} opts
 */
function initDetailMap(elementId, opts) {
    const el = document.getElementById(elementId);
    if (!el) return;

    const map = L.map(elementId).setView([opts.lat, opts.lng], 14);
    L.tileLayer(TILE_URL, { attribution: TILE_ATTR }).addTo(map);

    L.marker([opts.lat, opts.lng], { icon: defaultIcon() })
        .addTo(map)
        .bindPopup(opts.title)
        .openPopup();
}

// Expose globally
window.initLocationPicker = initLocationPicker;
window.initBrowseMap      = initBrowseMap;
window.initDetailMap      = initDetailMap;
