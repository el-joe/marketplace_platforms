<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVendorCommissionRuleRequest;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Vendor;
use App\Services\ActivityLoggerService;
use App\Services\Admin\Commissions\CommissionEditorData;
use App\Services\Admin\Commissions\CommissionTabData;
use App\Services\Admin\Commissions\CommissionTabRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Unified /commissions page: every commission type in one place, one tab each (?tab=<key>).
 *
 * Only the vendor-rules tab writes, because the `commissions` table is the one source with no editor
 * anywhere else. Every other tab reads live data and links to the screen that already owns the editor,
 * so no commission logic is duplicated here.
 */
class CommissionsController extends Controller
{
    public function __construct(private readonly ActivityLoggerService $activityLogger) {}

    public function index(Request $request): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $groups = CommissionTabRegistry::visibleFor($admin);
        abort_if($groups === [], 403);

        $visible = array_merge(...array_values($groups));
        $requested = (string) $request->query('tab', '');
        // An unknown or unauthorised ?tab= falls back to the first tab the admin may see.
        $activeKey = isset($visible[$requested]) ? $requested : array_key_first($visible);
        $tab = $visible[$activeKey];

        $isScoped = $request->attributes->get('is_scoped_admin') === true;
        $scopedVendorIds = $isScoped ? ($request->attributes->get('scoped_vendor_ids') ?? []) : null;
        $data = new CommissionTabData($scopedVendorIds);

        $canManageRules = ! $isScoped && $admin->can('commission_rules.manage');
        $editing = null;
        $rules = null;
        $vendors = collect();
        $categories = collect();

        if ($activeKey === 'vendor-rules') {
            $rules = $data->vendorRulesQuery()->paginate(CommissionTabData::ROW_LIMIT)->withQueryString();
            if ($canManageRules) {
                $editing = $request->filled('edit') ? Commission::find($request->query('edit')) : null;
                $vendors = Vendor::orderBy('store_name')->get(['id', 'store_name']);
                $categories = Category::orderBy('name_en')->get(['id', 'name_en', 'name_ar']);
            }
        }

        return view('admin.commissions.index', [
            'groups' => $groups,
            'activeKey' => $activeKey,
            'tab' => $tab,
            'content' => $data->for($activeKey),
            'editor' => (new CommissionEditorData($request, $scopedVendorIds))->for($activeKey),
            'isScoped' => $isScoped,
            'canManageRules' => $canManageRules,
            'rules' => $rules,
            'editing' => $editing,
            'vendors' => $vendors,
            'categories' => $categories,
        ]);
    }

    public function storeVendorRule(StoreVendorCommissionRuleRequest $request): RedirectResponse
    {
        $rule = Commission::create($request->ruleData());

        $this->audit('commission_rule_created', $rule, [], $rule->only($this->auditedFields()));

        return $this->backToRules('commissions.rule_saved');
    }

    public function updateVendorRule(StoreVendorCommissionRuleRequest $request, Commission $commission): RedirectResponse
    {
        $before = $commission->only($this->auditedFields());
        $commission->update($request->ruleData());

        $this->audit('commission_rule_updated', $commission, $before, $commission->fresh()->only($this->auditedFields()));

        return $this->backToRules('commissions.rule_saved');
    }

    public function destroyVendorRule(Request $request, Commission $commission): RedirectResponse
    {
        abort_unless(
            $request->user('admin')->can('commission_rules.manage') && $request->attributes->get('is_scoped_admin') !== true,
            403
        );

        $before = $commission->only($this->auditedFields());
        $commission->delete();

        $this->audit('commission_rule_deleted', $commission, $before, []);

        return $this->backToRules('commissions.rule_deleted');
    }

    /**
     * @return list<string>
     */
    private function auditedFields(): array
    {
        return ['vendor_id', 'category_id', 'rate_pct', 'rate_type', 'min_commission', 'max_commission', 'effective_from', 'effective_until', 'priority'];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(string $event, Commission $rule, array $before, array $after): void
    {
        $this->activityLogger->log(
            description: $event,
            subject: $rule,
            causer: auth('admin')->user(),
            properties: ['before' => $before, 'after' => $after],
            logName: 'commission',
            event: $event,
        );
    }

    private function backToRules(string $messageKey): RedirectResponse
    {
        return redirect()->route('admin.commissions.index', ['tab' => 'vendor-rules'])->with('success', __($messageKey));
    }
}
