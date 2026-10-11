<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommissionRateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a row of the `commissions` table.
 *
 * `rate_type` is limited to `flat`: CheckoutPricingEngine applies every matching row as `rate_pct` and
 * never reads the tiered type, so offering it would imply a behaviour that does not exist.
 */
class StoreVendorCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Scoped (assigned-only) admins may read this page but never write platform commission rules.
        return $this->user('admin')?->can('commission_rules.manage') === true
            && $this->attributes->get('is_scoped_admin') !== true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['nullable', 'uuid', Rule::exists('vendors', 'id')],
            'category_id' => ['nullable', 'uuid', Rule::exists('categories', 'id')],
            'rate_pct' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'rate_type' => ['required', Rule::in([CommissionRateType::Flat->value])],
            'min_commission' => ['nullable', 'integer', 'min:0'],
            'max_commission' => ['nullable', 'integer', 'min:0', 'gte:min_commission'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            // A row with neither vendor nor category outranks vendors.commission_rate and every category rate.
            'confirm_platform_wide' => [Rule::excludeIf(fn () => filled($this->input('vendor_id')) || filled($this->input('category_id'))), 'accepted'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleData(): array
    {
        $data = $this->validated();
        unset($data['confirm_platform_wide']);

        $data['min_commission'] = (int) ($data['min_commission'] ?? 0);
        $data['priority'] = (int) ($data['priority'] ?? 0);

        return $data + ['vendor_id' => null, 'category_id' => null, 'max_commission' => null, 'effective_until' => null];
    }
}
