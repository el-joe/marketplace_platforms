@extends('layouts.admin')

@section('title', $travelPackage->title_en)

@section('content')
<div class="p-6 space-y-6 max-w-5xl">

    {{-- ─── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.travel.packages.index') }}" class="text-xs text-gray-400 hover:text-gray-600 mb-1 inline-block">{{ __('admin.travel.back_to_packages_link') }}</a>
            <h1 class="text-2xl font-bold text-gray-900">{{ $travelPackage->title_en }}</h1>
            @if($travelPackage->title_ar)
                <p class="text-sm text-gray-400 mt-0.5" dir="rtl">{{ $travelPackage->title_ar }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if($travelPackage->status === \App\Enums\TravelPackageStatus::PendingReview)
                <button type="button" class="btn btn-success js-show-approve-btn">{{ __('admin.travel.approve_publish') }}</button>
                <button type="button" class="btn btn-danger js-show-reject-btn">{{ __('admin.travel.return_to_agency') }}</button>
            @elseif(in_array($travelPackage->status, [\App\Enums\TravelPackageStatus::Active, \App\Enums\TravelPackageStatus::SoldOut]))
                <button type="button" class="btn btn-secondary js-expire-btn"
                    data-url="{{ route('admin.travel.packages.expire', $travelPackage->id) }}">
                    {{ __('admin.travel.mark_expired') }}
                </button>
            @endif
        </div>
    </div>

    {{-- ─── Status + rejection note ─────────────────────────────────────────────── --}}
    @php
    $statusColors = [
        'draft'          => 'bg-gray-100 text-gray-600',
        'pending_review' => 'bg-amber-100 text-amber-700',
        'active'         => 'bg-emerald-100 text-emerald-700',
        'sold_out'       => 'bg-purple-100 text-purple-700',
        'expired'        => 'bg-gray-100 text-gray-500',
    ];
    @endphp
    <div class="flex items-center gap-3">
        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$travelPackage->status->value] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $travelPackage->status->label() }}
        </span>
        @if($travelPackage->approved_at && $travelPackage->approvedByAdmin)
            <span class="text-xs text-gray-400">
                {{ __('admin.travel.approved_by_at', ['name' => $travelPackage->approvedByAdmin->name, 'date' => $travelPackage->approved_at->format('d M Y H:i')]) }}
            </span>
        @endif
    </div>

    @if($travelPackage->rejection_reason)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <p class="font-medium mb-1">{{ __('admin.travel.previously_returned') }}</p>
        <p class="whitespace-pre-wrap">{{ $travelPackage->rejection_reason }}</p>
    </div>
    @endif

    {{-- ─── Main grid ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Trip Details --}}
        <x-card>
            <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.trip_details') }}</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.travel.destination_label') }}</dt>
                    <dd class="text-gray-900 text-end">
                        @if($travelPackage->destinationCountry)
                            {{ $travelPackage->destinationCountry->flag_emoji }} {{ $travelPackage->destinationCountry->name_en }}
                            @if($travelPackage->destinationCity)
                                <span class="text-gray-400">, {{ $travelPackage->destinationCity->name_en }}</span>
                            @endif
                        @else
                            {{ $travelPackage->destination_country }}{{ $travelPackage->destination_city ? ', ' . $travelPackage->destination_city : '' }}
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.departure') }}</dt><dd class="text-gray-900">{{ $travelPackage->departure_date->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.return') }}</dt><dd class="text-gray-900">{{ $travelPackage->return_date->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.duration') }}</dt><dd class="text-gray-900">{{ $travelPackage->duration_days }}d / {{ $travelPackage->duration_nights }}n</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('common.price') }}</dt><dd class="text-gray-900 font-semibold">{{ $travelPackage->priceFormatted() }}</dd></div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.travel.seats_fraction') }}</dt>
                    <dd class="text-gray-900">{{ $travelPackage->seats_booked }} / {{ $travelPackage->available_seats ?? '∞' }}</dd>
                </div>
                @if($fillPct !== null)
                <div>
                    <div class="flex justify-between text-xs text-gray-400 mb-0.5"><span>{{ __('admin.travel.fill_rate') }}</span><span>{{ $fillPct }}%</span></div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="{{ $fillPct >= 90 ? 'bg-red-500' : ($fillPct >= 70 ? 'bg-yellow-500' : 'bg-green-500') }} h-1.5 rounded-full" style="width:{{ $fillPct }}%"></div>
                    </div>
                </div>
                @endif
            </dl>
        </x-card>

        {{-- Agency Card --}}
        <x-card>
            <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.agency') }}</h3>
            @php $agency = $travelPackage->agency; @endphp
            @if($agency)
            <div class="flex items-center gap-3 mb-3">
                @if($agency->logo_path)
                    <img src="{{ Storage::url($agency->logo_path) }}" class="w-12 h-12 rounded-lg object-contain border border-gray-200" alt="">
                @else
                    <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 text-xs">{{ __('admin.logo') }}</div>
                @endif
                <div>
                    <p class="font-semibold text-gray-900">{{ $agency->name }}</p>
                    <p class="text-xs text-gray-500">{{ $agency->email }}</p>
                    @if($agency->phone)<p class="text-xs text-gray-500">{{ $agency->phone }}</p>@endif
                </div>
            </div>
            <dl class="space-y-1 text-sm">
                @if($agency->license_number)
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('admin.travel.license_number') }}</dt><dd class="text-gray-900 font-mono text-xs">{{ $agency->license_number }}</dd></div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('common.status') }}</dt>
                    <dd><span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $agency->status === \App\Enums\TravelAgencyStatus::Active ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-600' }}">{{ $agency->status->label() }}</span></dd>
                </div>
            </dl>
            <div class="mt-3">
                <a href="{{ route('admin.travel.agencies.show', $agency->id) }}" class="btn btn-secondary btn-sm">{{ __('admin.travel.view_agency') }}</a>
            </div>
            @else
                <p class="text-sm text-gray-400">{{ __('admin.travel.agency_not_found') }}</p>
            @endif
        </x-card>

    </div>

    {{-- ─── Booking Stats ────────────────────────────────────────────────────────── --}}
    <x-card>
        <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.booking_stats_title') }}</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $bookingStats['total'] }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.travel.total_bookings') }}</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-success-600">{{ $bookingStats['confirmed'] }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.travel.confirmed') }}</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-danger-600">{{ $bookingStats['cancelled'] }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.travel.cancelled') }}</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900">
                    {{ $travelPackage->currency }} {{ number_format($bookingStats['revenue'], 2) }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.travel.confirmed_revenue') }}</p>
            </div>
        </div>
    </x-card>

    {{-- ─── Inclusions ───────────────────────────────────────────────────────────── --}}
    @if($travelPackage->inclusions->isNotEmpty())
    <x-card>
        <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.inclusions') }}</h3>
        <ul class="grid grid-cols-2 gap-1 text-sm text-gray-700">
            @foreach($travelPackage->inclusions as $item)
            <li class="flex items-center gap-2"><span class="text-success-500 shrink-0">✓</span> {{ $item->icon }} {{ $item->name }}</li>
            @endforeach
        </ul>
    </x-card>
    @endif

    {{-- ─── Categories ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide">{{ __('admin.travel.categories_label') }}</h3>
            <button onclick="document.getElementById('cat-edit-panel').classList.toggle('hidden')"
                    class="text-xs text-primary-600 hover:underline">{{ __('admin.travel.edit_categories') }}</button>
        </div>

        {{-- Read-only badges --}}
        <div id="cat-badges" class="flex flex-wrap gap-2 min-h-[24px]">
            @forelse($travelPackage->categories as $cat)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-50 text-primary-700">
                {{ $cat->icon ? $cat->icon . ' ' : '' }}{{ $cat->name_en }}
            </span>
            @empty
            <span class="text-xs text-gray-400">{{ __('admin.travel.no_categories_assigned') }}</span>
            @endforelse
        </div>

        {{-- Edit panel (hidden by default) --}}
        <div id="cat-edit-panel" class="hidden mt-4 border-t border-gray-100 pt-4 space-y-3">
            @php
                $allCats      = \App\Models\TravelCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name_en')->get();
                $assignedIds  = $travelPackage->categories->pluck('id')->all();
                $parentCats   = $allCats->whereNull('parent_id');
                $childCats    = $allCats->whereNotNull('parent_id')->groupBy('parent_id');
            @endphp

            @if($allCats->isEmpty())
                <p class="text-sm text-gray-400">{{ __('admin.travel.no_categories_to_assign') }}</p>
            @else
                <div class="space-y-2">
                    @foreach($parentCats as $parent)
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-800">
                            <input type="checkbox" class="cat-checkbox w-4 h-4 rounded border-gray-300 text-primary-600"
                                   value="{{ $parent->id }}"
                                   {{ in_array($parent->id, $assignedIds) ? 'checked' : '' }}>
                            <span class="text-sm">{{ $parent->icon ? $parent->icon . ' ' : '' }}{{ $parent->name_en }}</span>
                        </label>
                        @if(isset($childCats[$parent->id]))
                        <div class="ms-6 mt-1 grid grid-cols-2 gap-1.5">
                            @foreach($childCats[$parent->id] as $child)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="cat-checkbox w-4 h-4 rounded border-gray-300 text-primary-600"
                                       value="{{ $child->id }}"
                                       {{ in_array($child->id, $assignedIds) ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ $child->icon ? $child->icon . ' ' : '' }}{{ $child->name_en }}</span>
                            </label>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button onclick="saveCats()" id="save-cats-btn"
                            class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 flex items-center gap-2">
                        <span id="save-cats-label">{{ __('common.save') }}</span>
                        <svg id="save-cats-spin" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </button>
                    <span id="save-cats-msg" class="text-xs text-emerald-600 hidden">{{ __('admin.travel.categories_updated') }}</span>
                </div>
            @endif
        </div>
    </x-card>

    {{-- ─── Descriptions ─────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-card dir="ltr">
            <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.description_en_title') }}</h3>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $travelPackage->description_en ?? '—' }}</p>
        </x-card>
        <x-card class="" style="direction:rtl" dir="rtl">
            <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.description_ar_title') }}</h3>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $travelPackage->description_ar ?? '—' }}</p>
        </x-card>
    </div>

    {{-- ─── Contract File ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-3">{{ __('admin.travel.package_contract') }}</h3>
        @if($travelPackage->contract_file_path)
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <svg class="w-8 h-8 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $travelPackage->contract_file_original_name }}</p>
                    @if($travelPackage->contract_uploaded_at)
                    <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.travel.uploaded_at', ['date' => $travelPackage->contract_uploaded_at->format('d M Y H:i')]) }}</p>
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.travel.packages.contract.download', $travelPackage->id) }}"
               class="btn btn-secondary btn-sm shrink-0">{{ __('admin.travel.download_pdf') }}</a>
        </div>
        @else
        <p class="text-sm text-amber-600 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ __('admin.travel.no_contract_file') }}
        </p>
        @endif
    </x-card>

    {{-- ─── Media Gallery ─────────────────────────────────────────────────────────── --}}
    @if($travelPackage->media->count())
    <x-card>
        <h3 class="font-semibold text-gray-700 text-xs uppercase tracking-wide mb-4">{{ __('admin.travel.media_files_count', ['count' => $travelPackage->media->count()]) }}</h3>
        <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
            @foreach($travelPackage->media as $m)
            @if($m->media_type === 'image')
                <a href="/storage/{{ $m->file_path }}" target="_blank">
                    <img src="/storage/{{ $m->file_path }}" class="rounded-lg h-28 w-full object-cover border border-gray-200 hover:opacity-90 transition-opacity" alt="">
                </a>
            @else
                <video src="/storage/{{ $m->file_path }}" controls class="rounded-lg h-28 w-full object-cover border border-gray-200"></video>
            @endif
            @endforeach
        </div>
    </x-card>
    @endif

</div>

{{-- ─── Approve Modal ────────────────────────────────────────────────────────── --}}
<x-modal id="approve-modal" title="{{ __('admin.travel.approve_package') }}" size="sm">
    <p class="text-sm text-gray-600">
        {!! str_replace(':name', '<strong>' . e($travelPackage->title_en) . '</strong>', __('admin.travel.confirm_publish_package')) !!}
    </p>
    <div class="flex justify-end gap-3 mt-5">
        <button type="button" class="btn btn-secondary" onclick="$('#approve-modal').modal('close')">{{ __('common.cancel') }}</button>
        <button type="button" id="confirm-approve-btn" class="btn btn-success">{{ __('admin.travel.approve_publish') }}</button>
    </div>
</x-modal>

{{-- ─── Reject Modal ─────────────────────────────────────────────────────────── --}}
<x-modal id="reject-modal" title="{{ __('admin.travel.return_to_agency') }}" size="md">
    <p class="text-sm text-gray-600 mb-3">
        {!! str_replace(':name', '<strong>' . e($travelPackage->title_en) . '</strong>', __('admin.travel.confirm_return_package')) !!}
    </p>
    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.reason') }} <span class="text-red-500">*</span></label>
    <textarea id="reject-reason-input" rows="4" class="form-input w-full text-sm"
        placeholder="{{ __('admin.travel.return_reason_hint') }}"></textarea>
    <p class="text-xs text-red-500 hidden mt-1" id="reject-reason-error">{{ __('admin.travel.reason_is_required') }}</p>
    <div class="flex justify-end gap-3 mt-5">
        <button type="button" class="btn btn-secondary" onclick="$('#reject-modal').modal('close')">{{ __('common.cancel') }}</button>
        <button type="button" id="confirm-reject-btn" class="btn btn-danger">{{ __('admin.travel.return_to_draft') }}</button>
    </div>
</x-modal>

@endsection

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    mark_expired_confirm: "{{ __('admin.travel.mark_expired_confirm') }}",
    failed_generic: "{{ __('admin.travel.failed_generic') }}",
    failed_to_approve: "{{ __('admin.travel.failed_to_approve') }}",
});

(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const approveUrl = '{{ route('admin.travel.packages.approve', $travelPackage->id) }}';
    const rejectUrl  = '{{ route('admin.travel.packages.reject', $travelPackage->id) }}';
    const expireUrl  = '{{ route('admin.travel.packages.expire', $travelPackage->id) }}';

    function postJson(url, data) {
        return $.ajax({ url, type: 'POST', data: JSON.stringify(data ?? {}), contentType: 'application/json', headers: { 'X-CSRF-TOKEN': csrfToken } });
    }

    document.querySelector('.js-show-approve-btn')?.addEventListener('click', () => $('#approve-modal').modal('open'));
    document.querySelector('.js-show-reject-btn')?.addEventListener('click', () => $('#reject-modal').modal('open'));

    document.querySelector('.js-expire-btn')?.addEventListener('click', function () {
        if (!confirm(window.TRANSLATIONS.mark_expired_confirm)) return;
        postJson(this.dataset.url).done(() => location.reload()).fail(xhr => alert(xhr.responseJSON?.message ?? window.TRANSLATIONS.failed_generic));
    });

    document.getElementById('confirm-approve-btn')?.addEventListener('click', () => {
        postJson(approveUrl).done(() => location.reload()).fail(xhr => {
            $('#approve-modal').modal('close');
            alert(xhr.responseJSON?.message ?? window.TRANSLATIONS.failed_to_approve);
        });
    });

    document.getElementById('confirm-reject-btn')?.addEventListener('click', () => {
        const reason = document.getElementById('reject-reason-input').value.trim();
        if (!reason) { document.getElementById('reject-reason-error').classList.remove('hidden'); return; }
        postJson(rejectUrl, { rejection_reason: reason }).done(() => location.reload()).fail(xhr => {
            alert(xhr.responseJSON?.message ?? window.TRANSLATIONS.failed_generic);
        });
    });
})();

async function saveCats() {
    const btn   = document.getElementById('save-cats-btn');
    const label = document.getElementById('save-cats-label');
    const spin  = document.getElementById('save-cats-spin');
    const msg   = document.getElementById('save-cats-msg');

    const ids = [...document.querySelectorAll('.cat-checkbox:checked')].map(el => el.value);

    btn.disabled = true;
    label.textContent = '{{ __("admin.travel.saving") }}';
    spin.classList.remove('hidden');
    msg.classList.add('hidden');

    const resp = await fetch('{{ route('admin.travel.packages.categories.sync', $travelPackage) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        },
        body: JSON.stringify({ category_ids: ids }),
    });

    const data = await resp.json();

    btn.disabled = false;
    label.textContent = '{{ __("common.save") }}';
    spin.classList.add('hidden');

    if (resp.ok) {
        const badgesEl = document.getElementById('cat-badges');
        if (data.categories.length === 0) {
            badgesEl.innerHTML = '<span class="text-xs text-gray-400">{{ __("admin.travel.no_categories_assigned") }}</span>';
        } else {
            badgesEl.innerHTML = data.categories.map(c =>
                `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-50 text-primary-700">${c.icon ? c.icon + ' ' : ''}${c.name_en}</span>`
            ).join('');
        }
        msg.classList.remove('hidden');
        setTimeout(() => msg.classList.add('hidden'), 3000);
    } else {
        alert(data.message ?? '{{ __("admin.travel.save_failed") }}');
    }
}
</script>
@endpush
