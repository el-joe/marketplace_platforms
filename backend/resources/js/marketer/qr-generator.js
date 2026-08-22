/**
 * resources/js/marketer/qr-generator.js
 * Stand-alone QR generate form + WhatsApp share button helper.
 * Used on campaign show page and QR codes index.
 */

document.addEventListener('DOMContentLoaded', () => {
    // ── QR Generate form (generic) ────────────────────────────────────────────
    document.querySelectorAll('[data-qr-generate-form]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('[type=submit]');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const url = form.dataset.url;
            if (!url) return;

            btn.disabled = true;
            btn.textContent = t('marketer.qr_generator.generating');

            try {
                const payload = Object.fromEntries(new FormData(form));
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (data.success) {
                    const resultBox = document.getElementById(form.dataset.resultTarget);
                    if (resultBox) {
                        const img = resultBox.querySelector('img');
                        const downloadBtn = resultBox.querySelector('[data-download]');
                        if (img) img.src = data.data.qr_url;
                        if (downloadBtn) downloadBtn.href = data.data.download_url;
                        resultBox.classList.remove('hidden');
                    }
                } else {
                    alert(data.message || t('marketer.qr_generator.could_not_generate'));
                }
            } catch {
                alert(t('marketer.qr_generator.network_error'));
            } finally {
                btn.disabled = false;
                btn.textContent = btn.dataset.label ?? t('marketer.qr_generator.generate_label');
            }
        });
    });

    // ── WhatsApp share button ─────────────────────────────────────────────────
    document.querySelectorAll('[data-whatsapp-share]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const url = btn.dataset.whatsappShare;
            if (!url) return;
            const text = btn.dataset.shareText ?? t('marketer.qr_generator.share_default_text');
            window.open('https://wa.me/?text=' + encodeURIComponent(text + ' ' + url), '_blank');
        });
    });

    // ── Copy-to-clipboard generic ─────────────────────────────────────────────
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.copy;
            const el = target ? document.getElementById(target) : null;
            const value = el ? (el.value || el.textContent).trim() : btn.dataset.copyValue;
            if (!value) return;
            navigator.clipboard.writeText(value).then(() => {
                const original = btn.textContent;
                btn.textContent = t('marketer.qr_generator.copied');
                setTimeout(() => { btn.textContent = original; }, 2000);
            });
        });
    });
});
