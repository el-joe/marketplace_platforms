@extends('layouts.marketer')
@section('title', 'ترويجاتي')
@section('page-title', 'ترويجاتي')

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@push('scripts')
    @vite('resources/js/marketer/promote-bookings.js')
    <script>
        window.AD_BOOKINGS_CONFIG = {
            datatableUrl: "{{ route('marketer.promote.bookings.datatable') }}",
        };
    </script>
@endpush

@section('content')
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex items-center gap-2 flex-wrap">
        @php
            $tabs = [
                '' => 'الكل',
                'draft' => __('ads.booking_status.draft'),
                'pending_review' => __('ads.booking_status.pending_review'),
                'approved' => __('ads.booking_status.approved'),
                'scheduled' => __('ads.booking_status.scheduled'),
                'active' => __('ads.booking_status.active'),
                'completed' => __('ads.booking_status.completed'),
                'rejected' => __('ads.booking_status.rejected'),
                'cancelled' => __('ads.booking_status.cancelled'),
                'expired' => __('ads.booking_status.expired'),
            ];
        @endphp
        @foreach ($tabs as $value => $label)
            <button class="ad-booking-tab px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 {{ $value === '' ? 'bg-primary-50 border-primary-300 text-primary-700' : '' }}"
                data-status="{{ $value }}">
                {{ $label }} @if(isset($counts[$value]) && $value !== '') <span class="text-gray-400">({{ $counts[$value] }})</span> @endif
            </button>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table id="ad-bookings-table" class="w-full text-sm" style="width:100%">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <th class="px-4 py-3 text-left font-semibold">المرجع</th>
                    <th class="px-4 py-3 text-left font-semibold">المكان الإعلاني</th>
                    <th class="px-4 py-3 text-left font-semibold">التواريخ</th>
                    <th class="px-4 py-3 text-left font-semibold">المبلغ</th>
                    <th class="px-4 py-3 text-left font-semibold">الدفع</th>
                    <th class="px-4 py-3 text-left font-semibold">الحالة</th>
                    <th class="px-4 py-3 text-left font-semibold">حالة التصميم</th>
                    <th class="px-4 py-3 text-left font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"></tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
            <span id="ad-bookings-table-info" class="text-xs text-gray-400"></span>
            <div id="ad-bookings-table-pagination" class="flex items-center gap-1"></div>
        </div>
    </div>
@endsection
