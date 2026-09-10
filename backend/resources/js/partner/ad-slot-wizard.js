import './app.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function toast(msg, type = 'success') {
    window.Toastify?.({
        text: msg,
        duration: 4000,
        gravity: 'top',
        position: 'left',
        style: { background: type === 'success' ? '#16a34a' : '#dc2626', borderRadius: '0.75rem' },
    }).showToast();
}

window.adBookingWizard = function () {
    const cfg = window.AD_SLOT_CONFIG;

    return {
        step: 1,
        loading: false,
        error: '',
        slot: cfg.slot,
        isMetered: ['cpm', 'cpc'].includes(cfg.slot.pricing_model),
        listingDestinationType: cfg.slot.vendor_type === 'classified_vendor' ? 'classified_listing' : 'listing',
        listingDestinationTypeLabel: cfg.slot.vendor_type === 'classified_vendor' ? 'Classified listing' : 'Product listing',
        dateHint: '',
        quote: null,
        destinationOptions: [],
        bookingId: null,
        files: {},
        form: {
            booked_from: '',
            booked_until: '',
            budget: null,
            destination_type: cfg.slot.vendor_type === 'classified_vendor' ? 'classified_listing' : 'listing',
            destination_reference_id: null,
            title_en: '', title_ar: '', subtitle_en: '', subtitle_ar: '', cta_label_en: '', cta_label_ar: '',
            payment_method: 'payout_deduction',
            terms: false,
        },

        init() {
            if (this.slot.pricing_model === 'fixed_weekly') this.dateHint = 'Range must be a multiple of 7 days.';
            if (this.slot.pricing_model === 'fixed_monthly') this.dateHint = 'Range must be a multiple of 30 days.';
        },

        async next() {
            this.error = '';
            if (this.step === 1) {
                if (!this.form.booked_from || !this.form.booked_until) {
                    this.error = 'Please select dates.';
                    return;
                }
                if (this.isMetered && (!this.form.budget || this.form.budget < (this.slot.min_budget || 0))) {
                    this.error = `Budget must be at least ${this.slot.min_budget} ${this.slot.currency}.`;
                    return;
                }
                await this.fetchQuote();
                if (this.error) return;
                this.step = 2;
                return;
            }
            if (this.step === 2) { this.step = 3; return; }
            if (this.step === 3) {
                if (this.form.destination_type !== 'store' && !this.form.destination_reference_id) {
                    this.error = 'Please choose a destination.';
                    return;
                }
                await this.createDraft();
                if (this.error) return;
                this.step = 4;
                return;
            }
            if (this.step === 4) {
                if (!this.files.desktop_en || !this.files.mobile_en) {
                    this.error = 'Desktop EN and Mobile EN creatives are required.';
                    return;
                }
                await this.uploadCreative();
                if (this.error) return;
                this.step = 5;
                return;
            }
        },

        prev() { if (this.step > 1) this.step -= 1; },

        async fetchQuote() {
            this.loading = true;
            try {
                const res = await fetch(cfg.quoteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                    body: JSON.stringify({
                        booked_from: this.form.booked_from,
                        booked_until: this.form.booked_until,
                        budget: this.form.budget,
                    }),
                });
                const data = await res.json();
                if (!data.success) { this.error = data.message; return; }
                this.quote = data.data;
            } catch (e) {
                this.error = 'Network error.';
            } finally { this.loading = false; }
        },

        async searchDestinations(q) {
            const url = new URL(cfg.destinationsUrl, window.location.origin);
            url.searchParams.set('type', this.form.destination_type);
            url.searchParams.set('q', q || '');
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            this.destinationOptions = data.data || [];
        },

        async createDraft() {
            this.loading = true;
            try {
                const res = await fetch(cfg.storeBookingUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                    body: JSON.stringify({
                        slot_id: this.slot.id,
                        booked_from: this.form.booked_from,
                        booked_until: this.form.booked_until,
                        budget: this.form.budget,
                        payment_method: this.form.payment_method,
                    }),
                });
                const data = await res.json();
                if (!data.success) { this.error = data.message; return; }
                this.bookingId = data.data.id;
            } catch (e) {
                this.error = 'Network error.';
            } finally { this.loading = false; }
        },

        onFile(event, slotKey) {
            const file = event.target.files[0];
            if (!file) return;
            const required = slotKey.startsWith('desktop') ? this.slot.creative_spec.desktop : this.slot.creative_spec.mobile;
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                if (required && (img.width !== required.w || img.height !== required.h)) {
                    this.error = `${slotKey} must be exactly ${required.w}x${required.h}px (got ${img.width}x${img.height}).`;
                    event.target.value = '';
                    delete this.files[slotKey];
                } else {
                    this.error = '';
                    this.files[slotKey] = file;
                }
                URL.revokeObjectURL(url);
            };
            img.src = url;
        },

        async uploadCreative() {
            this.loading = true;
            try {
                const fd = new FormData();
                Object.entries(this.files).forEach(([k, f]) => fd.append(k, f));
                fd.append('title_en', this.form.title_en || '');
                fd.append('title_ar', this.form.title_ar || '');
                fd.append('subtitle_en', this.form.subtitle_en || '');
                fd.append('subtitle_ar', this.form.subtitle_ar || '');
                fd.append('cta_label_en', this.form.cta_label_en || '');
                fd.append('cta_label_ar', this.form.cta_label_ar || '');
                fd.append('destination_type', this.form.destination_type);
                if (this.form.destination_reference_id) fd.append('destination_reference_id', this.form.destination_reference_id);

                const url = cfg.uploadCreativeUrlTemplate.replace('__ID__', this.bookingId);
                const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' }, body: fd });
                const data = await res.json();
                if (!data.success) { this.error = data.message; return; }
            } catch (e) {
                this.error = 'Network error.';
            } finally { this.loading = false; }
        },

        async submit() {
            this.loading = true;
            try {
                const url = cfg.submitUrlTemplate.replace('__ID__', this.bookingId);
                const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
                const data = await res.json();
                if (!data.success) {
                    this.error = data.message;
                    toast(data.message, 'error');
                    return;
                }
                toast('Booking submitted for review.');
                window.location.href = `/partner/ad-bookings/${this.bookingId}`;
            } catch (e) {
                this.error = 'Network error.';
            } finally { this.loading = false; }
        },
    };
};
