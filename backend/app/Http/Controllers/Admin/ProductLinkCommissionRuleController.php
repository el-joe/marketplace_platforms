<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductLinkCommissionRuleRequest;
use App\Models\Country;
use App\Models\ProductLinkCommissionRule;
use App\Services\ActivityLoggerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * WS12 #24: Admin → Vendors → "Product link / QR commission". One rule per country: which sale models it covers, the
 * discount on the platform commission (percent / fixed / both / zero) and the attribution window. Applies only to
 * orders placed after the change; existing orders keep the commission they were charged.
 */
class ProductLinkCommissionRuleController extends Controller
{
    public function __construct(private readonly ActivityLoggerService $activity) {}

    public function index(): View
    {
        $countries = Country::query()->where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'currency_code']);
        $rules = ProductLinkCommissionRule::query()->whereIn('country_id', $countries->pluck('id'))->get()->keyBy('country_id');

        return view('admin.product-link-commission.index', [
            'countries' => $countries,
            'rules' => $rules,
            'types' => ProductLinkCommissionRule::TYPES,
            'models' => ProductLinkCommissionRule::FULFILLMENT_MODELS,
            'canManage' => (bool) auth('admin')->user()?->can('product_link_commission.manage'),
        ]);
    }

    public function update(SaveProductLinkCommissionRuleRequest $request, Country $country): RedirectResponse
    {
        $validated = $request->validated();
        $type = $validated['discount_type'];

        $rule = ProductLinkCommissionRule::firstOrNew(['country_id' => $country->id]);
        $before = $rule->exists ? $rule->only(['discount_type', 'discount_percent', 'discount_fixed', 'attribution_window_days', 'fulfillment_models', 'is_active']) : null;

        $rule->fill([
            'fulfillment_models' => array_values(array_unique($validated['fulfillment_models'] ?? [])),
            'discount_type' => $type,
            'discount_percent' => in_array($type, [ProductLinkCommissionRule::TYPE_PERCENT, ProductLinkCommissionRule::TYPE_BOTH], true) ? round((float) $validated['discount_percent'], 4) : 0,
            'discount_fixed' => in_array($type, [ProductLinkCommissionRule::TYPE_FIXED, ProductLinkCommissionRule::TYPE_BOTH], true) ? round((float) $validated['discount_fixed'], 4) : 0,
            'attribution_window_days' => (int) $validated['attribution_window_days'],
            'is_active' => $request->boolean('is_active'),
            'updated_by_admin_id' => auth('admin')->id(),
        ])->save();

        $this->activity->log(
            description: 'product_link_commission_rule_saved',
            subject: $rule,
            causer: auth('admin')->user(),
            properties: ['country_id' => $country->id, 'before' => $before, 'after' => $rule->only(array_keys($before ?? $rule->getAttributes()))],
            logName: 'commission',
            event: 'product_link_commission_rule_saved',
        );

        return redirect()->route('admin.product-link-commission.index')
            ->with('success', __('product_link.admin.saved', ['country' => localized($country)]));
    }
}
