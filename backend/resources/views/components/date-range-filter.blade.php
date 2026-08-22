{{--
  Usage (inside a GET <form>, e.g. the form wrapped by <x-filter-card>):
    <x-date-range-filter />

  Renders quick preset buttons (7d/30d/90d/YTD/custom) plus custom from/to
  date inputs. Submits `date_from` / `date_to` as GET params on the
  enclosing <form> — it does not render its own <form>.

  Props:
    from (string|null)  — initial date_from value (default: request('date_from'))
    to   (string|null)  — initial date_to value (default: request('date_to'))
--}}
@props([
    'from' => request('date_from'),
    'to'   => request('date_to'),
])

<div class="flex items-center gap-2 flex-wrap" x-data="dateRangeFilter('{{ $from }}', '{{ $to }}')">
    <div class="flex gap-1">
        <button type="button" @click="setRange('7d')" class="btn btn-ghost btn-xs" :class="{ 'btn-primary': preset === '7d' }">{{ __('common.last_7_days') }}</button>
        <button type="button" @click="setRange('30d')" class="btn btn-ghost btn-xs" :class="{ 'btn-primary': preset === '30d' }">{{ __('common.last_30_days') }}</button>
        <button type="button" @click="setRange('90d')" class="btn btn-ghost btn-xs" :class="{ 'btn-primary': preset === '90d' }">{{ __('common.last_90_days') }}</button>
        <button type="button" @click="setRange('ytd')" class="btn btn-ghost btn-xs" :class="{ 'btn-primary': preset === 'ytd' }">{{ __('common.year_to_date') }}</button>
        <button type="button" @click="setRange('custom')" class="btn btn-ghost btn-xs" :class="{ 'btn-primary': preset === 'custom' }">{{ __('common.custom') }}</button>
    </div>
    <div class="flex items-center gap-2" x-show="preset === 'custom'" x-cloak>
        <input type="date" name="date_from" x-model="dateFrom" class="form-input text-sm">
        <span class="text-gray-400">—</span>
        <input type="date" name="date_to" x-model="dateTo" class="form-input text-sm">
    </div>
</div>

<script>
    function dateRangeFilter(initialFrom, initialTo) {
        return {
            preset: initialFrom || initialTo ? 'custom' : '',
            dateFrom: initialFrom || '',
            dateTo: initialTo || '',
            setRange(preset) {
                this.preset = preset;
                const now = new Date();
                if (preset === '7d') { this.dateTo = this.fmt(now); this.dateFrom = this.fmt(new Date(now - 7 * 86400000)); }
                else if (preset === '30d') { this.dateTo = this.fmt(now); this.dateFrom = this.fmt(new Date(now - 30 * 86400000)); }
                else if (preset === '90d') { this.dateTo = this.fmt(now); this.dateFrom = this.fmt(new Date(now - 90 * 86400000)); }
                else if (preset === 'ytd') { this.dateTo = this.fmt(now); this.dateFrom = this.fmt(new Date(now.getFullYear(), 0, 1)); }
                if (preset !== 'custom') this.$el.closest('form').submit();
            },
            fmt(d) { return d.toISOString().split('T')[0]; },
        };
    }
</script>
