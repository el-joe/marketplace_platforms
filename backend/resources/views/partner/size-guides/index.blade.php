@extends('layouts.partner')

@section('title', __('partner.size_guides.title'))
@section('page-title', __('partner.size_guides.title'))

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <p class="text-sm text-gray-500 max-w-2xl">{{ __('partner.size_guides.intro') }}</p>
        <div class="flex gap-2 flex-shrink-0">
            <a href="{{ route('partner.size-guides.create', ['template' => 1]) }}"
               class="border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                {{ __('partner.size_guides.create_from_template') }}
            </a>
            <a href="{{ route('partner.size-guides.create') }}"
               class="bg-yellow-400 hover:bg-yellow-300 text-gray-900 text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                {{ __('partner.size_guides.create') }}
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-x-auto">
        @if($guides->isEmpty())
            <div class="p-10 text-center text-sm text-gray-500">{{ __('partner.size_guides.empty') }}</div>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold">{{ __('partner.size_guides.name') }}</th>
                        <th class="px-4 py-3 text-start font-semibold">{{ __('partner.size_guides.title_ar') }}</th>
                        <th class="px-4 py-3 text-start font-semibold">{{ __('partner.size_guides.sizes_count') }}</th>
                        <th class="px-4 py-3 text-start font-semibold">{{ __('partner.size_guides.used_by') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($guides as $guide)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $guide->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $guide->title_ar ?: $guide->title_en ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $guide->rows_count }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $guide->listings_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('partner.size-guides.edit', $guide) }}" class="text-primary-600 hover:text-primary-700 font-medium">
                                        {{ __('partner.size_guides.edit') }}
                                    </a>
                                    <form method="POST" action="{{ route('partner.size-guides.destroy', $guide) }}"
                                          onsubmit="return confirm(@js(__('partner.size_guides.delete_confirm')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-600 font-medium">{{ __('partner.size_guides.delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
