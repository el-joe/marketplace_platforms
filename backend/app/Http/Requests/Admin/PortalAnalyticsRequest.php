<?php

namespace App\Http\Requests\Admin;

use App\Enums\RegistrationFlow;
use App\Services\PortalAnalytics\PortalAnalyticsFilters;
use App\Services\PortalAnalytics\PortalAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortalAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is guarded by admin.permission:portal_analytics.view
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'granularity' => ['nullable', Rule::in(PortalAnalyticsFilters::GRANULARITIES)],
            'country' => ['nullable', 'string', 'max:20', Rule::exists('countries', 'site_code')],
            'flow' => ['nullable', Rule::in(RegistrationFlow::values())],
            'compare' => ['nullable', 'boolean'],
            'export' => ['nullable', Rule::in(['csv', 'excel', 'word'])],
            'section' => ['nullable', Rule::in(PortalAnalyticsService::EXPORT_SECTIONS)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $filters = $this->filters();

            if ($filters->days() > (int) config('portal_analytics.max_range_days', 731)) {
                $validator->errors()->add('date_to', __('admin.portal_analytics.range_too_long', ['days' => config('portal_analytics.max_range_days', 731)]));
            }
        });
    }

    public function filters(): PortalAnalyticsFilters
    {
        $default = PortalAnalyticsFilters::default();

        return new PortalAnalyticsFilters(
            from: $this->filled('date_from') ? CarbonImmutable::createFromFormat('Y-m-d', $this->input('date_from')) : $default->from,
            to: $this->filled('date_to') ? CarbonImmutable::createFromFormat('Y-m-d', $this->input('date_to')) : $default->to,
            granularity: (string) $this->input('granularity', $default->granularity),
            countryCode: $this->filled('country') ? (string) $this->input('country') : null,
            flow: RegistrationFlow::tryFrom((string) $this->input('flow')) ?? $default->flow,
            compare: $this->has('compare') ? $this->boolean('compare') : $default->compare,
        );
    }
}
