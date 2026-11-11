{{--
    Product requests list, shared by the partner and marketer panels.
    Expects: $panel ('partner'|'marketer'), $localTime (Closure: ?Carbon → ?Carbon in the viewer's timezone),
             $productRequests (paginator), $statusCounts (status => count), $statusFilter (?ProductRequestStatus).
--}}
@use('App\Enums\ProductRequestStatus')
@php
    $routePrefix = $panel . '.product-requests.';
    $totalCount = $statusCounts->sum();
@endphp

<div class="space-y-4">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">{{ __('product_requests.panel.subtitle') }}</p>
        <a href="{{ route($routePrefix . 'create') }}"
            class="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-300 text-gray-900 text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ __('product_requests.panel.new_request') }}
        </a>
    </div>

    {{-- Status tabs --}}
    <div class="bg-white rounded-2xl border border-gray-200">
        <div class="flex items-center overflow-x-auto">
            <a href="{{ route($routePrefix . 'index') }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                'border-yellow-400 text-yellow-600' => ! $statusFilter,
                'border-transparent text-gray-500 hover:text-gray-700' => $statusFilter,
            ])>
                {{ __('product_requests.panel.all') }}
                <span class="ms-1 text-xs text-gray-400">({{ $totalCount }})</span>
            </a>
            @foreach(ProductRequestStatus::cases() as $status)
                <a href="{{ route($routePrefix . 'index', ['status' => $status->value]) }}" @class([
                    'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                    'border-yellow-400 text-yellow-600' => $statusFilter === $status,
                    'border-transparent text-gray-500 hover:text-gray-700' => $statusFilter !== $status,
                ])>
                    {{ $status->label() }}
                    <span class="ms-1 text-xs text-gray-400">({{ $statusCounts[$status->value] ?? 0 }})</span>
                </a>
            @endforeach
        </div>
    </div>

    @if($productRequests->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 py-16 px-6 text-center">
            <div class="w-14 h-14 mx-auto bg-yellow-50 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10M12 9v4m-2-2h4" />
                </svg>
            </div>
            @if($statusFilter)
                <h3 class="font-semibold text-gray-800">{{ __('product_requests.panel.empty_filtered') }}</h3>
            @else
                <h3 class="font-semibold text-gray-800 mb-1">{{ __('product_requests.panel.empty_title') }}</h3>
                <p class="text-sm text-gray-400 max-w-md mx-auto">{{ __('product_requests.panel.empty_hint') }}</p>
                <a href="{{ route($routePrefix . 'create') }}"
                    class="inline-flex items-center gap-2 mt-5 bg-yellow-400 hover:bg-yellow-300 text-gray-900 text-sm font-semibold px-5 py-2 rounded-xl transition-colors">
                    {{ __('product_requests.panel.new_request') }}
                </a>
            @endif
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                            <th class="px-4 py-3 text-start font-semibold">{{ __('product_requests.request_number') }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ __('product_requests.panel.product') }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ __('product_requests.fields.category') }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ __('common.status') }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ __('product_requests.panel.submitted_at') }}</th>
                            <th class="px-4 py-3 text-end font-semibold">{{ __('product_requests.panel.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($productRequests as $productRequest)
                            @php $thumbnailUrl = $productRequest->imageUrls()->first(); @endphp
                            <tr class="hover:bg-gray-50/60 align-top">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route($routePrefix . 'show', $productRequest->id) }}"
                                        class="font-mono text-xs text-gray-600 hover:text-yellow-600">{{ $productRequest->request_number }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-3 min-w-[14rem]">
                                        <div class="w-11 h-11 rounded-lg border border-gray-100 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                                            @if($thumbnailUrl)
                                                <img src="{{ $thumbnailUrl }}" alt="" class="w-full h-full object-cover" loading="lazy">
                                            @else
                                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route($routePrefix . 'show', $productRequest->id) }}"
                                                class="font-medium text-gray-800 hover:text-yellow-600 line-clamp-2">{{ $productRequest->displayName() }}</a>
                                            @if($productRequest->gtin)
                                                <p class="text-xs font-mono text-gray-400 mt-0.5">{{ $productRequest->gtin }}</p>
                                            @endif
                                            @if($productRequest->status === ProductRequestStatus::Rejected && $productRequest->rejection_reason)
                                                <p class="text-xs text-red-600 mt-1 max-w-xs" title="{{ $productRequest->rejection_reason }}">
                                                    <span class="font-medium">{{ __('product_requests.panel.rejection_reason') }}:</span>
                                                    {{ \Illuminate\Support\Str::limit($productRequest->rejection_reason, 80) }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $productRequest->category?->name ?? __('product_requests.panel.no_value') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $productRequest->status->color() }}">
                                        {{ $productRequest->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">{{ $localTime($productRequest->created_at)?->format('Y/m/d H:i') }}</td>
                                <td class="px-4 py-3 text-end whitespace-nowrap">
                                    <a href="{{ route($routePrefix . 'show', $productRequest->id) }}"
                                        class="text-xs font-medium text-yellow-600 hover:text-yellow-700">{{ __('product_requests.panel.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($productRequests->hasPages())
            <div>{{ $productRequests->links() }}</div>
        @endif
    @endif
</div>
