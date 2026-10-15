<?php

namespace App\Http\Requests\Admin;

use App\Enums\MarketerType;
use App\Models\Marketer;
use App\Models\MarketerCommissionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Admin > marketer profile > per-category commission rule.
 *
 * Type constraints (server side, mirrored by the disabled inputs in the UI):
 *   influencer: a fixed amount per campaign only (percentage rejected);
 *   affiliate:  a percentage only (fixed amount rejected);
 *   broker:     fixed, percentage, or both.
 */
class StoreMarketerCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth('admin')->user()?->can('marketers.manage');
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', 'in:'.implode(',', MarketerCommissionRule::SCOPES)],
            'marketer_type' => ['nullable', 'in:'.implode(',', MarketerType::values())],
            'category_id' => ['nullable', 'uuid'],
            'excluded_category_ids' => ['nullable', 'array'],
            'excluded_category_ids.*' => ['uuid'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_flat_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'commission_min_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'fee_fixed' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
        ];
    }

    public function attributes(): array
    {
        return [
            'commission_rate' => __('marketer_commissions.field_percentage'),
            'commission_flat_amount' => __('marketer_commissions.field_fixed'),
            'commission_min_amount' => __('marketer_commissions.field_min'),
            'fee_percentage' => __('marketer_commissions.field_fee_percentage'),
            'fee_fixed' => __('marketer_commissions.field_fee_fixed'),
        ];
    }

    /** The marketer type this rule is written for, or null when the marketer holds no v3 type (legacy job). */
    public function marketerType(): ?MarketerType
    {
        $held = MarketerType::heldBy($this->marketer());
        $requested = $this->input('marketer_type');

        if ($requested) {
            $type = MarketerType::tryFrom($requested);

            return $type && in_array($type, $held, true) ? $type : null;
        }

        return $held[0] ?? null;
    }

    public function marketer(): Marketer
    {
        /** @var Marketer $marketer */
        $marketer = $this->route('marketer');

        return $marketer;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $held = MarketerType::heldBy($this->marketer());
            $requested = $this->input('marketer_type');
            if ($requested && ! in_array(MarketerType::tryFrom($requested), $held, true)) {
                $v->errors()->add('marketer_type', __('marketer_commissions.error_type_not_held'));

                return;
            }

            $type = $this->marketerType();
            $rate = (float) $this->input('commission_rate', 0);
            $flat = (float) $this->input('commission_flat_amount', 0);

            if ($type === MarketerType::Influencer) {
                if ($rate > 0) {
                    $v->errors()->add('commission_rate', __('marketer_commissions.error_influencer_no_percentage'));
                }
                if ($flat <= 0) {
                    $v->errors()->add('commission_flat_amount', __('marketer_commissions.error_influencer_fixed_required'));
                }

                return;
            }

            if ($type === MarketerType::Affiliate) {
                if ($flat > 0) {
                    $v->errors()->add('commission_flat_amount', __('marketer_commissions.error_affiliate_no_fixed'));
                }
                if ($rate <= 0) {
                    $v->errors()->add('commission_rate', __('marketer_commissions.error_affiliate_percentage_required'));
                }

                return;
            }

            if ($rate <= 0 && $flat <= 0) {
                $v->errors()->add('commission_rate', __('marketer_commissions.error_broker_required'));
            }
        });
    }
}
