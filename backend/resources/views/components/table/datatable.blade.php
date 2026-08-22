{{--
x-datatable
Props: id, url, columns, filters, bulkActions, createAction,
pageLength, order, selectable, responsive, optionsJson
--}}
<div class="space-y-4" data-datatable-id="{{ $id }}" data-datatable-options='{{ $optionsJson }}'>

    {{-- ── Toolbar ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            {{-- Global search --}}
            <div class="relative">
                <input type="text" id="{{ $id }}-search" placeholder="{{ __('admin.search') }}" autocomplete="off"
                    class="form-input pl-9 w-64 text-sm">
                <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400 pointer-events-none" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>

            {{-- Filters toggle --}}
            @if(count($filters))
                <button type="button" id="{{ $id }}-filter-toggle" class="btn btn-ghost gap-2 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                    </svg>
                    {{ __('admin.filters') }}
                    <span id="{{ $id }}-filter-count" class="hidden badge badge-primary text-xs leading-none px-1.5 py-0.5">
                        0
                    </span>
                </button>
            @endif
        </div>

        {{-- Create button --}}
        @if($createAction)
            <a href="{{ $createAction['url'] }}" class="btn btn-primary gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ $createAction['label'] }}
            </a>
        @endif
    </div>

    {{-- ── Filter panel ────────────────────────────────────────────────── --}}
    @if(count($filters))
        <div id="{{ $id }}-filters" class="hidden">
            <x-card>
                <form id="{{ $id }}-filter-form" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                    onsubmit="return false;">
                    @foreach($filters as $filter)
                        @include('components.table.filter-field', ['filter' => $filter, 'tableId' => $id])
                    @endforeach
                    <div class="flex items-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.apply') }}</button>
                        <button type="button" id="{{ $id }}-clear-filters" class="btn btn-ghost btn-sm">{{ __('admin.clear') }}</button>
                    </div>
                </form>
            </x-card>
        </div>
    @endif

    {{-- ── Bulk action bar ─────────────────────────────────────────────── --}}
    @if($selectable && count($bulkActions))
        <div id="{{ $id }}-bulk-bar" class="hidden items-center gap-3 bg-primary-50 border border-primary-200
                                                            rounded-lg px-4 py-3">
            <span id="{{ $id }}-selected-count" class="text-sm font-medium text-primary-700">
                {{ str_replace(':count', 0, __('admin.selected_count')) }}
            </span>
            <div class="w-px h-4 bg-primary-300"></div>
            @foreach($bulkActions as $action)
                <button type="button" class="btn btn-sm {{ $action['class'] ?? 'btn-ghost' }}"
                    data-bulk-action="{{ $action['id'] }}" data-table="{{ $id }}"
                    data-confirm="{{ $action['confirm'] ?? true }}"
                    data-confirm-message="{{ $action['confirmMessage'] ?? __('admin.confirm_bulk_action_default') }}">
                    {{ $action['label'] }}
                </button>
            @endforeach
            <button type="button" id="{{ $id }}-deselect-all" class="ml-auto text-xs text-primary-600 hover:underline">
                {{ __('admin.deselect_all') }}
            </button>
        </div>
    @endif

    {{-- ── Table card ──────────────────────────────────────────────────── --}}
    <x-card :padding="'none'">
        <div class="overflow-x-auto">
            <table id="{{ $id }}" class="w-full text-sm dt-table" style="width:100%">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        @if($selectable)
                            <th class="w-10 px-4 py-3 text-center">
                                <input type="checkbox" id="{{ $id }}-select-all" class="rounded border-gray-300 text-primary-600
                                                                                      focus:ring-primary-500"
                                    title="{{ __('admin.select_all_page') }}">
                            </th>
                        @endif
                        @foreach($columns as $column)
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold
                                                                               text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                {{ $column['title'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white"></tbody>
            </table>
        </div>
    </x-card>
</div>

@push('scripts')
    <script>
        (function () {
            var rawOptions = @json(json_decode($optionsJson));

            function boot() {
                // Replace the render placeholder strings with actual renderer calls
                // (render must be a function reference, not a JSON string)
                rawOptions.columns = rawOptions.columns.map(function (col) {
                    if (col.render && typeof col.render === 'string') {
                        if (col.render === '__DT_SELECT_CHECKBOX__') {
                            col.render = function (data, type, row) {
                                if (type !== 'display') return '';
                                return '<input type="checkbox"'
                                    + ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"'
                                    + ' value="' + (row.id || '') + '">';
                            };
                        } else {
                            try {
                                // Evaluate the render expression in window scope
                                col.render = eval('(' + col.render + ')');
                            } catch (e) {
                                col.render = null;
                            }
                        }
                    }
                    return col;
                });

                window.initDataTable('{{ $id }}', rawOptions);
            }

            // ES modules loaded via Vite are deferred — DOMContentLoaded fires
            // after all deferred scripts, so initDataTable is guaranteed to exist.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>
@endpush
