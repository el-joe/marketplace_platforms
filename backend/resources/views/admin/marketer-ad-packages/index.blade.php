@extends('layouts.admin')
@section('title', __('ad_packages.ad_packages'))

@section('content')
<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">{{ __('ad_packages.ad_packages') }}</h1>
        <a href="{{ route('admin.marketer-ad-packages.create') }}" class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white">{{ __('ad_packages.new_package') }}</a>
    </div>
    @if(session('success'))<div class="p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="p-3 text-start">{{ __('ad_packages.name') }}</th><th class="p-3 text-start">{{ __('ad_packages.package_price') }}</th>
                <th class="p-3 text-start">VAT %</th><th class="p-3 text-start">{{ __('ad_packages.target') }}</th>
                <th class="p-3 text-start">{{ __('ad_packages.duration') }}</th><th class="p-3 text-start">{{ __('ad_packages.active') }}</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($packages as $p)
                <tr class="border-t">
                    <td class="p-3">{{ $p->name_ar }} <span class="text-gray-400">{{ $p->name_en }}</span></td>
                    <td class="p-3">{{ number_format($p->price) }} {{ $p->currency }}</td>
                    <td class="p-3">{{ $p->vat_pct }}</td><td class="p-3">{{ $p->target_type }}</td>
                    <td class="p-3">{{ $p->duration_days }}</td><td class="p-3">{{ $p->is_active ? '✓' : '—' }}</td>
                    <td class="p-3 flex gap-3">
                        <a class="text-blue-600" href="{{ route('admin.marketer-ad-packages.edit', $p->id) }}">{{ __('ad_packages.edit') }}</a>
                        <form method="POST" action="{{ route('admin.marketer-ad-packages.destroy', $p->id) }}" onsubmit="return confirm('?')">@csrf @method('DELETE')<button class="text-red-600">{{ __('ad_packages.delete') }}</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-6 text-center text-gray-500">{{ __('ad_packages.no_packages') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="text-lg font-bold">{{ __('ad_packages.pending_subscriptions') }}</h2>
    <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="p-3 text-start">{{ __('ad_packages.marketer') }}</th><th class="p-3 text-start">{{ __('ad_packages.name') }}</th>
                <th class="p-3 text-start">{{ __('ad_packages.total_with_vat') }}</th><th class="p-3 text-start">{{ __('ad_packages.payment_proof') }}</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($pending as $s)
                <tr class="border-t">
                    <td class="p-3">{{ $s->marketer?->name ?? $s->marketer_id }}</td>
                    <td class="p-3">{{ $s->package?->name_ar }}</td>
                    <td class="p-3">{{ number_format($s->amount_paid) }} {{ $s->currency }}</td>
                    <td class="p-3">@if($s->payment_proof_path)<a class="text-blue-600" href="{{ route('admin.marketer-ad-packages.proof', $s->id) }}">{{ __('ad_packages.view') }}</a>@endif</td>
                    <td class="p-3 flex gap-2">
                        <form method="POST" action="{{ route('admin.marketer-ad-packages.approve', $s->id) }}">@csrf<button class="px-3 py-1 rounded bg-green-600 text-white">{{ __('ad_packages.approve') }}</button></form>
                        <form method="POST" action="{{ route('admin.marketer-ad-packages.reject', $s->id) }}" onsubmit="return confirm('?')">@csrf<button class="px-3 py-1 rounded bg-red-600 text-white">{{ __('ad_packages.reject') }}</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-gray-500">{{ __('ad_packages.none_pending') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="text-lg font-bold">{{ __('ad_packages.recent_subscriptions') }}</h2>
    <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="min-w-full text-sm"><tbody>
        @foreach($recent as $s)
            <tr class="border-t"><td class="p-3">{{ $s->marketer?->name ?? $s->marketer_id }}</td><td class="p-3">{{ $s->package?->name_ar }}</td>
            <td class="p-3">{{ number_format($s->amount_paid) }} {{ $s->currency }}</td><td class="p-3">{{ $s->status }}</td>
            <td class="p-3">{{ $s->expires_at?->format('Y-m-d') }}</td></tr>
        @endforeach
        </tbody></table>
    </div>
</div>
@endsection
