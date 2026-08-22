import './app.js';

// ── Helpers ─────────────────────────────────────────────────────────────────

function cfg() {
    return window.DISPUTE || {};
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

// ── Reply form ────────────────────────────────────────────────────────────────

function appendMessage(msg) {
    const thread = document.getElementById('dispute-thread');
    if (!thread) return;

    const row = document.createElement('div');
    row.className = 'flex justify-end msg-row';
    row.innerHTML = `
        <div class="max-w-[80%] sm:max-w-[75%]">
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
    const form = document.getElementById('form-dispute-reply');
    if (!form) return;

    const status = cfg().status;
    if (['resolved', 'closed'].includes(status)) return;

    const btn = document.getElementById('btn-dispute-reply');
    const textarea = document.getElementById('dispute-reply-message');

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
                toast(data.message || 'حدث خطأ أثناء إرسال الرد', false);
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

// ── Evidence upload ──────────────────────────────────────────────────────────

function appendEvidence(ev) {
    const list = document.getElementById('evidence-list');
    if (!list) return;

    // Remove "no evidence" placeholder if present
    const empty = list.querySelector('p.text-center');
    if (empty) empty.remove();

    const isImage = /\.(jpe?g|png)$/i.test(ev.file_url);
    const item = document.createElement('div');
    item.className = 'flex items-start gap-2 rounded-lg border border-gray-100 bg-gray-50 p-2';
    item.innerHTML = `
        <div class="flex-shrink-0 w-10 h-10 rounded overflow-hidden bg-gray-200 flex items-center justify-center">
            ${isImage
            ? `<img src="${ev.file_url}" alt="دليل" class="w-full h-full object-cover">`
            : `<svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`
        }
        </div>
        <div class="min-w-0 flex-1">
            ${ev.description ? `<p class="text-xs text-gray-700 truncate">${ev.description}</p>` : ''}
            <a href="${ev.file_url}" target="_blank" rel="noopener" class="text-xs text-primary-600 hover:underline">
                ${isImage ? 'عرض الصورة' : 'تحميل الملف'}
            </a>
            <p class="text-xs text-gray-400 mt-0.5">الآن</p>
        </div>
    `;
    list.appendChild(item);
}

function initEvidenceUpload() {
    const form = document.getElementById('form-evidence-upload');
    if (!form) return;

    const status = cfg().status;
    if (['resolved', 'closed'].includes(status)) return;

    const btn = document.getElementById('btn-upload-evidence');
    const fileInput = document.getElementById('evidence-file');
    const descInput = document.getElementById('evidence-description');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const file = fileInput.files[0];
        if (!file) {
            toast('يرجى اختيار ملف', false);
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('description', descInput.value.trim());

        setLoading(btn, true);
        try {
            const res = await fetch(cfg().evidenceUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg().csrf, 'Accept': 'application/json' },
                body: formData,
            });
            const data = await res.json();

            if (!res.ok) {
                const msg = data.message || Object.values(data.errors ?? {})[0]?.[0] || 'حدث خطأ أثناء رفع الملف';
                toast(msg, false);
                return;
            }

            toast(data.message);
            appendEvidence(data.evidence);
            form.reset();
        } catch {
            toast('تعذّر الاتصال بالخادم', false);
        } finally {
            setLoading(btn, false);
        }
    });
}

// ── Bootstrap ────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initReplyForm();
    initEvidenceUpload();

    // Auto-scroll thread to bottom on load
    const thread = document.getElementById('dispute-thread');
    if (thread) thread.scrollIntoView({ block: 'end' });
});
