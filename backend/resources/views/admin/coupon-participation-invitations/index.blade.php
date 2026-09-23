@extends('layouts.admin')

@section('title', __('admin.coupon_participation_section.cps_title'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold text-gray-900">{{ __('admin.coupon_participation_section.cps_title') }}</h1>
        <a href="{{ route('admin.coupon-participation-invitations.create') }}" class="btn btn-primary">{{ __('admin.coupon_participation_section.cps_new') }}</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_title_col') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_status') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_min_fee') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_approved_participants') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_total_requests') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('admin.coupon_participation_section.cps_deadline') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($invitations as $invitation)
                <tr>
                    <td class="px-4 py-2">{{ $invitation->title ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $invitation->status }}</td>
                    <td class="px-4 py-2">{{ number_format($invitation->min_fee_amount) }} {{ $invitation->currency }}</td>
                    <td class="px-4 py-2">{{ $invitation->approved_requests_count }} / {{ $invitation->max_participants }}</td>
                    <td class="px-4 py-2">{{ $invitation->requests_count }}</td>
                    <td class="px-4 py-2">{{ $invitation->registration_deadline->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-2 text-end">
                        <a href="{{ route('admin.coupon-participation-invitations.show', $invitation->id) }}" class="text-blue-600">{{ __('admin.coupon_participation_section.cps_view') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">{{ __('admin.coupon_participation_section.cps_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $invitations->links() }}</div>
</div>
@endsection
