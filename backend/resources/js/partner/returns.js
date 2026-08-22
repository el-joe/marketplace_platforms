import './app.js';

function cfg() {
    return window.RETURN_CONFIG || {};
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

function appendMessage(msg) {
    const thread = document.getElementById('thread');
    if (!thread) return;

    document.getElementById('empty-thread')?.remove();

    const escaped = msg.message.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
    const row = document.createElement('div');
    row.className = 'flex justify-end';
    row.innerHTML = `
        <div class="max-w-[85%]">
            <p class="text-xs mb-1 text-end text-gray-400">أنت · الآن</p>
            <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm bg-primary-600 text-white rounded-tl-md">
                ${escaped}
            </div>
        </div>
    `;
    thread.appendChild(row);
    thread.scrollTop = thread.scrollHeight;
}

function initMessageForm() {
    const form = document.getElementById('form-message');
    if (!form) return;

    const btn        = document.getElementById('btn-send');
    const textarea   = document.getElementById('msg-text');
    const fileInput  = document.getElementById('msg-attachments');
    const statusEl   = document.getElementById('msg-status');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const message = textarea.value.trim();
        if (!message) {
            statusEl.textContent = 'الرجاء كتابة رسالة.';
            return;
        }

        const formData = new FormData();
        formData.append('message', message);

        const files = fileInput?.files ?? [];
        if (files.length > 5) {
            statusEl.textContent = 'الحد الأقصى 5 مرفقات.';
            return;
        }
        for (const file of files) {
            formData.append('attachments[]', file);
        }

        statusEl.textContent = '';
        setLoading(btn, true);

        try {
            const res = await fetch(cfg().messagesUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': cfg().csrf,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await res.json();

            if (!res.ok) {
                const errMsg = data.message
                    || Object.values(data.errors ?? {})[0]?.[0]
                    || 'حدث خطأ، يرجى المحاولة مجدداً';
                statusEl.textContent = errMsg;
                toast(errMsg, false);
                return;
            }

            toast(data.message || 'تم إرسال الرسالة.');
            appendMessage(data.data);
            textarea.value = '';
            if (fileInput) fileInput.value = '';
        } catch {
            toast('تعذّر الاتصال بالخادم', false);
        } finally {
            setLoading(btn, false);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initMessageForm();

    const thread = document.getElementById('thread');
    if (thread) thread.scrollTop = thread.scrollHeight;
});
