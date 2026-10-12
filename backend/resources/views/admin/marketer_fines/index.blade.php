@extends('layouts.admin')
@section('title', __('admin.marketer_fines.title'))

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.marketer_fines.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.marketer_fines.subtitle') }}</p>
    </div>

    @if (session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-3 gap-3">
        @foreach (['pending', 'deducted', 'waived'] as $st)
            <div class="rounded-lg border bg-white p-4">
                <div class="text-xs text-gray-500">{{ __('admin.marketer_fines.status_'.$st) }}</div>
                <div class="text-xl font-bold">{{ number_format((int) ($totals[$st]->total ?? 0)) }}</div>
                <div class="text-xs text-gray-400">{{ (int) ($totals[$st]->n ?? 0) }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <select name="status" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('admin.marketer_fines.all_statuses') }}</option>
            @foreach (['pending', 'deducted', 'waived'] as $st)
                <option value="{{ $st }}" @selected(($filters['status'] ?? null) === $st)>{{ __('admin.marketer_fines.status_'.$st) }}</option>
            @endforeach
        </select>
        <select name="category" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('admin.marketer_fines.all_categories') }}</option>
            @foreach (\App\Enums\PromotionCategory::cases() as $c)
                <option value="{{ $c->value }}" @selected((string) ($filters['category'] ?? '') === (string) $c->value)>{{ $c->value }} — {{ $c->label() }}</option>
            @endforeach
        </select>
        <input type="text" name="period" value="{{ $filters['period'] ?? '' }}" placeholder="YYYY-MM" class="w-28 rounded-md border-gray-300 text-sm">
        <button class="rounded-md bg-gray-800 px-3 py-2 text-xs font-medium text-white">{{ __('common.search') }}</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50"><tr>
                @foreach (['marketer', 'category', 'period', 'progress', 'amount', 'status'] as $col)
                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('admin.marketer_fines.col_'.$col) }}</th>
                @endforeach
                <th></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($fines as $fine)
                <tr>
                    <td class="px-3 py-2"><a class="text-primary-600 hover:underline" href="{{ route('admin.marketers.show', $fine->marketer_id) }}">{{ $fine->marketer?->name }}</a></td>
                    <td class="px-3 py-2">{{ $fine->promotion_category->value }} — {{ $fine->promotion_category->label() }}</td>
                    <td class="px-3 py-2">{{ $fine->period }}</td>
                    <td class="px-3 py-2">{{ $fine->done }}/{{ $fine->target }}</td>
                    <td class="px-3 py-2 font-semibold">{{ number_format($fine->amount) }} {{ $fine->currency }}</td>
                    <td class="px-3 py-2">{{ __('admin.marketer_fines.status_'.$fine->status) }}@if($fine->deducted_at) <span class="text-xs text-gray-400">({{ $fine->deducted_at->format('Y-m-d') }})</span>@endif</td>
                    <td class="px-3 py-2 text-end">
                        @if ($fine->status === 'pending')
                            <form method="POST" action="{{ route('admin.marketer-fines.waive', $fine) }}">
                                @csrf
                                <button class="text-xs text-red-600 hover:underline">{{ __('admin.marketer_fines.waive') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">{{ __('admin.marketer_fines.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $fines->links() }}
</div>
@endsection
