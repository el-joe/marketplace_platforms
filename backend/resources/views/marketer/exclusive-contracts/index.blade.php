@extends('layouts.marketer')
@section('title', __('marketer.exclusive_contracts'))
@section('page-title', __('marketer.exclusive_contracts'))

@section('content')
<div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600"><tr>
            <th class="p-3 text-start">{{ __('marketer.listing_or_category') }}</th>
            <th class="p-3 text-start">{{ __('marketer.starts_at') }}</th>
            <th class="p-3 text-start">{{ __('marketer.ends_at') }}</th>
            <th class="p-3 text-start">{{ __('marketer.status') }}</th>
            <th class="p-3"></th>
        </tr></thead>
        <tbody>
        @forelse($contracts as $c)
            <tr class="border-t">
                <td class="p-3"><a class="text-blue-600" href="{{ route('marketer.exclusive-contracts.show', $c->id) }}">{{ $c->classifiedListing?->title ?? $c->classifiedCategory?->name ?? __('marketer.all_categories') }}</a></td>
                <td class="p-3">{{ $c->starts_at?->format('Y-m-d') }}</td>
                <td class="p-3">{{ $c->ends_at?->format('Y-m-d') }}</td>
                <td class="p-3">{{ __('marketer.contract_'.$c->status) }}</td>
                <td class="p-3">@if($c->contract_file_path)<a class="text-blue-600" href="{{ route('marketer.exclusive-contracts.download', $c->id) }}">{{ __('marketer.download_contract') }}</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="5" class="p-10 text-center text-gray-500">{{ __('marketer.classified_no_contracts') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $contracts->links() }}</div>
@endsection
