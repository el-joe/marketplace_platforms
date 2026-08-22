/**
 * partner/influencer-promotion.js
 * Alpine component for the "Influencer Promotion" tab on the listing show page.
 */

import './app.js';
import { csrfToken } from './datatable.js';

window.influencerPromotionPanel = function () {
    return {
        show: false,
        submitting: false,
        errorMessage: '',
        selectedIds: [],
        feePerSlot: 0,
        currency: '',

        init() {
            const $select = window.$ && window.$('#ip-marketer-select');
            if ($select && $select.length) {
                $select.on('change', () => {
                    this.selectedIds = $select.val() || [];
                });
            }
            this.loadFeePreview();
        },

        async loadFeePreview() {
            const cfg = window.INFLUENCER_PROMOTION;
            if (!cfg) return;
            try {
                const res = await fetch(cfg.feePreviewUrl, {
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json();
                this.feePerSlot = data.fee_per_slot ?? 0;
                this.currency = data.currency ?? '';
            } catch (e) {
                // silently ignore — fee will show as 0 until retried
            }
        },

        openModal() {
            this.errorMessage = '';
            this.show = true;
        },

        closeModal() {
            this.show = false;
        },

        formatMoney(amount) {
            return `${Number(amount || 0).toLocaleString()} ${this.currency}`;
        },

        async submit() {
            const cfg = window.INFLUENCER_PROMOTION;
            if (!cfg || this.selectedIds.length === 0) return;

            this.submitting = true;
            this.errorMessage = '';

            try {
                const res = await fetch(cfg.requestUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ marketer_ids: this.selectedIds }),
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    this.errorMessage = data.message || 'حدث خطأ أثناء إرسال الطلب.';
                    return;
                }

                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.reload();
                }
            } catch (e) {
                this.errorMessage = 'تعذر الاتصال بالخادم. يرجى المحاولة مرة أخرى.';
            } finally {
                this.submitting = false;
            }
        },
    };
};
