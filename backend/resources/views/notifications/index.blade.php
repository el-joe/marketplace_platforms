@extends($layout)

@section('title', __('common.notifications_page.title'))
@section('page-title', __('common.notifications_page.title'))

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-900">{{ __('common.notifications_page.all_notifications') }}</h1>
            @if($notifications->isNotEmpty())
                <button id="mark-all-read-btn"
                    class="text-sm text-primary-600 hover:underline">{{ __('common.notifications_page.mark_all_read') }}</button>
            @endif
        </div>

        @if($notifications->isEmpty())
            <div class="px-6 py-16 text-center text-gray-500 text-sm">
                {{ __('common.no_notifications_yet') }}
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($notifications as $n)
                    <li class="notification-row {{ $n->read ? 'bg-white' : 'bg-blue-50' }}"
                        data-id="{{ $n->id }}">
                        <a href="{{ $n->url }}"
                            class="block px-6 py-4 hover:bg-gray-50 transition-colors mark-read-link">
                            <div class="flex items-start gap-3">
                                @unless($n->read)
                                    <span class="mt-1.5 w-2 h-2 rounded-full bg-primary-500 shrink-0"></span>
                                @else
                                    <span class="mt-1.5 w-2 h-2 rounded-full bg-transparent shrink-0"></span>
                                @endunless
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $n->title }}</p>
                                    @if($n->message)
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $n->message }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400 mt-1">{{ $n->created_at }}</p>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="px-6 py-4 border-t border-gray-100">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Mark individual notification read when clicking its link
    document.querySelectorAll('.mark-read-link').forEach(function (link) {
        link.addEventListener('click', function () {
            const row = this.closest('.notification-row');
            const id = row?.dataset.id;
            if (!id) return;
            fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
        });
    });

    // Mark all read button
    const btn = document.getElementById('mark-all-read-btn');
    if (btn) {
        btn.addEventListener('click', function () {
            fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            }).then(() => {
                document.querySelectorAll('.notification-row').forEach(function (row) {
                    row.classList.remove('bg-blue-50');
                    row.classList.add('bg-white');
                    const dot = row.querySelector('.bg-primary-500');
                    if (dot) dot.classList.replace('bg-primary-500', 'bg-transparent');
                });
            });
        });
    }
});
</script>
@endpush
