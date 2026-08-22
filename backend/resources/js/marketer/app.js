/**
 * Marketer Portal — JS entry point (marketer.noon.loc)
 */
import Alpine from 'alpinejs';
import Toastify from 'toastify-js';
import 'toastify-js/src/toastify.css';

window.Alpine = Alpine;
window.Toastify = Toastify;

Alpine.start();

import '../shared/echo-setup.js';
