@php
    $isAr = app()->getLocale() === 'ar';
    $localName = fn ($model) => $model ? ($isAr ? $model->name_ar : $model->name_en) : '—';
    $statusBadge = [
        'active' => 'bg-emerald-50 text-emerald-700',
        'superseded' => 'bg-gray-100 text-gray-600',
        'revoked' => 'bg-red-50 text-red-600',
    ];
@endphp
<div id="contracts" class="bg-white rounded-xl border shadow-sm overflow-hidden" data-testid="marketer-contracts">
    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center gap-2 justify-between">
        <div class="flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">📑</span>
            <h3 class="font-bold text-gray-800">{{ __('contracts.marketer.profile_title') }}</h3>
        </div>
        <a href="{{ route('admin.marketers.contract.show', $marketer) }}" class="text-xs font-medium text-primary-600 hover:underline">{{ __('contracts.marketer.manage_onboarding') }} →</a>
    </div>

    <form method="GET" action="{{ route('admin.marketers.show', $marketer) }}#contracts" class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_type') }}</label>
            <select name="contract_type" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('admin.marketers.all') }}</option>
                @foreach (\App\Services\Marketer\MarketerProfileContractsService::TYPES as $type)
                    <option value="{{ $type }}" @selected($contractFilters['type'] === $type)>{{ __('contracts.marketer.type_'.$type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_category') }}</label>
            <select name="contract_category" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('admin.marketers.all') }}</option>
                @foreach ($contractCategories as $category)
                    <option value="{{ $category->id }}" @selected($contractFilters['category'] === $category->id)>{{ $localName($category) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_country') }}</label>
            <select name="contract_country" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('admin.marketers.all') }}</option>
                @foreach ($contractCountries as $country)
                    <option value="{{ $country->id }}" @selected($contractFilters['country'] === $country->id)>{{ $localName($country) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_status') }}</label>
            <select name="contract_status" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('admin.marketers.all') }}</option>
                @foreach (\App\Services\Marketer\MarketerProfileContractsService::STATUSES as $status)
                    <option value="{{ $status }}" @selected($contractFilters['status'] === $status)>{{ __('contracts.marketer.state_'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_from') }}</label>
            <input type="date" name="contract_from" value="{{ $contractFilters['from'] }}" class="border rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_to') }}</label>
            <input type="date" name="contract_to" value="{{ $contractFilters['to'] }}" class="border rounded-lg px-3 py-2 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg">{{ __('admin.marketers.search') }}</button>
        @if (array_filter($contractFilters))
            <a href="{{ route('admin.marketers.show', $marketer) }}#contracts" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg">{{ __('admin.marketers.reset') }}</a>
        @endif
    </form>

    {{-- Onboarding contract (marketer <-> platform) --}}
    @if ($onboardingContract)
        <div class="px-6 py-4 border-b border-gray-100" data-testid="onboarding-contract">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ __('contracts.marketer.type_onboarding') }}</h4>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span>{{ $isAr ? ($onboardingContract['version']->title_ar ?: $onboardingContract['version']->title_en) : ($onboardingContract['version']->title_en ?: $onboardingContract['version']->title_ar) }} v{{ $onboardingContract['version']->version_number }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $onboardingContract['accepted'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                    {{ $onboardingContract['accepted'] ? __('contracts.marketer.accepted_on', ['date' => $onboardingContract['accepted_at']?->format('Y-m-d')]) : __('contracts.marketer.not_accepted') }}
                </span>
            </div>
        </div>
    @endif

    {{-- Collaboration contracts that apply to this marketer --}}
    <div class="px-6 py-4 border-b border-gray-100" data-testid="applicable-contracts">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ __('contracts.marketer.applicable_title') }}</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs"><tr>
                    <th class="px-3 py-2 text-start">{{ __('admin.contracts.col_name') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('contracts.marketer.display_on') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('contracts.admin.col_country') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('contracts.marketer.category') }}</th>
                    <th class="px-3 py-2 text-center">{{ __('admin.contracts.col_version') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('contracts.marketer.effective') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($applicableContracts as $template)
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $template->name }}</td>
                        <td class="px-3 py-2 text-xs">{{ $template->displayDestinations->map->label()->implode(', ') ?: '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $template->country ? $localName($template->country) : __('contracts.admin.legacy_country') }}</td>
                        <td class="px-3 py-2 text-xs">{{ $template->category ? $localName($template->category) : __('contracts.marketer.category_any') }}</td>
                        <td class="px-3 py-2 text-center">v{{ $template->version }}</td>
                        <td class="px-3 py-2 text-xs">{{ $template->effective_at?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">{{ __('contracts.marketer.no_applicable') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($applicableContracts->hasPages())<div class="pt-3">{{ $applicableContracts->fragment('contracts')->links() }}</div>@endif
    </div>

    {{-- Signed by sellers --}}
    <div class="px-6 py-4" data-testid="signed-contracts">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ __('contracts.marketer.signed_title') }}</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs"><tr>
                    <th class="px-3 py-2 text-start">{{ __('admin.contracts.col_name') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('contracts.marketer.col_seller') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('contracts.admin.col_country') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('contracts.marketer.category') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('contracts.marketer.col_signed_at') }}</th>
                    <th class="px-3 py-2 text-center">{{ __('admin.contracts.col_status') }}</th>
                    <th class="px-3 py-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($signedContracts as $signed)
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $signed->template?->name }} <span class="text-xs text-gray-400">v{{ $signed->template_version }}</span></td>
                        <td class="px-3 py-2">{{ $signed->vendor?->store_name }}</td>
                        <td class="px-3 py-2 text-xs">{{ $signed->country ? $localName($signed->country) : '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $signed->template?->category ? $localName($signed->template->category) : '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ admin_tz($signed->signed_at)?->format('Y-m-d') }}</td>
                        <td class="px-3 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-xs {{ $statusBadge[$signed->status] ?? 'bg-gray-100' }}">{{ __('contracts.marketer.status_'.$signed->status) }}</span></td>
                        <td class="px-3 py-2 text-end"><a class="text-xs text-primary-600 hover:underline" href="{{ route('admin.marketers.collaboration-contracts.show', [$marketer, $signed]) }}">{{ __('contracts.marketer.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">{{ __('contracts.marketer.no_signed') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($signedContracts->hasPages())<div class="pt-3">{{ $signedContracts->fragment('contracts')->links() }}</div>@endif
    </div>
</div>
