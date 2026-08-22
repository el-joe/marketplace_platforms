@extends('layouts.admin')

@section('title', $travelAgency->name)

@section('content')
<div class="p-6 space-y-6 max-w-5xl">

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-4">
            @if($travelAgency->logoUrl())
            <img src="{{ $travelAgency->logoUrl() }}" class="w-16 h-16 rounded-xl object-cover border border-gray-200">
            @else
            <div class="w-16 h-16 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 text-2xl font-bold">
                {{ mb_substr($travelAgency->name, 0, 1) }}
            </div>
            @endif
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $travelAgency->name }}</h1>
                <p class="text-sm text-gray-500">{{ $travelAgency->email }}</p>
            </div>
        </div>

        <div class="flex gap-2">
            @if($travelAgency->status === \App\Enums\TravelAgencyStatus::Pending)
            <button onclick="approveAgency()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">{{ __('admin.travel.approve') }}</button>
            <button onclick="rejectAgency()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium">{{ __('admin.classifieds.reject') }}</button>
            @elseif($travelAgency->status === \App\Enums\TravelAgencyStatus::Active)
            <button onclick="suspendAgency()" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium">{{ __('admin.travel.suspend') }}</button>
            @elseif($travelAgency->status === \App\Enums\TravelAgencyStatus::Suspended)
            <button onclick="reactivateAgency()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">{{ __('admin.travel.reactivate') }}</button>
            @endif
            <a href="{{ route('admin.travel.agencies.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('admin.travel.back') }}</a>
        </div>
    </div>

    {{-- Status Badge --}}
    @php
    $statusColors = ['pending' => 'bg-amber-100 text-amber-700', 'active' => 'bg-emerald-100 text-emerald-700', 'suspended' => 'bg-red-100 text-red-700'];
    @endphp
    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$travelAgency->status->value] ?? '' }}">
        {{ $travelAgency->status->label() }}
    </span>

    {{-- Details Grid --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">{{ __('admin.travel.agency_info') }}</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.phone') }}</dt><dd class="text-gray-900">{{ $travelAgency->phone ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.license_number') }}</dt><dd class="text-gray-900">{{ $travelAgency->license_number ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('common.country') }}</dt><dd class="text-gray-900">{{ $travelAgency->country?->name_en }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.registered') }}</dt><dd class="text-gray-900">{{ $travelAgency->created_at->format('d M Y') }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">{{ __('admin.travel.approval') }}</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.approved_by') }}</dt><dd class="text-gray-900">{{ $travelAgency->approvedByAdmin?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.approved_at') }}</dt><dd class="text-gray-900">{{ $travelAgency->approved_at?->format('d M Y H:i') ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    {{-- Packages --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">{{ __('admin.travel.packages') }} ({{ $travelAgency->packages->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.package_name') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.destination') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.departure_date') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.price') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.status') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($travelAgency->packages as $pkg)
                    @php
                    $pkgColors = ['draft'=>'bg-gray-100 text-gray-600','pending_review'=>'bg-amber-100 text-amber-700','active'=>'bg-emerald-100 text-emerald-700','sold_out'=>'bg-purple-100 text-purple-700','expired'=>'bg-gray-100 text-gray-500'];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $pkg->title_en }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $pkg->destination_country }}{{ $pkg->destination_city ? ', '.$pkg->destination_city : '' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $pkg->departure_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $pkg->priceFormatted() }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $pkgColors[$pkg->status->value] ?? '' }}">
                                {{ $pkg->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.travel.packages.show', $pkg) }}" class="text-primary-600 text-xs hover:underline">{{ __('admin.travel.review') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">{{ __('admin.travel.no_packages_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    approve_agency_confirm: "{{ __('admin.travel.approve_agency_confirm') }}",
    error_approving_agency: "{{ __('admin.travel.error_approving_agency') }}",
    rejection_reason_prompt: "{{ __('admin.travel.rejection_reason_prompt') }}",
    error_rejecting_agency: "{{ __('admin.travel.error_rejecting_agency') }}",
    suspension_reason_prompt: "{{ __('admin.travel.suspension_reason_prompt') }}",
    error_suspending_agency: "{{ __('admin.travel.error_suspending_agency') }}",
    reactivate_agency_confirm: "{{ __('admin.travel.reactivate_agency_confirm') }}",
    error_reactivating_agency: "{{ __('admin.travel.error_reactivating_agency') }}",
});

const agencyId = '{{ $travelAgency->id }}';

async function approveAgency() {
    if (!confirm(window.TRANSLATIONS.approve_agency_confirm)) return;
    const res = await fetch(`/travel/agencies/${agencyId}/approve`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
    if (res.ok) location.reload();
    else alert(window.TRANSLATIONS.error_approving_agency);
}

async function rejectAgency() {
    const reason = prompt(window.TRANSLATIONS.rejection_reason_prompt);
    if (!reason) return;
    const res = await fetch(`/travel/agencies/${agencyId}/reject`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ reason }),
    });
    if (res.ok) location.href = '{{ route('admin.travel.agencies.index') }}';
    else alert(window.TRANSLATIONS.error_rejecting_agency);
}

async function suspendAgency() {
    const reason = prompt(window.TRANSLATIONS.suspension_reason_prompt);
    if (!reason) return;
    const res = await fetch(`/travel/agencies/${agencyId}/suspend`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ reason }),
    });
    if (res.ok) location.reload();
    else alert(window.TRANSLATIONS.error_suspending_agency);
}

async function reactivateAgency() {
    if (!confirm(window.TRANSLATIONS.reactivate_agency_confirm)) return;
    const res = await fetch(`/travel/agencies/${agencyId}/reactivate`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
    if (res.ok) location.reload();
    else alert(window.TRANSLATIONS.error_reactivating_agency);
}
</script>
@endpush
@endsection
