@extends('layouts.admin')
@section('title', __('admin.contracts.templates_title'))

@section('content')
<div class="p-6 space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.contracts.templates_title') }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">{{ __('admin.contracts.templates_desc') }}</p>
        </div>
        <a href="{{ route('admin.contracts.templates.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            {{ __('admin.contracts.new_template') }}
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($missingAgreementCountries->isNotEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">{{ __('contracts.admin.missing_agreement_title') }}</p>
            <p class="mt-1">{{ __('contracts.admin.missing_agreement_body', ['countries' => $missingAgreementCountries->map(fn ($country) => app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en)->implode(', ')]) }}</p>
        </div>
    @endif

    @if ($uncoveredSections->isNotEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">{{ __('contracts.sections.uncovered_title') }}</p>
            <p class="mt-1">{{ __('contracts.sections.uncovered_body') }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($uncoveredSections as $section)
                    <a href="{{ route('admin.classifieds.categories.index') }}"
                       class="rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">{{ app()->getLocale() === 'ar' ? $section->name_ar : $section->name_en }}</a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($uncoveredMarketerDestinations->isNotEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="uncovered-marketer-types">
            <p class="font-semibold">{{ __('contracts.marketer.uncovered_title') }}</p>
            <p class="mt-1">{{ __('contracts.marketer.uncovered_body', ['types' => $uncoveredMarketerDestinations->map->label()->implode(', ')]) }}</p>
        </div>
    @endif

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4 text-sm">
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('admin.contracts.col_scope') }}</label>
            <select name="scope" class="form-input text-sm">
                <option value="">{{ __('admin.contracts.filter_all') }}</option>
                @foreach (['classified' => 'filter_classified', 'product' => 'filter_product', 'platform' => 'filter_platform', 'marketer' => 'filter_marketer', 'customer' => 'filter_customer'] as $value => $label)
                    <option value="{{ $value }}" @selected($scope === $value)>{{ __('admin.contracts.' . $label) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.marketer.kind') }}</label>
            <select name="kind" class="form-input text-sm">
                <option value="">{{ __('admin.contracts.filter_all') }}</option>
                @foreach (\App\Enums\ContractKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected($kindFilter === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.marketer.display_on') }}</label>
            <select name="destination" class="form-input text-sm">
                <option value="">{{ __('admin.contracts.filter_all') }}</option>
                @foreach ($destinations as $destination)
                    <option value="{{ $destination->key }}" @selected(request('destination') === $destination->key)>{{ $destination->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('admin.contracts.col_status') }}</label>
            <select name="status" class="form-input text-sm">
                <option value="">{{ __('admin.contracts.filter_all') }}</option>
                @foreach (['draft' => 'status_draft', 'published' => 'status_published', 'retired' => 'status_retired'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ __('admin.contracts.' . $label) }}</option>
                @endforeach
                <option value="scheduled" @selected($status === 'scheduled')>{{ __('contracts.admin.status_scheduled_filter') }}</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.admin.col_country') }}</label>
            <select name="country" class="form-input text-sm">
                <option value="">{{ __('contracts.admin.all_countries_filter') }}</option>
                <option value="legacy" @selected($countryFilter === 'legacy')>{{ __('contracts.admin.legacy_country') }}</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected($countryFilter === $country->id)>{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.sections.filter_label') }}</label>
            <select name="section" class="form-input text-sm">
                <option value="">{{ __('contracts.sections.filter_all') }}</option>
                @foreach ($sectionOptions as $root)
                    <option value="{{ $root->id }}" @selected($sectionFilter === $root->id)>{{ app()->getLocale() === 'ar' ? $root->name_ar : $root->name_en }}</option>
                    @foreach ($root->children as $child)
                        <option value="{{ $child->id }}" @selected($sectionFilter === $child->id)>— {{ app()->getLocale() === 'ar' ? $child->name_ar : $child->name_en }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.contracts.apply') }}</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="table-responsive">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_name') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_scope') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('contracts.marketer.display_on') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('contracts.admin.col_country') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('contracts.sections.col_sections') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_version') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_status') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_assigned') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('admin.contracts.signatures') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($templates as $template)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ $template->name }}
                                @if ($template->isFileMode())
                                    <span class="ms-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">{{ __('admin.contracts.mode_file_badge') }}</span>
                                    @foreach (['ar', 'en'] as $language)
                                        @if (isset($template->files[$language]))
                                            <a href="{{ route('admin.contracts.templates.file', [$template, $language]) }}" target="_blank" class="ms-1 text-[10px] font-semibold uppercase text-primary-600 hover:underline">{{ $language }}</a>
                                        @endif
                                    @endforeach
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ __('admin.contracts.scope_' . $template->category_scope) }}
                                <div class="text-[11px] text-gray-400">{{ $template->contract_kind?->label() }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($template->isMarketerScope())
                                    <div class="flex max-w-xs flex-wrap gap-1">
                                        @foreach ($template->displayDestinations as $destination)
                                            <span class="rounded-full bg-purple-50 px-2 py-0.5 text-[11px] font-medium text-purple-700">{{ $destination->label() }}</span>
                                        @endforeach
                                        @if ($template->marketers_count > 0)
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700">{{ __('contracts.marketer.n_marketers', ['count' => $template->marketers_count]) }}</span>
                                        @endif
                                        @if ($template->category)
                                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] text-indigo-700">{{ app()->getLocale() === 'ar' ? $template->category->name_ar : $template->category->name_en }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($template->country)
                                    {{ app()->getLocale() === 'ar' ? $template->country->name_ar : $template->country->name_en }}
                                @else
                                    <span class="text-gray-400">{{ __('contracts.admin.legacy_country') }}</span>
                                @endif
                                @if ($template->is_country_default)
                                    <span class="ms-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">{{ __('contracts.admin.default_badge') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php($linkedSections = $sectionBadges[$template->id] ?? collect())
                                @if ($linkedSections->isEmpty())
                                    <span class="text-gray-300">—</span>
                                @else
                                    <div class="flex max-w-xs flex-wrap gap-1">
                                        @foreach ($linkedSections->take(3) as $section)
                                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700">{{ app()->getLocale() === 'ar' ? $section->name_ar : $section->name_en }}</span>
                                        @endforeach
                                        @if ($linkedSections->count() > 3)
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600" title="{{ $linkedSections->skip(3)->map(fn ($section) => app()->getLocale() === 'ar' ? $section->name_ar : $section->name_en)->implode(', ') }}">{{ __('contracts.sections.more', ['count' => $linkedSections->count() - 3]) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">v{{ $template->version }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($template->isScheduled())
                                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">{{ __('admin.contracts.status_scheduled', ['date' => admin_tz($template->effective_at)?->format('d/m/Y')]) }}</span>
                                @elseif ($template->is_published)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('admin.contracts.status_published') }}</span>
                                @elseif ($template->is_active)
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ __('admin.contracts.status_draft') }}</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ __('admin.contracts.status_retired') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">{{ $assignedCounts[$template->id] ?? 0 }}</td>
                            <td class="px-4 py-3 text-center">{{ $signatureCounts[$template->id] ?? 0 }}</td>
                            <td class="px-4 py-3 text-end whitespace-nowrap space-x-3">
                                <a href="{{ route('admin.contracts.templates.signatures', $template) }}" class="text-xs font-medium text-primary-600 hover:underline">{{ __('admin.contracts.signatures') }}</a>
                                <a href="{{ route('admin.contracts.templates.edit', $template) }}" class="text-xs font-medium text-primary-600 hover:underline">{{ __('common.edit') }}</a>
                                @if (! $template->is_published && $template->is_active)
                                    <form method="POST" action="{{ route('admin.contracts.templates.publish', $template) }}" class="inline"
                                          onsubmit="return confirm(@js(__('admin.contracts.publish_confirm')))">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('admin.contracts.publish') }}</button>
                                    </form>
                                @endif
                                @if ($template->is_published && $template->country_id && ! $template->isPlatformScope())
                                    <form method="POST" action="{{ route('admin.contracts.templates.default', $template) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="default" value="{{ $template->is_country_default ? 0 : 1 }}">
                                        <button type="submit" class="text-xs font-medium text-indigo-700 hover:underline">{{ $template->is_country_default ? __('contracts.admin.unset_default') : __('contracts.admin.set_default') }}</button>
                                    </form>
                                @endif
                                @if ($template->is_published)
                                    @php($signatureCount = (int) ($signatureCounts[$template->id] ?? 0))
                                    <form method="POST" action="{{ route('admin.contracts.templates.retire', $template) }}" class="inline"
                                          onsubmit="return confirm(@js($signatureCount > 0 ? __('contracts.admin.retire_confirm_signed', ['count' => $signatureCount]) : __('contracts.admin.retire_confirm')))">
                                        @csrf
                                        @if ($signatureCount > 0)<input type="hidden" name="confirm_signatures" value="1">@endif
                                        <button type="submit" class="text-xs font-medium text-amber-700 hover:underline">{{ __('contracts.admin.retire') }}</button>
                                    </form>
                                @elseif (! $template->is_active)
                                    <form method="POST" action="{{ route('admin.contracts.templates.reactivate', $template) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-gray-600 hover:underline">{{ __('contracts.admin.reactivate') }}</button>
                                    </form>
                                @endif
                                @if (! $template->is_published && (int) ($signatureCounts[$template->id] ?? 0) === 0)
                                    <form method="POST" action="{{ route('admin.contracts.templates.destroy', $template) }}" class="inline"
                                          onsubmit="return confirm(@js(__('admin.contracts.delete_confirm')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline">{{ __('common.delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-gray-400">{{ __('admin.contracts.no_templates') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $templates->links() }}</div>
</div>
@endsection
