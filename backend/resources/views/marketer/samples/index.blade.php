@extends('layouts.marketer')
@section('title', __('marketer.samples.title'))
@section('page-title', __('marketer.samples.page_title'))

@section('content')
<div class="space-y-4">

    {{-- Status summary --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-yellow-700">{{ $statusCounts['pending'] }}</div>
            <div class="text-xs text-yellow-600 mt-0.5">{{ __('marketer.samples.pending_label') }}</div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-blue-700">{{ $statusCounts['dispatched'] }}</div>
            <div class="text-xs text-blue-600 mt-0.5">{{ __('marketer.samples.dispatched_label') }}</div>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-green-700">{{ $statusCounts['delivered'] }}</div>
            <div class="text-xs text-green-600 mt-0.5">{{ __('marketer.samples.delivered_label') }}</div>
        </div>
    </div>

    @if($samples->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">📦</div>
            <h3 class="font-bold text-gray-700">{{ __('marketer.samples.no_samples') }}</h3>
            <p class="text-gray-400 text-sm mt-1">{{ __('marketer.samples.no_samples_hint') }}</p>
        </div>
    @else
        @foreach($samples as $sample)
        @php
            $product = $sample->campaign->vendorListing?->productVariant?->product
                     ?? $sample->campaign->adminListing?->productVariant?->product;
            $statusMap = [
                'pending'    => ['label' => __('marketer.samples.status_pending'),    'cls' => 'bg-yellow-100 text-yellow-700'],
                'dispatched' => ['label' => __('marketer.samples.status_dispatched'), 'cls' => 'bg-blue-100 text-blue-700'],
                'delivered'  => ['label' => __('marketer.samples.status_delivered'),  'cls' => 'bg-green-100 text-green-700'],
                'returned'   => ['label' => __('marketer.samples.status_returned'),   'cls' => 'bg-red-100 text-red-700'],
            ];
            $st = $statusMap[$sample->status] ?? ['label' => $sample->status, 'cls' => 'bg-gray-100 text-gray-500'];
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h4 class="font-bold text-gray-900">{{ $product?->name_ar ?? __('marketer.invitations.campaign_default_label') }}</h4>
                    <div class="text-sm text-gray-500 mt-0.5">{{ __('marketer.samples.quantity_label') }} {{ $sample->quantity }} {{ __('marketer.samples.pieces_unit') }}</div>
                </div>
                <span class="px-2 py-0.5 text-xs font-semibold rounded {{ $st['cls'] }}">{{ $st['label'] }}</span>
            </div>

            @if($sample->status === 'pending')
                @if($sample->delivery_address_snapshot)
                <div class="p-3 bg-green-50 rounded-lg text-xs text-green-700 mb-3">
                    {{ __('marketer.samples.address_registered') }}
                </div>
                @else
                <form method="POST" action="{{ route('marketer.samples.address', $sample) }}" class="space-y-3">
                    @csrf
                    <p class="text-sm text-amber-700 font-semibold">{{ __('marketer.samples.address_required_warning') }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="address_line_1" placeholder="{{ __('marketer.samples.address_line_1_placeholder') }}" required class="border rounded-lg px-3 py-2 text-sm col-span-2">
                        <input type="text" name="city" placeholder="{{ __('marketer.samples.city_placeholder') }}" required class="border rounded-lg px-3 py-2 text-sm">
                        <input type="text" name="country" placeholder="{{ __('marketer.samples.country_placeholder') }}" required class="border rounded-lg px-3 py-2 text-sm">
                        <input type="text" name="phone" placeholder="{{ __('marketer.samples.phone_placeholder') }}" required class="border rounded-lg px-3 py-2 text-sm col-span-2">
                        <textarea name="notes" placeholder="{{ __('marketer.samples.notes_placeholder') }}" rows="2" class="border rounded-lg px-3 py-2 text-sm col-span-2"></textarea>
                    </div>
                    <button type="submit" class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500">
                        {{ __('marketer.samples.save_address_button') }}
                    </button>
                </form>
                @endif
            @elseif($sample->delivery_address_snapshot)
            <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-600">
                <strong>{{ __('marketer.samples.delivery_address_label') }}</strong>
                {{ $sample->delivery_address_snapshot['address_line_1'] }},
                {{ $sample->delivery_address_snapshot['city'] }},
                {{ $sample->delivery_address_snapshot['country'] }}
            </div>
            @endif

            @if($sample->status === 'dispatched')
            <form method="POST" action="{{ route('marketer.samples.received', $sample) }}" class="mt-3">
                @csrf
                <button type="submit" class="px-5 py-2 bg-green-600 text-white font-bold rounded-lg text-sm hover:bg-green-700">
                    {{ __('marketer.samples.confirm_received_button') }}
                </button>
            </form>
            @endif

            @if($sample->dispatched_at)
            <div class="text-xs text-gray-400 mt-2">{{ __('marketer.samples.dispatch_date_label') }} {{ $sample->dispatched_at->format('Y-m-d') }}</div>
            @endif
        </div>
        @endforeach

        <div>{{ $samples->links() }}</div>
    @endif
</div>
@endsection
