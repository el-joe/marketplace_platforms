<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class OrderListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Comma-separated list of statuses (e.g. "placed,confirmed") to
            // support the vendor app's grouped filter tabs alongside exact
            // single-status filtering.
            'status'    => ['nullable', 'string', 'regex:/^[a-z_]+(,[a-z_]+)*$/'],
            // "Issues" tab: SLA-breached orders OR cancelled/returned/refunded, regardless of `status`.
            'issues'    => ['nullable', 'boolean'],
            'search'    => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return string[]|null */
    public function statuses(): ?array
    {
        if (! $this->filled('status')) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $this->string('status')))));
    }
}
