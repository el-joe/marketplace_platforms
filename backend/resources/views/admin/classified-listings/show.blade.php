@extends('layouts.admin')

@section('title', __('admin.classifieds.listing_number_title', ['number' => $listing->listing_number]))

@section('content')
<div class="p-6 space-y-6 max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.classifieds.listings.index') }}" class="text-gray-400 hover:text-gray-600">
                    <x-heroicon name="arrow-left" class="w-5 h-5" />
                </a>
                <h1 class="text-xl font-bold text-gray-900">{{ $listing->listing_number }}</h1>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $listing->status === \App\Enums\ClassifiedListingStatus::Active ? 'bg-emerald-100 text-emerald-700'
                        : ($listing->status === \App\Enums\ClassifiedListingStatus::PendingReview ? 'bg-amber-100 text-amber-700'
                        : ($listing->status === \App\Enums\ClassifiedListingStatus::Rejected ? 'bg-red-100 text-red-700'
                        : 'bg-gray-100 text-gray-700')) }}">
                    {{ __('admin.classifieds.status_' . $listing->status->value) }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                {{ __('admin.classifieds.by') }} {{ $listing->seller?->name ?? $listing->seller?->store_name }}
                @if($listing->is_vendor_listing)
                    <span class="inline-flex items-center rounded-full bg-violet-50 px-1.5 py-0.5 text-[10px] font-medium text-violet-700">{{ __('admin.classifieds.vendor') }}</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium text-sky-700">{{ __('admin.classifieds.customer') }}</span>
                @endif
                · {{ $listing->created_at->format('Y-m-d H:i') }}
            </p>
        </div>

        @if($listing->status === \App\Enums\ClassifiedListingStatus::PendingReview)
        <div class="flex gap-2">
            <button type="button"
                    onclick="document.getElementById('approve-modal').classList.remove('hidden')"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                {{ __('admin.classifieds.approve') }}
            </button>
            <button type="button"
                    onclick="document.getElementById('reject-modal').classList.remove('hidden')"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                {{ __('admin.classifieds.reject') }}
            </button>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-3 gap-6">

        {{-- Left: Details --}}
        <div class="col-span-2 space-y-4">

            {{-- Core info --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h2 class="font-semibold text-gray-900">{{ __('admin.classifieds.listing_details') }}</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500">{{ __('admin.classifieds.title_en_short') }}</dt><dd class="font-medium" dir="ltr">{{ $listing->title_en }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('admin.classifieds.title_ar_short') }}</dt><dd class="font-medium" dir="rtl">{{ $listing->title_ar }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('common.category') }}</dt><dd>{{ $listing->classifiedCategory?->name_en }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('admin.classifieds.purpose') }}</dt><dd>{{ __('admin.classifieds.purpose_' . $listing->listing_purpose) }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('common.price') }}</dt><dd class="font-bold text-gray-900">{{ $listing->price_formatted }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('admin.classifieds.negotiable') }}</dt><dd>{{ $listing->price_negotiable ? __('common.yes') : __('common.no') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('common.country') }}</dt><dd>{{ $listing->country?->name_en }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('common.city') }}</dt><dd>{{ $listing->city?->name_en ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('admin.classifieds.views') }}</dt><dd>{{ number_format($listing->views_count) }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('admin.classifieds.expires') }}</dt><dd>{{ $listing->expires_at?->format('Y-m-d') ?? '—' }}</dd></div>
                </dl>

                @if(!empty($listing->attributes))
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('admin.classifieds.attributes') }}</h3>
                    <dl class="grid grid-cols-2 gap-2 text-sm bg-gray-50 rounded-lg p-3">
                        @foreach($listing->attributes as $k => $v)
                        <div>
                            <dt class="text-xs text-gray-500">{{ str_replace('_',' ',$k) }}</dt>
                            <dd class="font-medium">{{ $v }}</dd>
                        </div>
                        @endforeach
                    </dl>
                </div>
                @endif

                @if($listing->description_en || $listing->description_ar)
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">{{ __('admin.classifieds.description') }}</h3>
                    <p class="text-sm text-gray-600" dir="ltr">{{ $listing->description_en }}</p>
                    @if($listing->description_ar)
                    <p class="text-sm text-gray-600 mt-1" dir="rtl">{{ $listing->description_ar }}</p>
                    @endif
                </div>
                @endif
            </div>

            {{-- Images --}}
            @if($listing->images->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">{{ __('common.images') }} ({{ $listing->images->count() }})</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                    @foreach($listing->images as $img)
                    <div class="relative aspect-square rounded-lg overflow-hidden border border-gray-100">
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($img->file_path) }}"
                             class="w-full h-full object-cover">
                        @if($img->is_primary)
                        <span class="absolute bottom-1 start-1 bg-primary-600 text-white text-[10px] px-1 rounded">{{ __('admin.classifieds.primary') }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Attachments --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">{{ __('admin.classifieds.attachments') }}</h2>
                @forelse($listing->attachments as $att)
                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            {{ \Illuminate\Support\Facades\Lang::has('admin.classifieds.attach_' . $att->attachment_type)
                                ? __('admin.classifieds.attach_' . $att->attachment_type)
                                : str_replace('_',' ', $att->attachment_type) }}
                        </p>
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($att->file_path) }}"
                           target="_blank" class="text-xs text-primary-600 hover:underline">{{ __('admin.classifieds.view_file') }}</a>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $att->status === \App\Enums\ClassifiedListingAttachmentStatus::Verified ? 'bg-emerald-100 text-emerald-700'
                                : ($att->status === \App\Enums\ClassifiedListingAttachmentStatus::Rejected ? 'bg-red-100 text-red-700'
                                : 'bg-amber-100 text-amber-700') }}">
                            {{ __('admin.classifieds.attachment_status_' . $att->status->value) }}
                        </span>
                        @if($att->status !== \App\Enums\ClassifiedListingAttachmentStatus::Verified)
                        <button onclick="verifyAttachment('{{ $att->id }}', 'verify')"
                                class="text-xs text-emerald-600 font-medium hover:underline">{{ __('admin.classifieds.verify') }}</button>
                        @endif
                        @if($att->status !== \App\Enums\ClassifiedListingAttachmentStatus::Rejected)
                        <button onclick="verifyAttachment('{{ $att->id }}', 'reject')"
                                class="text-xs text-red-500 font-medium hover:underline">{{ __('admin.classifieds.reject') }}</button>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-400">{{ __('admin.classifieds.no_attachments') }}</p>
                @endforelse
            </div>

            {{-- Inquiries --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">{{ __('admin.classifieds.inquiries') }} ({{ $listing->inquiries->count() }})</h2>
                @forelse($listing->inquiries as $inq)
                <div class="py-2 border-b border-gray-100 last:border-0 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ $inq->customer?->name }}</span>
                        <span class="text-xs text-gray-400">{{ $inq->created_at->diffForHumans() }}</span>
                    </div>
                    @if($inq->message)<p class="text-gray-600 mt-0.5">{{ $inq->message }}</p>@endif
                    @if($inq->contact_phone)<p class="text-gray-500 text-xs">📞 {{ $inq->contact_phone }}</p>@endif
                </div>
                @empty
                <p class="text-sm text-gray-400">{{ __('admin.classifieds.no_inquiries') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Right: Sidebar --}}
        <div class="space-y-4">

            {{-- Contract --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-900 mb-3 text-sm">{{ __('admin.classifieds.contract_info') }}</h3>
                @if($listing->contract_accepted_at)
                <div class="space-y-1.5 text-xs text-gray-600">
                    <p><span class="font-medium">{{ __('admin.classifieds.template_label') }}</span> {{ $listing->contractTemplate?->name }}</p>
                    <p><span class="font-medium">{{ __('admin.classifieds.signed') }}</span> {{ $listing->contract_accepted_at->format('Y-m-d H:i') }}</p>
                    @php $sig = json_decode($listing->contract_signature_data, true); @endphp
                    @if($sig)
                    <p><span class="font-medium">{{ __('admin.classifieds.name_label') }}</span> {{ $sig['name'] }}</p>
                    <p><span class="font-medium">{{ __('admin.classifieds.ip_label') }}</span> {{ $sig['ip'] }}</p>
                    @endif
                </div>
                @else
                <p class="text-xs text-gray-400">{{ __('admin.classifieds.not_signed_yet') }}</p>
                @endif
            </div>

            {{-- Location --}}
            @if($listing->latitude && $listing->longitude)
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-900 mb-2 text-sm">{{ __('admin.classifieds.location') }}</h3>
                <p class="text-xs text-gray-500">{{ $listing->latitude }}, {{ $listing->longitude }}</p>
            </div>
            @endif

            {{-- Sketch --}}
            @if($listing->sketch_file_path)
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-900 mb-2 text-sm">{{ __('admin.classifieds.sketch_floor_plan') }}</h3>
                <a href="{{ \Illuminate\Support\Facades\Storage::url($listing->sketch_file_path) }}"
                   target="_blank"
                   class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                    <x-heroicon name="document" class="w-4 h-4" /> {{ __('admin.classifieds.view_sketch') }}
                </a>
            </div>
            @endif

            {{-- QR Barcode --}}
            @if($listing->barcode_path)
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <h3 class="font-semibold text-gray-900 mb-2 text-sm">{{ __('admin.classifieds.qr_code') }}</h3>
                <img src="{{ \Illuminate\Support\Facades\Storage::url($listing->barcode_path) }}"
                     alt="{{ __('admin.classifieds.qr_code') }}" class="w-24 h-24 mx-auto">
            </div>
            @endif

        </div>
    </div>
</div>

{{-- Approve Confirmation Modal --}}
<div id="approve-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm space-y-4" onclick="event.stopPropagation()">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-base">{{ __('admin.classifieds.approve_confirm_title') }}</h3>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.classifieds.approve_confirm_body') }}</p>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
            <button type="button"
                    onclick="document.getElementById('approve-modal').classList.add('hidden')"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                {{ __('common.cancel') }}
            </button>
            <form id="approve-form" method="POST" action="{{ route('admin.classifieds.listings.approve', $listing) }}">
                @csrf
                <button type="submit" id="approve-submit-btn"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors flex items-center gap-2">
                    <span id="approve-btn-label">{{ __('admin.classifieds.approve') }}</span>
                    <svg id="approve-spinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div id="reject-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md space-y-4" onclick="event.stopPropagation()">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-base">{{ __('admin.classifieds.reject_listing_title') }}</h3>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.classifieds.reject_confirm_body') }}</p>
            </div>
        </div>

        <form id="reject-form" method="POST" action="{{ route('admin.classifieds.listings.reject', $listing) }}">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('admin.classifieds.reject_reason_required') }}
                    <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" id="reject-reason" rows="4" required
                          placeholder="{{ __('admin.classifieds.reject_reason_placeholder') }}"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none resize-none"></textarea>
                <p id="reject-reason-error" class="hidden text-xs text-red-500 mt-1">{{ __('admin.classifieds.reject_reason_required_error') }}</p>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button"
                        onclick="document.getElementById('reject-modal').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    {{ __('common.cancel') }}
                </button>
                <button type="button" id="reject-submit-btn"
                        onclick="submitReject()"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors flex items-center gap-2">
                    <span id="reject-btn-label">{{ __('admin.classifieds.confirm_reject') }}</span>
                    <svg id="reject-spinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Approve: show spinner + disable button on submit ───────────────────────
document.getElementById('approve-form').addEventListener('submit', function () {
    const btn   = document.getElementById('approve-submit-btn');
    const label = document.getElementById('approve-btn-label');
    const spin  = document.getElementById('approve-spinner');
    btn.disabled = true;
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    label.textContent = '{{ __("admin.classifieds.approving") }}';
    spin.classList.remove('hidden');
});

// ── Reject: validate then show spinner + submit ────────────────────────────
function submitReject() {
    const textarea = document.getElementById('reject-reason');
    const errEl    = document.getElementById('reject-reason-error');
    const btn      = document.getElementById('reject-submit-btn');
    const label    = document.getElementById('reject-btn-label');
    const spin     = document.getElementById('reject-spinner');

    if (!textarea.value.trim()) {
        textarea.classList.add('border-red-400');
        errEl.classList.remove('hidden');
        textarea.focus();
        return;
    }

    textarea.classList.remove('border-red-400');
    errEl.classList.add('hidden');

    btn.disabled = true;
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    label.textContent = '{{ __("admin.classifieds.rejecting") }}';
    spin.classList.remove('hidden');

    document.getElementById('reject-form').submit();
}

// ── Verify / reject attachment ─────────────────────────────────────────────
async function verifyAttachment(id, action) {
    const resp = await fetch('{{ route("admin.classifieds.attachments.verify", "__ID__") }}'.replace('__ID__', id), {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ action }),
    });
    if (resp.ok) window.location.reload();
}

// ── Close modals on Escape key ─────────────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.getElementById('approve-modal').classList.add('hidden');
        document.getElementById('reject-modal').classList.add('hidden');
    }
});
</script>
@endpush
