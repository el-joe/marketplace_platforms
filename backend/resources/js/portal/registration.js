/**
 * Portal — Vendor Registration Wizard
 * Alpine.js component + document upload handling (custom, no FilePond dependency)
 *
 * Registered as Alpine.data('registrationWizard', ...) so it is available
 * when Alpine initialises from portal/app.js.
 */

// ── Helper: slug from any text (handles Arabic by stripping non-latin) ──────
function makeSlug(text) {
    return text
        .toLowerCase()
        .replace(/[\u0600-\u06ff\s]+/g, '-')   // Arabic chars → dash
        .replace(/[^a-z0-9-]/g, '')             // strip everything else
        .replace(/-{2,}/g, '-')                 // collapse multiple dashes
        .replace(/^-|-$/g, '');                 // trim leading/trailing
}

// ── Debounce utility ─────────────────────────────────────────────────────────
function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

// ── Main component ────────────────────────────────────────────────────────────
function registrationWizard() {
    return {
        // ── State ──────────────────────────────────────────────────────────
        step: 1,
        totalSteps: 5,
        loading: false,
        globalError: '',
        errors: {},

        // Form data
        form: {
            // Step 1
            name: '', email: '', phone: '', country_id: '',
            password: '', password_confirmation: '',
            // Step 2
            store_name: '', store_slug: '', store_description: '',
            business_type: 'individual', business_name: '',
            business_registration_number: '', tax_id: '',
            // Step 3
            contact_email: '', contact_phone: '', whatsapp_number: '',
            city_id: '', area: '', street_address: '',
            building: '', floor: '', apartment: '', postal_code: '',
            // Step 5
            terms_agreed: false, privacy_agreed: false,
        },

        // Dynamic document types loaded from server per country
        docTypes: [],
        docTypesLoading: false,

        // Document state (keyed by doc type code, populated dynamically)
        documents: {},
        docFilenames: {},
        docUploading: {},
        docErrors: {},
        docExpiries: {},

        // Slug check state
        slugStatus: '',    // '' | 'available' | 'taken'
        slugChecking: false,

        // Cities for country
        cities: [],

        // ── Lifecycle ──────────────────────────────────────────────────────
        init(opts = {}) {
            // Restore step from server session
            if (opts.currentStep && opts.currentStep > 1) {
                this.step = opts.currentStep;
            }

            // Restore form values from session
            const saved = opts.savedData || {};
            if (saved.step1) Object.assign(this.form, saved.step1);
            if (saved.step2) Object.assign(this.form, saved.step2);
            if (saved.step3) Object.assign(this.form, saved.step3);

            // Restore document paths (no filenames — already on server)
            if (saved.documents) {
                Object.assign(this.documents, saved.documents);
                for (const type in saved.documents) {
                    if (saved.documents[type]) {
                        this.docFilenames[type] = window.t('portal.registration.already_uploaded');
                    }
                }
            }

            // Load cities if country_id is already set
            if (this.form.country_id) {
                this.loadCities(this.form.country_id);
            }

            // If resuming directly onto step 4+, load doc types immediately
            if (this.step >= 4 && this.form.country_id) {
                this.loadDocumentTypes(this.form.country_id);
            }

            // Watch country_id changes → reload cities and clear doc types
            this.$watch('form.country_id', (id) => {
                this.form.city_id = '';
                this.cities = [];
                // Clear uploaded docs when country changes (requirements differ)
                this.docTypes = [];
                this.documents = {};
                this.docFilenames = {};
                this.docUploading = {};
                this.docErrors = {};
                this.docExpiries = {};
                if (id) {
                    this.loadCities(id);
                    if (this.step >= 4) this.loadDocumentTypes(id);
                }
            });
        },

        // ── Navigation ─────────────────────────────────────────────────────
        stepTitle() {
            return [
                '', // placeholder for 0-index
                window.t('portal.registration.step_title_account'),
                window.t('portal.registration.step_title_store'),
                window.t('portal.registration.step_title_contact'),
                window.t('portal.registration.step_title_documents'),
                window.t('portal.registration.step_title_review'),
            ][this.step] || '';
        },

        async nextStep() {
            this.globalError = '';
            this.errors = {};

            // Step 4 (documents) — validate locally, no server save
            if (this.step === 4) {
                const missingMandatory = this.docTypes
                    .filter(d => d.requirement_level === 'mandatory' && !this.documents[d.code])
                    .map(d => d.name_ar);

                if (missingMandatory.length > 0) {
                    this.globalError = window.t('portal.registration.missing_mandatory_documents', { list: missingMandatory.join('، ') });
                    return;
                }

                // Validate expiry dates are provided for types that require them
                const missingExpiry = this.docTypes
                    .filter(d => d.requires_expiry_date && this.documents[d.code] && !this.docExpiries[d.code])
                    .map(d => d.name_ar);

                if (missingExpiry.length > 0) {
                    this.globalError = window.t('portal.registration.missing_expiry_dates', { list: missingExpiry.join('، ') });
                    return;
                }

                this.step = 5;
                return;
            }

            // Steps 1–3: save to session via AJAX
            if (this.step <= 3) {
                this.loading = true;
                try {
                    const url = window._routes.step.replace('__STEP__', this.step);
                    const payload = this.stepPayload(this.step);

                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window._csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        this.errors = data.errors || {};
                        if (data.message) this.globalError = data.message;
                        return;
                    }

                    this.step = data.next_step;
                    // Load doc types when landing on step 4 after step 3 validation
                    if (this.step === 4 && this.form.country_id) {
                        this.loadDocumentTypes(this.form.country_id);
                    }
                } catch (e) {
                    this.globalError = window.t('portal.registration.network_error');
                } finally {
                    this.loading = false;
                }
                return;
            }

            this.step = Math.min(this.step + 1, this.totalSteps);
        },

        prevStep() {
            this.errors = {};
            this.globalError = '';
            this.step = Math.max(1, this.step - 1);
        },

        // ── Payload builder per step ───────────────────────────────────────
        stepPayload(step) {
            if (step === 1) {
                return {
                    name: this.form.name,
                    email: this.form.email,
                    phone: this.form.phone,
                    country_id: this.form.country_id,
                    password: this.form.password,
                    password_confirmation: this.form.password_confirmation,
                };
            }
            if (step === 2) {
                return {
                    store_name: this.form.store_name,
                    store_slug: this.form.store_slug,
                    store_description: this.form.store_description,
                    business_type: this.form.business_type,
                    business_name: this.form.business_name,
                    business_registration_number: this.form.business_registration_number,
                    tax_id: this.form.tax_id,
                };
            }
            if (step === 3) {
                return {
                    contact_email: this.form.contact_email,
                    contact_phone: this.form.contact_phone,
                    whatsapp_number: this.form.whatsapp_number,
                    city_id: this.form.city_id,
                    area: this.form.area,
                    street_address: this.form.street_address,
                    building: this.form.building,
                    floor: this.form.floor,
                    apartment: this.form.apartment,
                    postal_code: this.form.postal_code,
                };
            }
            return {};
        },

        // ── Final submit ───────────────────────────────────────────────────
        async submit() {
            this.globalError = '';
            this.errors = {};
            this.loading = true;

            try {
                const res = await fetch(window._routes.complete, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window._csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        terms_agreed: this.form.terms_agreed ? '1' : '0',
                        privacy_agreed: this.form.privacy_agreed ? '1' : '0',
                        doc_expiries: this.docExpiries,
                    }),
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    this.errors = data.errors || {};
                    this.globalError = data.message || window.t('portal.registration.submit_failed');
                    return;
                }

                // Redirect to success page
                window.location.href = data.redirect;
            } catch (e) {
                this.globalError = window.t('portal.registration.network_error');
            } finally {
                this.loading = false;
            }
        },

        // ── Slug helpers ───────────────────────────────────────────────────
        generateSlug(storeName) {
            if (!this.form.store_slug || this.form.store_slug === makeSlug(this._lastStoreName || '')) {
                this.form.store_slug = makeSlug(storeName);
                this.slugStatus = '';
            }
            this._lastStoreName = storeName;
        },

        checkSlugAvailability: debounce(async function () {
            const slug = this.form.store_slug.trim();
            if (!slug) { this.slugStatus = ''; return; }

            this.slugChecking = true;
            try {
                const url = window._routes.checkSlug + '?slug=' + encodeURIComponent(slug);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                // Server returns canonical slug; update if it changed
                if (data.slug && data.slug !== slug) this.form.store_slug = data.slug;
                this.slugStatus = data.available ? 'available' : 'taken';
            } catch {
                this.slugStatus = '';
            } finally {
                this.slugChecking = false;
            }
        }, 600),

        // ── City loading ───────────────────────────────────────────────────
        async loadCities(countryId) {
            try {
                const res = await fetch(window._routes.cities + '?country_id=' + encodeURIComponent(countryId), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.cities = data.cities || [];
            } catch {
                this.cities = [];
            }
        },

        // ── Document type loading ──────────────────────────────────────────
        async loadDocumentTypes(countryId) {
            this.docTypesLoading = true;
            try {
                const url = window._routes.docRequirements + '?country_id=' + encodeURIComponent(countryId);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.docTypes = data.documents || [];
            } catch {
                this.docTypes = [];
            } finally {
                this.docTypesLoading = false;
            }
        },

        // ── Document upload ────────────────────────────────────────────────
        async uploadDocument(event, type, docTypeMeta) {
            const file = event.target.files?.[0];
            if (!file) return;

            const maxKb    = docTypeMeta?.max_file_size_kb ?? 5120;
            const maxBytes = maxKb * 1024;
            if (file.size > maxBytes) {
                this.docErrors[type] = window.t('portal.registration.file_too_large', { max: Math.round(maxKb / 1024) });
                event.target.value = '';
                return;
            }

            const acceptedExts = docTypeMeta?.accepted_file_types ?? ['pdf', 'jpg', 'jpeg', 'png'];
            const mimeMap = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' };
            const allowed = acceptedExts.map(e => mimeMap[e]).filter(Boolean);
            if (allowed.length && !allowed.includes(file.type)) {
                this.docErrors[type] = window.t('portal.registration.unsupported_file_type', { types: acceptedExts.join('، ').toUpperCase() });
                event.target.value = '';
                return;
            }

            this.docErrors[type] = '';
            this.docUploading[type] = true;

            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('document_type', type);

                const res = await fetch(window._routes.upload, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': window._csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    const msgs = data.errors ? Object.values(data.errors).flat() : [];
                    this.docErrors[type] = msgs[0] || window.t('portal.registration.upload_failed');
                    event.target.value = '';
                    return;
                }

                this.documents[type] = data.path;
                this.docFilenames[type] = data.filename;
            } catch {
                this.docErrors[type] = window.t('portal.registration.upload_network_error');
            } finally {
                this.docUploading[type] = false;
                event.target.value = '';
            }
        },

        async removeDocument(type) {
            if (!this.documents[type]) return;

            try {
                await fetch(window._routes.docRemove, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window._csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ document_type: type }),
                });
            } catch { /* best effort */ }

            this.documents[type] = null;
            this.docFilenames[type] = '';
            this.docErrors[type] = '';
        },

        // ── Review helpers ─────────────────────────────────────────────────
        businessTypeLabel(type) {
            return {
                individual: window.t('portal.registration.business_type_individual'),
                sole_prop: window.t('portal.registration.business_type_sole_prop'),
                llc: window.t('portal.registration.business_type_llc'),
                corp: window.t('portal.registration.business_type_corp'),
            }[type] || type;
        },

        selectedCityName() {
            const city = this.cities.find(c => c.id === this.form.city_id);
            return city ? city.name_ar : (this.form.city_id || '—');
        },
    };
}

// Register with Alpine before it starts (portal/app.js calls Alpine.start())
document.addEventListener('alpine:init', () => {
    window.Alpine.data('registrationWizard', registrationWizard);
});
