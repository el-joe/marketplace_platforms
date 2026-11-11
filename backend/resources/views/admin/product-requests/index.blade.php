@extends('layouts.admin')

@section('title', __('admin.product_requests.title'))

@section('content')
<div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.product_requests.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.product_requests.subtitle') }}</p>
        </div>
    </div>

    {{-- ─── Status Tabs ─────────────────────────────────────────────────────── --}}
    <div class="w-full gap-1 border-b border-gray-200 overflow-x-auto whitespace-nowrap">
        @php
            $tabs = ['all' => __('admin.product_requests.all')];
            foreach ($statuses as $statusCase) {
                $tabs[$statusCase->value] = $statusCase->label()
                    . ($statusCase === \App\Enums\ProductRequestStatus::Pending ? " ({$pendingCount})" : '');
            }
        @endphp
        @foreach($tabs as $tabValue => $tabLabel)
            <a href="{{ route('admin.product-requests.index', array_merge(request()->except(['page', 'status']), ['status' => $tabValue])) }}"
               class="inline-block px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors
                      {{ $currentStatus === $tabValue ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    {{-- ─── Filters ─────────────────────────────────────────────────────────── --}}
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="status" value="{{ $currentStatus }}">
        <div>
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('admin.product_requests.search_placeholder') }}"
                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-72 max-w-full">
        </div>
        <div>
            <select name="requester_type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">{{ __('admin.product_requests.any_requester') }}</option>
                @foreach(['vendor', 'marketer'] as $type)
                    <option value="{{ $type }}" @selected(request('requester_type') === $type)>{{ __('product_requests.requester_types.' . $type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.product_requests.date_from') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.product_requests.date_to') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">{{ __('admin.product_requests.filter') }}</button>
        <a href="{{ route('admin.product-requests.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('admin.product_requests.reset') }}</a>
    </form>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="table-responsive">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.product_requests.number_column') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.product_requests.product_column') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.product_requests.requester_column') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.product_requests.category_column') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.product_requests.status_column') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.product_requests.submitted_at_column') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.product_requests.reviewed_by_column') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($productRequests as $productRequest)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-700 whitespace-nowrap">{{ $productRequest->request_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $productRequest->displayName() }}</div>
                                @if($productRequest->gtin)
                                    <div class="text-xs text-gray-500 font-mono" dir="ltr">{{ $productRequest->gtin }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-gray-900">{{ $productRequest->requesterName() }}</div>
                                <div class="text-xs text-gray-500">{{ __('product_requests.requester_types.' . $productRequest->requester_type) }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ app()->getLocale() === 'ar' ? ($productRequest->category?->name_ar ?: $productRequest->category?->name_en) : $productRequest->category?->name_en }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $productRequest->status->color() }}">
                                    {{ $productRequest->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ admin_tz($productRequest->created_at)?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $productRequest->reviewer?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('admin.product-requests.show', $productRequest->id) }}"
                                   class="text-xs text-primary-600 font-medium hover:underline">{{ __('admin.product_requests.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">{{ __('admin.product_requests.no_records') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($productRequests->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $productRequests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
