@extends('layouts.marketer')
@section('title', __('marketer.conversations'))
@section('page-title', __('marketer.conversations'))

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 divide-y">
        @forelse($conversations as $c)
        <a href="{{ route('marketer.conversations.show', $c->id) }}" data-conv="{{ $c->id }}"
           class="block p-3 hover:bg-gray-50 {{ $active?->id === $c->id ? 'bg-gray-50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="font-medium text-sm">
                    @if($c->marketer_has_unread)<span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>@endif
                    {{ $c->customer?->name }}
                </span>
                <span class="text-xs text-gray-400">{{ $c->last_message_at?->diffForHumans() }}</span>
            </div>
            <p class="text-xs text-gray-500 truncate" data-preview>{{ $c->latestMessage?->body }}</p>
        </a>
        @empty
        <p class="p-4 text-sm text-gray-500">{{ __('marketer.no_conversations') }}</p>
        @endforelse
    </div>

    <div class="md:col-span-2 bg-white rounded-xl border border-gray-200 flex flex-col min-h-[400px]">
        @if($active)
        <div class="p-3 border-b text-sm font-semibold">{{ $active->customer?->name }}
            @if($active->classifiedListing)<span class="text-gray-400 font-normal">- {{ $active->classifiedListing->title }}</span>@endif
        </div>
        <div id="chat-messages" class="flex-1 p-4 space-y-2 overflow-y-auto">
            @foreach($messages as $m)
            <div class="flex {{ $m->sender_type === 'marketer' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[75%] px-3 py-2 rounded-2xl text-sm {{ $m->sender_type === 'marketer' ? 'bg-teal-600 text-white' : 'bg-gray-200 text-gray-800' }}">
                    {{ $m->body }}
                    <div class="text-[10px] opacity-70 mt-1">{{ $m->created_at->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            @endforeach
        </div>
        <form id="chat-form" method="POST" action="{{ route('marketer.conversations.message', $active->id) }}" class="p-3 border-t flex gap-2">@csrf
            <input name="body" required autocomplete="off" maxlength="5000" placeholder="{{ __('marketer.type_message') }}" class="flex-1 border rounded-lg px-3 py-2 text-sm">
            <button class="px-4 py-2 bg-teal-600 text-white rounded-lg text-sm">{{ __('marketer.send') }}</button>
        </form>
        @else
        <p class="p-6 text-sm text-gray-500">{{ __('marketer.conversations') }}</p>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const marketerId = @json((string) auth('marketer')->user()->marketer_id);
    const activeId = @json($active?->id);
    const box = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const seen = new Set();

    function bubble(m) {
        if (!box || seen.has(m.id)) return;
        seen.add(m.id);
        const mine = m.sender_type === 'marketer';
        const row = document.createElement('div');
        row.className = 'flex ' + (mine ? 'justify-end' : 'justify-start');
        const b = document.createElement('div');
        b.className = 'max-w-[75%] px-3 py-2 rounded-2xl text-sm ' + (mine ? 'bg-teal-600 text-white' : 'bg-gray-200 text-gray-800');
        b.textContent = m.body;
        const t = document.createElement('div');
        t.className = 'text-[10px] opacity-70 mt-1';
        t.textContent = new Date(m.created_at).toLocaleString();
        b.appendChild(t);
        row.appendChild(b);
        box.appendChild(row);
        box.scrollTop = box.scrollHeight;
    }

    let audioCtx = null;
    function playPop() {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            const now = audioCtx.currentTime;
            [[660, 0], [990, 0.09]].forEach(([freq, start]) => {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.0001, now + start);
                gain.gain.exponentialRampToValueAtTime(0.2, now + start + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + start + 0.18);
                osc.connect(gain).connect(audioCtx.destination);
                osc.start(now + start);
                osc.stop(now + start + 0.2);
            });
        } catch (e) {}
    }

    function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

    if (box) { box.scrollTop = box.scrollHeight; }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = form.elements.body;
            const body = input.value.trim();
            if (!body) return;
            input.value = '';
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(), 'X-Socket-ID': window.Echo?.socketId?.() ?? '',
                    },
                    body: JSON.stringify({ body }),
                });
                if (!res.ok) throw new Error();
                const json = await res.json();
                bubble(json.data);
                updateRow(activeId, json.data.body, false);
            } catch (err) {
                input.value = body;
            }
        });
    }

    function updateRow(convId, preview, unread) {
        const row = document.querySelector('[data-conv="' + convId + '"]');
        if (!row) { if (unread) window.location.reload(); return; }
        row.querySelector('[data-preview]').textContent = preview;
        if (unread && convId !== activeId && !row.querySelector('.bg-red-500')) {
            const dot = document.createElement('span');
            dot.className = 'inline-block w-2 h-2 rounded-full bg-red-500';
            row.querySelector('span.font-medium').prepend(dot);
        }
        row.parentElement.prepend(row);
    }

    window.Echo?.private('marketer-chat.' + marketerId).listen('.message.sent', (e) => {
        const m = e.message;
        if (m.sender_type !== 'customer') return;
        playPop();
        if (e.conversation_id === activeId) {
            bubble(m);
            fetch(@json($active ? route('marketer.conversations.show', $active->id) : ''), { headers: { 'Accept': 'text/html' } }).catch(() => {});
        }
        updateRow(e.conversation_id, m.body, true);
    });
})();
</script>
@endpush
