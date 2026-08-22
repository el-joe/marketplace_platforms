@extends('layouts.partner')

@section('title', __('partner.ai_tools.title'))

@section('content')
<div class="p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('partner.ai_tools.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.ai_tools.subtitle') }}</p>
        </div>
        <div id="credits-badge" class="text-xs text-gray-500 bg-gray-100 rounded-lg px-3 py-2">
            {{ __('partner.ai_tools.loading_credits') }}
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-4">
            <button onclick="showTab('enhance')" id="tab-enhance"
                class="tab-btn border-b-2 border-blue-600 text-blue-600 pb-2 px-1 text-sm font-medium">
                {{ __('partner.ai_tools.tab_enhance') }}
            </button>
            <button onclick="showTab('video')" id="tab-video"
                class="tab-btn border-b-2 border-transparent text-gray-500 pb-2 px-1 text-sm font-medium hover:text-gray-700">
                {{ __('partner.ai_tools.tab_video') }}
            </button>
        </nav>
    </div>

    {{-- Image Enhancement Tab --}}
    <div id="panel-enhance" class="space-y-4">
        <p class="text-sm text-gray-600">
            {{ __('partner.ai_tools.enhance_intro') }}
        </p>

        @if($listing->productVariant?->images?->isNotEmpty())
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($listing->productVariant->images as $image)
            <div class="border rounded-xl overflow-hidden bg-white shadow-sm" id="image-card-{{ $image->id }}">
                <img src="{{ asset('storage/' . $image->path) }}"
                     class="w-full h-40 object-cover">
                <div class="p-2 space-y-1">
                    <button
                        onclick="enhanceImage('{{ $image->id }}')"
                        class="w-full text-xs bg-blue-600 text-white rounded-lg py-1.5 font-medium hover:bg-blue-700 transition enhance-btn"
                        data-image-id="{{ $image->id }}">
                        ✨ {{ __('partner.ai_tools.enhance_with_ai') }}
                    </button>
                </div>
                {{-- Result area (hidden until job completes) --}}
                <div id="result-{{ $image->id }}" class="hidden p-2 space-y-2">
                    <p class="text-xs font-semibold text-gray-500">{{ __('partner.ai_tools.ai_enhanced_label') }}</p>
                    <img id="enhanced-img-{{ $image->id }}" src="" class="w-full h-40 object-cover rounded-lg border-2 border-green-400">
                    <div class="flex gap-1">
                        <button onclick="applyEnhancement('{{ $image->id }}')"
                            class="flex-1 text-xs bg-green-600 text-white rounded-lg py-1.5 font-medium hover:bg-green-700">
                            ✓ {{ __('partner.ai_tools.apply') }}
                        </button>
                        <button onclick="discardEnhancement('{{ $image->id }}')"
                            class="flex-1 text-xs bg-gray-200 text-gray-700 rounded-lg py-1.5 font-medium hover:bg-gray-300">
                            ✗ {{ __('partner.ai_tools.discard') }}
                        </button>
                    </div>
                </div>
                {{-- Processing spinner --}}
                <div id="spinner-{{ $image->id }}" class="hidden p-3 flex items-center gap-2 text-xs text-gray-500">
                    <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    {{ __('partner.ai_tools.enhancing') }}
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-400">{{ __('partner.ai_tools.no_images') }}</p>
        @endif
    </div>

    {{-- Video Generation Tab --}}
    <div id="panel-video" class="hidden space-y-4">
        <p class="text-sm text-gray-600">
            {{ __('partner.ai_tools.video_intro') }}
        </p>

        <form id="video-form" class="space-y-3 max-w-lg">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.ai_tools.prompt_label') }}</label>
                <textarea name="prompt" rows="4" required
                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    placeholder="{{ __('partner.ai_tools.prompt_placeholder') }}"></textarea>
            </div>
            <button type="submit"
                class="bg-purple-600 text-white rounded-xl px-5 py-2 text-sm font-medium hover:bg-purple-700 transition">
                🎬 {{ __('partner.ai_tools.generate_video') }}
            </button>
        </form>

        <div id="video-status" class="hidden">
            <div id="video-processing" class="flex items-center gap-2 text-sm text-gray-500">
                <svg class="animate-spin h-5 w-5 text-purple-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ __('partner.ai_tools.generating_video') }}
            </div>
            <div id="video-result" class="hidden space-y-2">
                <p class="text-sm font-semibold text-green-700">✓ {{ __('partner.ai_tools.video_ready') }}</p>
                <video id="video-player" controls class="rounded-xl max-w-md border"></video>
                <a id="video-download" href="#" download
                   class="inline-block text-sm text-blue-600 underline">{{ __('partner.ai_tools.download_video') }}</a>
            </div>
            <p id="video-error" class="hidden text-sm text-red-600"></p>
        </div>
    </div>

</div>

