<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductLinkCommissionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductLinkCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('product_link_commission.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'fulfillment_models' => [Rule::requiredIf($this->boolean('is_active')), 'array'],
            'fulfillment_models.*' => ['string', Rule::in(ProductLinkCommissionRule::FULFILLMENT_MODELS)],
            'discount_type' => ['required', Rule::in(ProductLinkCommissionRule::TYPES)],
            'discount_percent' => ['nullable', 'required_if:discount_type,percent,both', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'discount_fixed' => ['nullable', 'required_if:discount_type,fixed,both', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'attribution_window_days' => ['required', 'integer', 'min:1', 'max:'.ProductLinkCommissionRule::MAX_WINDOW_DAYS],
        ];
    }

    /** The part(s) the chosen type uses must be above zero ("zero" is the type that waives everything). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $type = $this->input('discount_type');
                if (in_array($type, [ProductLinkCommissionRule::TYPE_PERCENT, ProductLinkCommissionRule::TYPE_BOTH], true) && (float) $this->input('discount_percent') <= 0) {
                    $validator->errors()->add('discount_percent', __('product_link.admin.errors.amount_required'));
                }
                if (in_array($type, [ProductLinkCommissionRule::TYPE_FIXED, ProductLinkCommissionRule::TYPE_BOTH], true) && (float) $this->input('discount_fixed') <= 0) {
                    $validator->errors()->add('discount_fixed', __('product_link.admin.errors.amount_required'));
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fulfillment_models' => __('product_link.admin.fields.sale_models'),
            'discount_type' => __('product_link.admin.fields.discount_type'),
            'discount_percent' => __('product_link.admin.fields.discount_percent'),
            'discount_fixed' => __('product_link.admin.fields.discount_fixed'),
            'attribution_window_days' => __('product_link.admin.fields.window'),
        ];
    }
}
