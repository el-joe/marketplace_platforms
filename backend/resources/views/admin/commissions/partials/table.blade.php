@php use Illuminate\Support\Facades\Lang; @endphp

<div class="mt-5">
    <div class="mb-2 flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900">{{ __('commissions.tables.'.$table['title']) }}</h3>
        @if ($table['total'] > count($table['rows']))
            <span class="text-xs text-gray-500">{{ __('commissions.showing', ['shown' => count($table['rows']), 'total' => $table['total']]) }}</span>
        @endif
    </div>

    @if ($table['rows'] === [])
        <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach ($table['columns'] as $column)
                            <th scope="col" class="whitespace-nowrap px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.'.$column) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($table['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td class="px-3 py-2 text-gray-800">{{ Lang::has('commissions.values.'.$cell) ? __('commissions.values.'.$cell) : $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
