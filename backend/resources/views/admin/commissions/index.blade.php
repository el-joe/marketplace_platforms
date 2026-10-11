@extends('layouts.admin')

@section('title', __('commissions.title'))

@php
    use App\Services\Admin\Commissions\CommissionTabRegistry as Registry;

    $effectColor = [
        Registry::EFFECT_APPLIED => 'success',
        Registry::EFFECT_SYSTEM => 'primary',
        Registry::EFFECT_NOT_APPLIED => 'danger',
        Registry::EFFECT_ANALYTICS => 'warning',
        Registry::EFFECT_REPORT => 'warning',
        Registry::EFFECT_REFERENCE => 'gray',
    ];
    $toneClass = [
        'info' => 'border-blue-200 bg-blue-50 text-blue-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-red-200 bg-red-50 text-red-900',
    ];
    $activeGroup = $tab['group'];
    // Shared tokens for the in-page editors (tab partials inherit these).
    $input = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 aria-[invalid=true]:border-red-500';
    $label = 'block text-xs font-medium text-gray-600';
    $btnPrimary = 'rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-60';
    $btnLink = 'text-primary-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';
    $btnDanger = 'text-danger-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500';
    $tabPartial = 'admin.commissions.tabs.'.$activeKey;
    $fieldError = fn (string $name) => $errors->first($name);
    $firstTabOf = fn (array $tabs) => array_key_first($tabs);
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('commissions.title') }}</h1>
        <p class="mt-0.5 text-sm text-gray-500">{{ __('commissions.subtitle') }}</p>
        <p class="mt-1 text-xs text-gray-400">{{ __('commissions.money_note') }}</p>
    </div>

    <div data-flash role="status" class="mb-6 hidden rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"></div>
    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Primary navigation: one entry per group. Switching keeps the user on /commissions (?tab=). --}}
    <nav aria-label="{{ __('commissions.groups_label') }}" class="mb-3 overflow-x-auto">
        <ul class="flex min-w-max gap-2 border-b border-gray-200">
            @foreach ($groups as $groupKey => $groupTabs)
                @php $isActiveGroup = $groupKey === $activeGroup; @endphp
                <li>
                    <a href="{{ route('admin.commissions.index', ['tab' => $isActiveGroup ? $activeKey : $firstTabOf($groupTabs)]) }}"
                       @if ($isActiveGroup) aria-current="true" @endif
                       class="-mb-px inline-block whitespace-nowrap border-b-2 px-4 py-2 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500
                              {{ $isActiveGroup ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                        {{ __('commissions.groups.'.$groupKey) }}
                        <span class="ms-1 text-xs font-normal text-gray-400">{{ count($groupTabs) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Secondary navigation: one entry per commission type in the active group. --}}
    <nav aria-label="{{ __('commissions.tabs_label') }}" class="mb-6 overflow-x-auto">
        <ul class="flex min-w-max gap-2">
            @foreach ($groups[$activeGroup] as $tabKey => $groupTab)
                @php $isActive = $tabKey === $activeKey; @endphp
                <li>
                    <a href="{{ route('admin.commissions.index', ['tab' => $tabKey]) }}"
                       @if ($isActive) aria-current="page" @endif
                       class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-3 py-1.5 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500
                              {{ $isActive ? 'border-primary-600 bg-primary-50 font-semibold text-primary-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                        <span class="text-xs text-gray-400">#{{ $groupTab['item'] }}</span>
                        {{ __('commissions.tabs.'.$tabKey.'.title') }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <section aria-labelledby="commission-tab-title" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <header class="mb-5">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="commission-tab-title" class="text-lg font-semibold text-gray-900">{{ __('commissions.tabs.'.$activeKey.'.title') }}</h2>
                <x-badge :color="$effectColor[$tab['effect']] ?? 'gray'" size="md">{{ __('commissions.effects.'.$tab['effect']) }}</x-badge>
                <x-badge color="gray" size="md">{{ __('commissions.access.'.$tab['access']) }}</x-badge>
            </div>
            <p class="mt-2 text-sm text-gray-600">{{ __('commissions.tabs.'.$activeKey.'.summary') }}</p>
            <p class="mt-1 text-xs text-gray-400">{{ __('commissions.effects_help.'.$tab['effect']) }}</p>

            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('commissions.who_pays') }}</dt>
                    <dd class="mt-0.5 text-gray-800">{{ __('commissions.tabs.'.$activeKey.'.pays') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('commissions.who_receives') }}</dt>
                    <dd class="mt-0.5 text-gray-800">{{ __('commissions.tabs.'.$activeKey.'.receives') }}</dd>
                </div>
            </dl>
        </header>

        @foreach ($content['notices'] as [$tone, $noticeKey])
            <div class="mb-3 rounded-lg border px-4 py-3 text-sm {{ $toneClass[$tone] ?? $toneClass['info'] }}" role="note">
                {{ __('commissions.notices.'.$noticeKey) }}
            </div>
        @endforeach

        @if ($content['stats'] !== [])
            <dl class="my-5 grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ($content['stats'] as [$statKey, $statValue])
                    @php [$statBase, $statSuffix] = array_pad(explode(':', $statKey, 2), 2, null); @endphp
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <dt class="text-xs text-gray-500">{{ __('commissions.stats.'.$statBase) }}@if ($statSuffix): {{ $statSuffix }}@endif</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900">{{ \Illuminate\Support\Facades\Lang::has('commissions.values.'.$statValue) ? __('commissions.values.'.$statValue) : $statValue }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if (view()->exists($tabPartial))
            @include($tabPartial)
        @else
            @foreach ($content['tables'] as $table)
                @include('admin.commissions.partials.table', ['table' => $table])
            @endforeach
        @endif

        @if ($activeKey === 'related-fees')
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach (__('commissions.related_columns') as $heading)
                                <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach (__('commissions.related_reference') as [, $feeName, $configuredIn, $treatment])
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $feeName }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $configuredIn }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $treatment }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($tab['manage'] !== [])
            <footer class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                @foreach ($tab['manage'] as $link)
                    <a href="{{ route($link['route']) }}"
                       class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        {{ __('commissions.manage.'.$link['label']) }}
                    </a>
                @endforeach
            </footer>
        @endif
    </section>
@endsection

@push('scripts')
    @include('admin.commissions.partials.scripts')
@endpush
