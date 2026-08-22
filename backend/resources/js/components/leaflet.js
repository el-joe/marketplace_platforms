/**
 * leaflet.js — bundles Leaflet locally and exposes it as window.L.
 *
 * Loaded as a Vite module (deferred), so consumers must wait for
 * DOMContentLoaded before referencing window.L, same as jQuery in datatable.js.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.L = L;
