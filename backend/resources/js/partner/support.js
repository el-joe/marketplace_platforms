import './app.js';

// ── Helpers ─────────────────────────────────────────────────────────────────

function cfg() {
    return window.SUPPORT || window.SUPPORT_TICKET || {};
}

function toast(text, ok = true) {
    window.Toastify?.({
        text,
        duration: 3000,
        gravity: 'top',
        position: 'right',
        backgroundColor: ok ? '#16a34a' : '#dc2626',
    }).showToast();
}

function setLoading(btn, loading) {
    btn.disabled = loading;
    btn.dataset.orig = btn.dataset.orig || btn.innerHTML;
    btn.innerHTML = loading
        ? '<svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>'
        : btn.dataset.orig;
}

// ── Create ticket form ───────────────────────────────────────────────────────

function initCreateForm() {
    const form = document.getElementById('form-create-ticket');
    if (!form) return;

    const btn = document.getElementById('btn-create-ticket');
    const error = document.getElementById('ticket-error');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.classList.add('hidden');

        const body = new URLSearchParams();
        body.set('subject', form.querySelector('[name=subject]').value.trim());
        body.set('category', form.querySelector('[name=category]').value);
        body.set('description', form.querySelector('[name=description]').value.trim());
        body.set('priority', form.querySelector('[name=priority]').value);

        if (!body.get('subject') || !body.get('category') || !body.get('description')) {
            error.textContent = 'يرجى تعبئة جميع الحقول المطلوبة';
            error.classList.remove('hidden');
            return;
        }

        setLoading(btn, true);
        try {
            const res = await fetch(cfg().storeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': cfg().csrf, 'Accept': 'application/json' },
                body,
            });
            const data = await res.json();

            if (!res.ok) {
                const msg = data.message || Object.values(data.errors ?? {})[0]?.[0] || 'حدث خطأ، يرجى المحاولة مجدداً';
                error.textContent = msg;
                error.classList.remove('hidden');
                return;
            }

            toast(data.message);
            window.location.href = data.redirect;
        } catch {
            error.textContent = 'تعذّر الاتصال بالخادم، يرجى المحاولة مجدداً';
            error.classList.remove('hidden');
        } finally {
            setLoading(btn, false);
        }
    });
}

// ── Reply form (ticket show page) ────────────────────────────────────────────

function appendMessage(msg) {
    const thread = document.getElementById('thread');
    if (!thread) return;

    const row = document.createElement('div');
    row.className = 'flex justify-end msg-row';
    row.innerHTML = `
        <div class="max-w-[80%] sm:max-w-[70%]">
            <p class="mb-1 text-xs text-end text-gray-400">أنت · الآن</p>
            <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm bg-primary-600 text-white rounded-tl-md">
                ${msg.message.replace(/\n/g, '<br>')}
            </div>
        </div>
    `;
    thread.appendChild(row);
    thread.scrollIntoView({ block: 'end', behavior: 'smooth' });
}

function initReplyForm() {
    const form = document.getElementById('form-reply');
    if (!form) return;

    const status = cfg().status;
    if (['resolved', 'closed'].includes(status)) return;

    const btn = document.getElementById('btn-reply');
    const textarea = document.getElementById('reply-message');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = textarea.value.trim();
        if (!message) return;

        setLoading(btn, true);
        try {
            const res = await fetch(cfg().replyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': cfg().csrf, 'Accept': 'application/json' },
                body: new URLSearchParams({ message }),
            });
            const data = await res.json();

            if (!res.ok) {
                toast(data.message || 'حدث خطأ أثناء الإرسال', false);
                return;
            }

            toast(data.message);
            appendMessage(data.msg);
            textarea.value = '';
        } catch {
            toast('تعذّر الاتصال بالخادم', false);
        } finally {
            setLoading(btn, false);
        }
    });
}

// ── Bootstrap ────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initCreateForm();
    initReplyForm();

    // Auto-scroll thread to bottom on load
    const thread = document.getElementById('thread');
    if (thread) thread.scrollIntoView({ block: 'end' });
});
