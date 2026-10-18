/**
 * Portal — Travel Agency Registration Wizard
 * Alpine.js component that drives the 3-step travel-agency registration form
 * (account → agency details → review) on portal.noon.loc.
 *
 * Registered as Alpine.data('travelAgencyWizard', ...) so it is available when
 * Alpine initialises from portal/app.js (loaded after this module).
 */

function travelAgencyWizard() {
    return {
        // ── State ──────────────────────────────────────────────────────────
        step: 1,
        totalSteps: 3,
        loading: false,
        globalError: '',
        errors: {},

        form: {
            // Step 1 — account
            name: '', email: '', phone: '', country_id: '',
            password: '', password_confirmation: '',
            // Step 2 — agency
            agency_name: '', license_number: '',
            // Step 3 — review
            terms_agreed: false, privacy_agreed: false,
        },

        // Logo (optional) staged on the server during step 2
        logoFile: null,
        logoFilename: '',
        logoPreview: '',
        existingLogo: false,

        // ── Lifecycle ──────────────────────────────────────────────────────
        init(opts = {}) {
            if (opts.currentStep && opts.currentStep > 1) {
                this.step = Math.min(opts.currentStep, this.totalSteps);
            }

            const saved = opts.savedData || {};
            if (saved.step1) Object.assign(this.form, saved.step1);
            if (saved.step2) Object.assign(this.form, saved.step2);

            if (saved.step2 && saved.step2.logo_filename) {
                this.logoFilename = saved.step2.logo_filename;
                this.existingLogo = true;
            }
        },

        // ── Navigation ─────────────────────────────────────────────────────
        stepTitle() {
            return [
                '',
                window.t('portal.travel_agency.step_title_account'),
                window.t('portal.travel_agency.step_title_agency'),
                window.t('portal.travel_agency.step_title_review'),
            ][this.step] || '';
        },

        prevStep() {
            this.errors = {};
            this.globalError = '';
            this.step = Math.max(1, this.step - 1);
        },

        async nextStep() {
            this.globalError = '';
            this.errors = {};

            if (this.step >= this.totalSteps) {
                return;
            }

            this.loading = true;
            try {
                const url = window._routes.step.replace('__STEP__', this.step);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window._csrf,
                        'Accept': 'application/json',
                    },
                    body: this.stepFormData(this.step),
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    this.errors = data.errors || {};
                    if (data.message) this.globalError = data.message;
                    return;
                }

                this.step = data.next_step;
            } catch (e) {
                this.globalError = window.t('portal.travel_agency.network_error');
            } finally {
                this.loading = false;
            }
        },

        /**
         * Build the multipart payload for a step. FormData is used for every
         * step so the optional logo uploads alongside the step 2 fields.
         */
        stepFormData(step) {
            const fd = new FormData();
            const add = (key, value) => fd.append(key, value ?? '');

            if (step === 1) {
                add('name', this.form.name);
                add('email', this.form.email);
                add('phone', this.form.phone);
                add('country_id', this.form.country_id);
                add('password', this.form.password);
                add('password_confirmation', this.form.password_confirmation);
            }

            if (step === 2) {
                add('agency_name', this.form.agency_name);
                add('license_number', this.form.license_number);
                if (this.logoFile) {
                    fd.append('logo', this.logoFile);
                }
            }

            return fd;
        },

        // ── Logo handling ──────────────────────────────────────────────────
        onLogoChange(event) {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }

            this.logoFile = file;
            this.logoFilename = file.name;
            this.existingLogo = false;
            this.errors.logo = null;

            if (this.logoPreview) {
                URL.revokeObjectURL(this.logoPreview);
            }
            this.logoPreview = URL.createObjectURL(file);
        },

        async removeLogo() {
            if (this.logoPreview) {
                URL.revokeObjectURL(this.logoPreview);
                this.logoPreview = '';
            }

            this.logoFile = null;
            this.logoFilename = '';
            this.errors.logo = null;

            if (this.existingLogo) {
                this.existingLogo = false;
                try {
                    await fetch(window._routes.logoRemove, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': window._csrf,
                            'Accept': 'application/json',
                        },
                    });
                } catch (e) {
                    /* best effort */
                }
            }
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
                    }),
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    this.errors = data.errors || {};
                    this.globalError = data.message || window.t('portal.travel_agency.submit_failed');
                    return;
                }

                window.location.href = data.redirect;
            } catch (e) {
                this.globalError = window.t('portal.travel_agency.network_error');
            } finally {
                this.loading = false;
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('travelAgencyWizard', travelAgencyWizard);
});
