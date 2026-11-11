{{--
    Product request details, shared by the partner and marketer panels.
    Expects: $panel ('partner'|'marketer'), $localTime (Closure: ?Carbon → ?Carbon in the viewer's timezone),
             $productRequest (with category, brand, country, product, requestedBy loaded).
--}}
@use('App\Enums\ProductRequestStatus')
@php
    $routePrefix = $panel . '.product-requests.';
    $isArabic = app()->getLocale() === 'ar';
    $status = $productRequest->status;
    $imageUrls = $productRequest->imageUrls();
    $approvedProduct = $productRequest->product;
    $approvedProductName = $approvedProduct
        ? ($isArabic ? ($approvedProduct->name_ar ?: $approvedProduct->name_en) : ($approvedProduct->name_en ?: $approvedProduct->name_ar))
        : null;
    // Pre-fills the listing wizard search (?q=); only the partner search matches barcodes.
    $listingSearchText = $approvedProduct
        ? (($panel === 'partner' && $approvedProduct->gtin) ? $approvedProduct->gtin : ($approvedProduct->name_en ?: $approvedProduct->name_ar))
        : null;
    $localizedName = fn ($model) => $model ? ($isArabic ? ($model->name_ar ?: $model->name_en) : ($model->name_en ?: $model->name_ar)) : null;
    $noValue = __('product_requests.panel.no_value');

    $finalStep = match ($status) {
        ProductRequestStatus::Approved => ['label' => __('product_requests.panel.timeline.approved'), 'tone' => 'emerald', 'at' => $productRequest->reviewed_at],
        ProductRequestStatus::Rejected => ['label' => __('product_requests.panel.timeline.rejected'), 'tone' => 'red', 'at' => $productRequest->reviewed_at],
        ProductRequestStatus::Cancelled => ['label' => __('product_requests.panel.timeline.cancelled'), 'tone' => 'gray', 'at' => $productRequest->updated_at],
        default => null,
    };
    $toneClasses = [
        'emerald' => 'bg-emerald-500 text-white',
        'red' => 'bg-red-500 text-white',
        'gray' => 'bg-gray-400 text-white',
    ];

    $details = [
        __('product_requests.fields.name_en') => ['value' => $productRequest->name_en, 'dir' => 'ltr'],
        __('product_requests.fields.name_ar') => ['value' => $productRequest->name_ar, 'dir' => 'rtl'],
        __('product_requests.fields.category') => ['value' => $productRequest->category?->name],
        __('product_requests.fields.brand') => ['value' => $localizedName($productRequest->brand) ?? $productRequest->brand_name],
        __('product_requests.fields.gtin') => ['value' => $productRequest->gtin, 'mono' => true],
        __('product_requests.fields.model_number') => ['value' => $productRequest->model_number, 'dir' => 'ltr'],
        __('product_requests.fields.country') => ['value' => $localizedName($productRequest->country) ?? __('product_requests.panel.any_country')],
        __('product_requests.panel.requested_by') => ['value' => $productRequest->requestedBy?->name],
    ];
@endphp

<div class="max-w-5xl space-y-4">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route($routePrefix . 'index') }}" class="hover:text-gray-700">{{ __('product_requests.title') }}</a>
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-gray-800 font-medium font-mono">{{ $productRequest->request_number }}</span>
    </div>

    {{-- Header --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-16 h-16 rounded-xl border border-gray-100 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                    @if($imageUrls->isNotEmpty())
                        <img src="{{ $imageUrls->first() }}" alt="" class="w-full h-full object-cover">
                    @else
                        <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10" />
                        </svg>
                    @endif
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-gray-900">{{ $productRequest->displayName() }}</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                        <span class="font-mono">{{ $productRequest->request_number }}</span>
                        <span>·</span>
                        <span>{{ __('product_requests.panel.submitted_at') }}: {!! format_tz($localTime($productRequest->created_at)) !!}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $status->color() }}">{{ $status->label() }}</span>
                @if($productRequest->isPending())
                    <form method="POST" action="{{ route($routePrefix . 'cancel', $productRequest->id) }}"
                        onsubmit="return confirm(@js(__('product_requests.panel.cancel_confirm')))">
                        @csrf
                        <button type="submit"
                            class="border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                            {{ __('product_requests.panel.cancel_request') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Timeline --}}
        <ol class="mt-6 grid grid-cols-3 gap-2 text-center">
            <li class="flex flex-col items-center gap-1.5">
                <span class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                </span>
                <span class="text-xs font-semibold text-gray-700">{{ __('product_requests.panel.timeline.submitted') }}</span>
                <span class="text-[11px] text-gray-400" dir="ltr">{{ $localTime($productRequest->created_at)?->format('Y/m/d H:i') }}</span>
            </li>
            <li class="flex flex-col items-center gap-1.5">
                <span @class([
                    'w-8 h-8 rounded-full flex items-center justify-center',
                    'bg-amber-400 text-white animate-pulse' => $productRequest->isPending(),
                    'bg-emerald-500 text-white' => in_array($status, [ProductRequestStatus::Approved, ProductRequestStatus::Rejected], true),
                    'bg-gray-200 text-gray-400' => $status === ProductRequestStatus::Cancelled,
                ])>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
                <span class="text-xs font-semibold text-gray-700">{{ __('product_requests.panel.timeline.under_review') }}</span>
            </li>
            <li class="flex flex-col items-center gap-1.5">
                <span class="w-8 h-8 rounded-full flex items-center justify-center {{ $finalStep ? $toneClasses[$finalStep['tone']] : 'bg-gray-200 text-gray-400' }}">
                    @if($status === ProductRequestStatus::Approved || ! $finalStep)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                    @endif
                </span>
                <span class="text-xs font-semibold text-gray-700">{{ $finalStep['label'] ?? __('product_requests.panel.timeline.approved') }}</span>
                @if($finalStep && $finalStep['at'])
                    <span class="text-[11px] text-gray-400" dir="ltr">{{ $localTime($finalStep['at'])?->format('Y/m/d H:i') }}</span>
                @endif
            </li>
        </ol>
    </div>

    {{-- Outcome --}}
    @if($status === ProductRequestStatus::Pending)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            {{ __('product_requests.panel.pending_note') }}
        </div>
    @elseif($status === ProductRequestStatus::Approved)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="font-semibold text-emerald-800">{{ __('product_requests.panel.approved_title') }}</h3>
                @if($approvedProductName)
                    <p class="text-sm text-emerald-700 mt-1">{{ __('product_requests.panel.approved_body', ['name' => $approvedProductName]) }}</p>
                @endif
            </div>
            @if($approvedProduct && Route::has($panel . '.listings.create'))
                <a href="{{ route($panel . '.listings.create', ['q' => $listingSearchText]) }}"
                    class="inline-flex items-center justify-center gap-2 bg-yellow-400 hover:bg-yellow-300 text-gray-900 text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('product_requests.panel.create_listing') }}
                </a>
            @endif
        </div>
    @elseif($status === ProductRequestStatus::Rejected)
        <div class="rounded-2xl border border-red-200 bg-red-50 p-5">
            <h3 class="font-semibold text-red-800">{{ __('product_requests.panel.rejected_title') }}</h3>
            @if($productRequest->rejection_reason)
                <p class="text-xs font-semibold text-red-700 mt-3">{{ __('product_requests.panel.rejection_reason') }}</p>
                <p class="text-sm text-red-700 mt-1 whitespace-pre-line">{{ $productRequest->rejection_reason }}</p>
            @endif
            <p class="text-xs text-red-600 mt-3">{{ __('product_requests.panel.rejected_hint') }}</p>
        </div>
    @else
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
            {{ __('product_requests.panel.cancelled_note') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Details --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('product_requests.panel.details_title') }}</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                @foreach($details as $label => $detail)
                    <div>
                        <dt class="text-xs text-gray-400">{{ $label }}</dt>
                        <dd @class(['text-gray-800 font-medium mt-0.5 break-words', 'font-mono' => $detail['mono'] ?? false])
                            @if(! empty($detail['dir'])) dir="{{ $detail['dir'] }}" @endif>{{ $detail['value'] ?: $noValue }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-5 pt-5 border-t border-gray-100 space-y-4 text-sm">
                <div>
                    <p class="text-xs text-gray-400">{{ __('product_requests.fields.description') }}</p>
                    <p class="text-gray-700 mt-1 whitespace-pre-line leading-relaxed">{{ $productRequest->description }}</p>
                </div>
                @if($productRequest->variants_note)
                    <div>
                        <p class="text-xs text-gray-400">{{ __('product_requests.fields.variants_note') }}</p>
                        <p class="text-gray-700 mt-1 whitespace-pre-line">{{ $productRequest->variants_note }}</p>
                    </div>
                @endif
                @if($productRequest->reference_url)
                    <div>
                        <p class="text-xs text-gray-400">{{ __('product_requests.fields.reference_url') }}</p>
                        @if($productRequest->linkableReferenceUrl())
                            <a href="{{ $productRequest->linkableReferenceUrl() }}" target="_blank" rel="noopener noreferrer nofollow" dir="ltr"
                                class="text-blue-600 hover:underline mt-1 inline-block break-all">{{ $productRequest->reference_url }}</a>
                        @else
                            <p class="text-gray-700 mt-1 break-all" dir="ltr">{{ $productRequest->reference_url }}</p>
                        @endif
                    </div>
                @endif
                @if($productRequest->notes)
                    <div>
                        <p class="text-xs text-gray-400">{{ __('product_requests.fields.notes') }}</p>
                        <p class="text-gray-700 mt-1 whitespace-pre-line">{{ $productRequest->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Images --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5" x-data="{ active: 0, urls: @js($imageUrls->all()) }">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('product_requests.panel.images_title') }}</h3>
            @if($imageUrls->isEmpty())
                <p class="text-sm text-gray-400">{{ $noValue }}</p>
            @else
                <a :href="urls[active]" target="_blank" rel="noopener"
                    class="block aspect-square rounded-xl border border-gray-100 bg-gray-50 overflow-hidden">
                    <img :src="urls[active]" src="{{ $imageUrls->first() }}" alt="" class="w-full h-full object-contain">
                </a>
                @if($imageUrls->count() > 1)
                    <div class="grid grid-cols-6 gap-2 mt-3">
                        @foreach($imageUrls as $index => $url)
                            <button type="button" @click="active = {{ $index }}"
                                class="aspect-square rounded-lg overflow-hidden border-2 transition-colors"
                                :class="active === {{ $index }} ? 'border-yellow-400' : 'border-transparent hover:border-gray-200'">
                                <img src="{{ $url }}" alt="" class="w-full h-full object-cover" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
