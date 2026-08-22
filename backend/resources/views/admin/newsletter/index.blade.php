@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@section('title', __('admin.nav.newsletter'))

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.nav.newsletter') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage email subscribers from the platform newsletter blocks.</p>
        </div>
        <a href="{{ route('admin.newsletter.export') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ number_format($totalActive) }}</p>
            <p class="text-sm text-gray-500 mt-1">Active Subscribers</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ number_format($last30Days) }}</p>
            <p class="text-sm text-gray-500 mt-1">New (Last 30 Days)</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ number_format($totalUnsubscribed) }}</p>
            <p class="text-sm text-gray-500 mt-1">Unsubscribed</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex items-center gap-3">
        <select id="filter-status" class="rounded-lg border border-gray-300 text-sm px-3 py-2">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="unsubscribed">Unsubscribed</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="newsletter-table" class="w-full text-sm text-gray-700" style="width:100%">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Country</th>
                        <th class="px-4 py-3 text-left">Source</th>
                        <th class="px-4 py-3 text-left">Locale</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Subscribed At</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = $('#newsletter-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('admin.newsletter.datatable') }}',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: (d) => {
                d.status = $('#filter-status').val();
            },
        },
        columns: [
            { data: 'email' },
            { data: 'country', render: (v) => v ? `<span class="uppercase font-medium">${v}</span>` : '—' },
            { data: 'source' },
            { data: 'locale' },
            { data: 'status', render: (v) => v === 'active'
                ? '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-xs">Active</span>'
                : '<span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-xs">Unsubscribed</span>'
            },
            { data: 'subscribed_at' },
            { data: 'id', orderable: false, render: (id) =>
                `<button type="button" class="delete-subscriber text-rose-500 hover:text-rose-700 text-xs" data-id="${id}">Delete</button>`
            },
        ],
        order: [[5, 'desc']],
        pageLength: 25,
        language: { search: 'Search email:' },
    });

    $('#filter-status').on('change', () => table.ajax.reload());

    $('#newsletter-table tbody').on('click', '.delete-subscriber', function () {
        const id = $(this).data('id');
        if (!confirm('Delete this subscriber?')) return;
        fetch(`/admin/newsletter/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).then(() => table.ajax.reload());
    });
});
</script>
@endpush
