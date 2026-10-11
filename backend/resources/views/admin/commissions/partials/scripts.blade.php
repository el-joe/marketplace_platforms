@php
    $jsText = [
        'saving' => __('commissions.js.saving'),
        'saved' => __('commissions.saved'),
        'failed' => __('commissions.js.failed'),
        'network' => __('commissions.js.network'),
    ];
@endphp
{{-- Shared behaviour for in-page editors. Forms opt in with data-ajax; everything else is a normal POST that
     redirects back to this same URL (so the selected ?tab= and filters are kept). --}}
<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const text = @json($jsText);

    // Message carried across the reload that follows a successful save.
    try {
        const flash = sessionStorage.getItem('commissionsFlash');
        if (flash) {
            sessionStorage.removeItem('commissionsFlash');
            const box = document.querySelector('[data-flash]');
            if (box) { box.textContent = flash; box.classList.remove('hidden'); }
        }
    } catch (e) { /* storage unavailable: the page still reloads with fresh data */ }

    const clearErrors = (form) => {
        form.querySelectorAll('[data-error-for]').forEach((el) => { el.textContent = ''; el.classList.add('hidden'); });
        form.querySelectorAll('[aria-invalid="true"]').forEach((el) => el.removeAttribute('aria-invalid'));
        const status = form.querySelector('[data-status]');
        if (status) { status.textContent = ''; status.className = 'text-xs'; }
    };
    const setStatus = (form, message, tone) => {
        const status = form.querySelector('[data-status]');
        if (!status) return;
        status.textContent = message;
        status.className = 'text-xs ' + (tone === 'error' ? 'text-red-700' : tone === 'ok' ? 'text-green-700' : 'text-gray-500');
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form');
        if (!form) return;

        // Guard every in-page form against repeat submissions.
        if (form.dataset.busy) { event.preventDefault(); return; }
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }

        const button = form.querySelector('[type="submit"]');
        if (!form.hasAttribute('data-ajax')) {
            form.dataset.busy = '1';
            if (button) button.disabled = true;
            return; // native submit: server redirects back to this page with flash/errors
        }

        event.preventDefault();
        form.dataset.busy = '1';
        if (button) button.disabled = true;
        clearErrors(form);
        setStatus(form, text.saving, 'info');

        let reloading = false;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));

            if (response.ok) {
                reloading = true;
                setStatus(form, body.message || text.saved, 'ok');
                try { sessionStorage.setItem('commissionsFlash', body.message || text.saved); } catch (e) { /* ignore */ }
                window.location.reload();
            } else if (response.status === 422 && body.errors) {
                Object.entries(body.errors).forEach(([field, messages]) => {
                    const slot = form.querySelector(`[data-error-for="${field}"]`);
                    const input = form.querySelector(`[name="${field}"]`);
                    if (input) input.setAttribute('aria-invalid', 'true');
                    if (slot) { slot.textContent = messages.join(' '); slot.classList.remove('hidden'); }
                });
                setStatus(form, body.message || text.failed, 'error');
            } else {
                setStatus(form, body.message || text.failed, 'error');
            }
        } catch (error) {
            setStatus(form, text.network, 'error');
        } finally {
            if (!reloading) {
                delete form.dataset.busy;
                if (button) button.disabled = false;
            }
        }
    });
})();
</script>
