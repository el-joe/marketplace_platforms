@extends('layouts.admin')

@section('title', 'Ad Subscriptions')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Ad Subscriptions</h1>
        <p class="text-sm text-gray-500 mt-0.5">Vendor listings currently boosted or previously subscribed.</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
                    <th class="px-4 py-3">Vendor</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Package</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Ends</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $sub)
                    <tr class="border-b border-gray-50">
                        <td class="px-4 py-3">{{ $sub->vendor?->name ?? $sub->vendor_id }}</td>
                        <td class="px-4 py-3">{{ $sub->vendorListing?->productVariant?->product?->name_en ?? $sub->vendor_listing_id }}</td>
                        <td class="px-4 py-3">{{ $sub->adPackage?->name_en }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-semibold rounded-full px-2 py-0.5
                                {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : ($sub->status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500') }}">
                                {{ $sub->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $sub->ends_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ number_format($sub->amount_paid) }} {{ $sub->currency }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($sub->status === 'active')
                                <button type="button" class="btn btn-xs btn-danger btn-cancel-sub" data-id="{{ $sub->id }}">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No ad subscriptions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $subscriptions->links() }}</div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tok = '{{ csrf_token() }}';
            $(document).on('click', '.btn-cancel-sub', function() {
                const id = $(this).data('id');
                fetch('/ad-subscriptions/' + id + '/cancel', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: '{}',
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        window.Toast.success(data.message);
                        location.reload();
                    } else {
                        window.Toast.error(data.message);
                    }
                });
            });
        });
    </script>
@endpush
