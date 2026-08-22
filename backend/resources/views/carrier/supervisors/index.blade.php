@extends('layouts.carrier')

@section('title', __('carrier.supervisors.title'))

@section('content')

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.supervisors.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('carrier.supervisors.subtitle') }}</p>
    </div>
    <a href="{{ route('carrier.supervisors.create') }}"
       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-4 py-2 rounded-lg transition">
        + {{ __('carrier.supervisors.add_supervisor') }}
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($supervisors->isEmpty())
    <div class="py-16 text-center text-gray-400 text-sm">{{ __('carrier.supervisors.no_supervisors') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.supervisors.name') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.supervisors.email') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.supervisors.permissions') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.supervisors.notifications') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.supervisors.status') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.supervisors.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($supervisors as $sup)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $sup->name }}</td>
                    <td class="px-6 py-3 text-gray-500">{{ $sup->email }}</td>
                    <td class="px-6 py-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach($sup->permissions ?? [] as $perm)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">{{ $perm }}</span>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-6 py-3">
                        @if($sup->receives_all_notifications)
                        <span class="text-xs text-emerald-600 font-medium">✓ {{ __('carrier.supervisors.enabled') }}</span>
                        @else
                        <span class="text-xs text-gray-400">{{ __('carrier.supervisors.disabled') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3">
                        @if($sup->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-700">{{ __('carrier.supervisors.active') }}</span>
                        @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">{{ __('carrier.supervisors.suspended') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 flex items-center gap-3">
                        <a href="{{ route('carrier.supervisors.edit', $sup->id) }}"
                           class="text-indigo-600 hover:underline text-xs font-medium">{{ __('carrier.common.edit') }}</a>
                        @if($sup->id !== auth('shipping_supervisor')->id())
                        <form method="POST" action="{{ route('carrier.supervisors.destroy', $sup->id) }}"
                              onsubmit="return confirm('{{ __('carrier.supervisors.confirm_delete') }}')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:underline text-xs font-medium">{{ __('carrier.common.delete') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $supervisors->links() }}
    </div>
    @endif
</div>

@endsection