@push('scripts')
<script>
const listingId = '{{ $listing->id }}';
const enhancementJobs = {}; // imageId → jobId
let videoJobId = null;
window.PARTNER_TRANSLATIONS = window.PARTNER_TRANSLATIONS || {};
Object.assign(window.PARTNER_TRANSLATIONS, {
    creditsLabel: (enhance, video) => `{{ __('partner.ai_tools.credits_label', ['enhance' => '__ENHANCE__', 'video' => '__VIDEO__']) }}`.replace('__ENHANCE__', enhance).replace('__VIDEO__', video),
    enhancementFailed: @json(__('partner.ai_tools.enhancement_failed')),
    generationFailed: @json(__('partner.ai_tools.generation_failed')),
    unknownError: @json(__('partner.ai_tools.unknown_error')),
});

// ── Tabs ──────────────────────────────────────────────────────────────────
function showTab(name) {
    ['enhance', 'video'].forEach(t => {
        document.getElementById('panel-' + t).classList.toggle('hidden', t !== name);
        const btn = document.getElementById('tab-' + t);
        btn.classList.toggle('border-blue-600', t === name);
        btn.classList.toggle('text-blue-600', t === name);
        btn.classList.toggle('border-transparent', t !== name);
        btn.classList.toggle('text-gray-500', t !== name);
    });
}

// ── Credits ───────────────────────────────────────────────────────────────
fetch('/partner/ai/credits')
    .then(r => r.json())
    .then(data => {
        const el = document.getElementById('credits-badge');
        el.textContent = window.PARTNER_TRANSLATIONS.creditsLabel(data.image_enhancement?.remaining ?? 0, data.video_generation?.remaining ?? 0);
    });

// ── Image Enhancement ─────────────────────────────────────────────────────
function enhanceImage(imageId) {
    document.getElementById('spinner-' + imageId).classList.remove('hidden');
    document.querySelector(`[data-image-id="${imageId}"]`).disabled = true;

    fetch(`/partner/ai/enhance/${listingId}/${imageId}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
    })
    .then(r => r.json())
    .then(data => {
        if (data.job_id) {
            enhancementJobs[imageId] = data.job_id;
            pollEnhancement(imageId, data.job_id);
        }
    });
}

function pollEnhancement(imageId, jobId) {
    fetch(`/partner/ai/enhance/status/${jobId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'completed') {
                document.getElementById('spinner-' + imageId).classList.add('hidden');
                document.getElementById('enhanced-img-' + imageId).src = data.enhanced_path;
                document.getElementById('result-' + imageId).classList.remove('hidden');
            } else if (data.status === 'failed') {
                document.getElementById('spinner-' + imageId).classList.add('hidden');
                document.querySelector(`[data-image-id="${imageId}"]`).disabled = false;
                alert(window.PARTNER_TRANSLATIONS.enhancementFailed + ': ' + (data.error || window.PARTNER_TRANSLATIONS.unknownError));
            } else {
                setTimeout(() => pollEnhancement(imageId, jobId), 3000);
            }
        });
}

function applyEnhancement(imageId) {
    const jobId = enhancementJobs[imageId];
    fetch(`/partner/ai/enhance/apply/${jobId}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
    })
    .then(r => r.json())
    .then(() => {
        document.getElementById('result-' + imageId).classList.add('hidden');
        // Swap the original thumbnail
        const card = document.getElementById('image-card-' + imageId);
        card.querySelector('img').src = document.getElementById('enhanced-img-' + imageId).src;
    });
}

function discardEnhancement(imageId) {
    document.getElementById('result-' + imageId).classList.add('hidden');
    document.querySelector(`[data-image-id="${imageId}"]`).disabled = false;
}

// ── Video Generation ──────────────────────────────────────────────────────
document.getElementById('video-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const prompt = this.querySelector('[name=prompt]').value;

    document.getElementById('video-status').classList.remove('hidden');
    document.getElementById('video-processing').classList.remove('hidden');
    document.getElementById('video-result').classList.add('hidden');
    document.getElementById('video-error').classList.add('hidden');

    fetch('/partner/ai/video/generate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        },
        body: JSON.stringify({ prompt, vendor_listing_id: null }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.job_id) {
            videoJobId = data.job_id;
            pollVideo(data.job_id);
        }
    });
});

function pollVideo(jobId) {
    fetch(`/partner/ai/video/status/${jobId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'completed') {
                document.getElementById('video-processing').classList.add('hidden');
                const player = document.getElementById('video-player');
                player.src = data.video_url;
                document.getElementById('video-download').href = data.video_url;
                document.getElementById('video-result').classList.remove('hidden');
            } else if (data.status === 'failed') {
                document.getElementById('video-processing').classList.add('hidden');
                const err = document.getElementById('video-error');
                err.textContent = window.PARTNER_TRANSLATIONS.generationFailed + ': ' + (data.error || window.PARTNER_TRANSLATIONS.unknownError);
                err.classList.remove('hidden');
            } else {
                setTimeout(() => pollVideo(jobId), 5000);
            }
        });
}
</script>
@endpush
@endsection
