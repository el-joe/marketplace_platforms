@extends('layouts.marketer')
@section('title', $ticket->ticket_number)
@section('page-title', 'تذكرة #' . $ticket->ticket_number)

@section('content')
@php
    $closed = in_array($ticket->status->value, ['resolved', 'closed'], true);
@endphp
<div class="max-w-2xl space-y-5">
    <div class="bg-white rounded-xl border p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">{{ $ticket->subject }}</h2>
            @unless($closed)
                <form method="POST" action="{{ route('marketer.support.close', $ticket->ticket_number) }}"
                      onsubmit="return confirm('هل تريد إغلاق هذه التذكرة؟');">
                    @csrf
                    <button class="text-xs text-gray-500 hover:text-red-600">إغلاق التذكرة</button>
                </form>
            @endunless
        </div>
        <p class="text-xs text-gray-400 mt-1">{{ str_replace('_', ' ', $ticket->category) }} · {{ $ticket->created_at->format('d M Y H:i') }}</p>
    </div>

    <div class="bg-white rounded-xl border divide-y" id="messages-list">
        @foreach($messages as $msg)
            <div class="p-4">
                <p class="text-sm text-gray-800">{{ $msg->message }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $msg->created_at->format('d M Y H:i') }}</p>
            </div>
        @endforeach
    </div>

    @unless($closed)
    <div class="bg-white rounded-xl border p-5">
        <form id="form-reply">
            @csrf
            <textarea name="message" rows="3" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="اكتب ردك..."></textarea>
            <div class="mt-3 flex justify-end">
                <button type="submit" class="px-5 py-2 bg-yellow-500 text-gray-900 text-sm font-semibold rounded-lg">إرسال الرد</button>
            </div>
        </form>
    </div>
    @endunless
</div>

@push('scripts')
<script>
document.getElementById('form-reply')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('{{ route('marketer.support.reply', $ticket->ticket_number) }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
        body: formData,
    })
        .then(async (res) => {
            const data = await res.json();
            if (!res.ok) throw data;
            const list = document.getElementById('messages-list');
            const div = document.createElement('div');
            div.className = 'p-4';
            div.innerHTML = `<p class="text-sm text-gray-800"></p><p class="text-xs text-gray-400 mt-1"></p>`;
            div.querySelector('p').textContent = data.msg.message;
            div.querySelectorAll('p')[1].textContent = new Date(data.msg.created_at).toLocaleString();
            list.appendChild(div);
            this.reset();
        })
        .catch((err) => alert(err?.message || 'حدث خطأ ما'));
});
</script>
@endpush
@endsection
