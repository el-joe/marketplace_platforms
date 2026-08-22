@extends('layouts.admin')

@section('title', __('admin.travel.agencies'))

@section('content')
    <div class="p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ __('admin.travel.agencies') }}</h1>
                @if ($pendingCount > 0)
                    <p class="text-sm text-amber-600 font-medium mt-0.5">
                        {{ __('admin.travel.pending_approval_count', ['count' => $pendingCount]) }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('admin.travel.search_name_email') }}"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-64">
            <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">{{ __('admin.travel.all_statuses') }}</option>

                @foreach ([
            'pending' => __('admin.travel.status.pending'),
            'active' => __('admin.travel.status.active'),
            'suspended' => __('admin.travel.status.suspended'),
        ] as $value => $label)
                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <select name="country_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">{{ __('admin.travel.all_countries') }}</option>
                @foreach ($countries as $c)
                    <option value="{{ $c->id }}" {{ request('country_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->name_en }}
                    </option>
                @endforeach
            </select>
            <button type="submit"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">{{ __('common.filters') }}</button>
            <a href="{{ route('admin.travel.agencies.index') }}"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('common.reset') }}</a>
        </form>

        <div id="agencies-table-wrap" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.agency') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.email') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.country') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.packages') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.status') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.registered') }}
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="agencies-tbody" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">{{ __('common.loading') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="agencies-pagination"
                class="px-4 py-3 border-t border-gray-100 flex items-center gap-2 text-sm text-gray-500"></div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.TRANSLATIONS = {
                confirm_action: "{{ __('common.confirm') }}",
                success: "{{ __('common.success') }}",
                error: "{{ __('common.error') }}",
                no_agencies_found: "{{ __('admin.travel.no_agencies_found') }}",
                view: "{{ __('common.view') }}",
                page_of: "{{ __('common.page') }} %s {{ __('common.of') }} %s",
                agencies_label: "{{ __('admin.travel.agencies') }}",
            };

            const statusColors = {
                pending: 'bg-amber-100 text-amber-700',
                active: 'bg-emerald-100 text-emerald-700',
                suspended: 'bg-red-100 text-red-700',
            };
            const statusLabels = {
                pending: "{{ __('admin.travel.status.pending') }}",
                active: "{{ __('admin.travel.status.active') }}",
                suspended: "{{ __('admin.travel.status.suspended') }}",
            };

            let currentPage = 1;

            async function loadAgencies(page = 1) {
                currentPage = page;
                const params = new URLSearchParams({
                    page,
                    q: document.querySelector('[name=q]').value,
                    status: document.querySelector('[name=status]').value,
                    country_id: document.querySelector('[name=country_id]').value,
                });

                const res = await fetch(`{{ route('admin.travel.agencies.datatable') }}?${params}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const json = await res.json();

                const tbody = document.getElementById('agencies-tbody');
                tbody.innerHTML = json.data.length === 0 ?
                    `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">${window.TRANSLATIONS.no_agencies_found}</td></tr>` :
                    json.data.map(a => `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">${a.name}</td>
                        <td class="px-4 py-3 text-gray-500">${a.email}</td>
                        <td class="px-4 py-3 text-gray-500">${a.country ?? '—'}</td>
                        <td class="px-4 py-3 text-gray-500">${a.packages}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[a.status] ?? ''}">
                                ${statusLabels[a.status] ?? a.status}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">${a.created_at}</td>
                        <td class="px-4 py-3">
                            <a href="/travel/agencies/${a.id}" class="text-primary-600 text-xs font-medium hover:underline">${window.TRANSLATIONS.view}</a>
                        </td>
                    </tr>`).join('');

                // Pagination
                const pg = json.meta;
                const div = document.getElementById('agencies-pagination');
                const pageOf = window.TRANSLATIONS.page_of.replace('%s', pg.current_page).replace('%s', pg.last_page);
                div.innerHTML = `${pageOf} &nbsp;·&nbsp; ${pg.total} ${window.TRANSLATIONS.agencies_label}`;
            }

            document.addEventListener('DOMContentLoaded', () => loadAgencies());
        </script>
    @endpush
@endsection
