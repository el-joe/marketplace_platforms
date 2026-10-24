<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketerGender;
use App\Enums\MarketerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMarketerCommissionRuleRequest;
use App\Models\Category;
use App\Models\City;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCommissionRule;
use App\Models\MarketerDefaultCommission;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\MarketerMarketerJobCategory;
use App\Models\MarketerSaleSettlement;
use App\Models\TravelCategory;
use App\Models\Wallet;
use App\Services\Marketer\MarketerDefaultCommissionResolver;
use App\Services\Marketer\MarketerEarningsService;
use App\Services\Marketer\MarketerSaleSettlementService;
use App\Services\MarketingFeeReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MarketerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $marketers = Marketer::query()
            ->with(['country', 'approvedBy', 'marketerJobs', 'marketerProfile:id,marketer_id,gender'])
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->type, fn ($q) => $q->whereHas('marketerJobs', fn ($jq) => $jq->where('key', $request->type)))
            ->when($request->status, fn ($q) => $q->where('global_status', $request->status))
            ->when($request->gender, function ($q) use ($request) {
                if ($request->gender === 'none') {
                    return $q->where(fn ($w) => $w->whereDoesntHave('marketerProfile')
                        ->orWhereHas('marketerProfile', fn ($p) => $p->whereNull('gender')));
                }

                return $q->whereHas('marketerProfile', fn ($p) => $p->where('gender', $request->gender));
            })
            ->withCount('invitations')
            ->latest()
            ->paginate(20);

        $pendingCount = Marketer::where('global_status', 'pending')->count();
        $countries = Country::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $marketerJobs = MarketerJob::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.marketers.index', compact('marketers', 'pendingCount', 'countries', 'marketerJobs'));
    }

    public function store(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:marketers,email', 'unique:marketer_admins,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'marketer_jobs' => ['required', 'array', 'min:1'],
            'marketer_jobs.*' => ['exists:marketer_jobs,id'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'password' => ['required', 'string', 'min:8'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['nullable', 'array'],
            'categories.*.*' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $marketer = Marketer::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'country_id' => $validated['country_id'] ?? null,
                'global_status' => 'active',
                'approved_at' => now(),
                'approved_by_admin_id' => auth('admin')->id(),
            ]);

            $marketer->marketerAdmins()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_owner' => true,
                'is_active' => true,
            ]);

            $marketer->marketerJobs()->sync($validated['marketer_jobs']);

            foreach ($request->input('categories', []) as $marketerJobId => $byType) {
                $assignment = MarketerMarketerJob::where('marketer_id', $marketer->id)
                    ->where('marketer_job_id', $marketerJobId)
                    ->first();

                if (! $assignment) {
                    continue;
                }

                foreach ((array) $byType as $categoryType => $categoryIds) {
                    if (! in_array($categoryType, ['product', 'classified'], true)) {
                        continue;
                    }

                    $assignment->categoryScopes()->where('category_type', $categoryType)->delete();

                    foreach (array_filter((array) $categoryIds) as $categoryId) {
                        MarketerMarketerJobCategory::create([
                            'marketer_marketer_job_id' => $assignment->id,
                            'category_type' => $categoryType,
                            'category_id' => $categoryId,
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'تم إنشاء حساب الماركتر بنجاح.');
    }

    public function show(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $marketer->load([
            'country',
            'approvedBy',
            'marketerProfile',
            'invitations.campaign.vendor',
            'marketerJobs',
            'marketerJobAssignments.marketerJob.categories',
            'marketerJobAssignments.categoryScopes',
            'exclusiveContracts' => fn ($q) => $q->latest('starts_at'),
            'exclusiveContracts.classifiedCategory',
            'exclusiveContracts.classifiedListing',
            'exclusiveContracts.createdBy',
            'listings' => fn ($q) => $q->with(['country', 'productVariant.product', 'travelPackage', 'classifiedListing', 'approvedBy'])->latest(),
        ]);

        $categories = Category::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $cities = City::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $classifiedCategories = ClassifiedCategory::orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);

        $travelCategories = TravelCategory::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $commissionRules = MarketerCommissionRule::where('marketer_id', $marketer->id)
            ->with('category')->orderBy('scope')->get();
        $commissionCategories = collect([
            'products' => $categories,
            'open_market' => ClassifiedCategory::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']),
            'travel' => $travelCategories,
        ]);
        $commissionCurrency = $marketer->country?->currency_code ?: config('app.currency', 'SAR');
        $commissionTypes = MarketerType::heldBy($marketer);
        $commissionDefaults = $this->commissionDefaultsFor($marketer, $commissionCategories);

        $statement = app(MarketerEarningsService::class);
        $conversions = MarketerCampaignConversion::with(['campaign', 'order', 'invitation.marketer.marketerProfile'])
            ->whereIn('invitation_id', $marketer->invitations->pluck('id'))
            ->latest()->limit(25)->get()
            ->each(fn ($c) => $c->setAttribute('statement', $statement->breakdown($c)));
        $statementTotals = collect(['gross', 'fee', 'net'])->mapWithKeys(
            fn ($k) => [$k => round((float) $conversions->where('status', 'approved')->sum(fn ($c) => $c->statement[$k]), 2)]
        );

        // Sales of this marketer's own listings (seller_type = marketer): gross -> platform commission -> VAT -> ... -> net.
        $salesService = app(MarketerSaleSettlementService::class);
        $salesTotals = $salesService->totalsFor($marketer->id);
        $sales = MarketerSaleSettlement::with('order:id,order_number')->where('marketer_id', $marketer->id)->latest()->limit(25)->get();

        // Earnings per campaign (gross, platform commission fee, net), influencer fixed amounts and wallet balance(s).
        $earnings = app(MarketingFeeReportService::class)->marketerEarnings($marketer->id);
        $marketerWallets = Wallet::where('owner_type', 'marketer')->where('owner_id', $marketer->id)->orderBy('currency')->get();

        return view('admin.marketers.show', compact('earnings', 'marketerWallets', 'conversions', 'statementTotals', 'salesTotals', 'sales', 'marketer', 'categories', 'cities', 'classifiedCategories', 'commissionRules', 'commissionCategories', 'commissionCurrency', 'commissionTypes', 'commissionDefaults'));
    }

    /**
     * Sync the specific-category scoping for one of a marketer's assigned
     * jobs, for a given category source (product/classified). An empty
     * `category_ids` array clears the scope rows, meaning "all categories".
     */
    public function syncJobCategories(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validate([
            'marketer_job_id' => ['required', 'exists:marketer_jobs,id'],
            'category_type' => ['required', 'in:product,classified'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string'],
        ]);

        $assignment = MarketerMarketerJob::where('marketer_id', $marketer->id)
            ->where('marketer_job_id', $validated['marketer_job_id'])
            ->firstOrFail();

        $assignment->categoryScopes()->where('category_type', $validated['category_type'])->delete();

        foreach (array_filter($validated['category_ids'] ?? []) as $categoryId) {
            MarketerMarketerJobCategory::create([
                'marketer_marketer_job_id' => $assignment->id,
                'category_type' => $validated['category_type'],
                'category_id' => $categoryId,
            ]);
        }

        return back()->with('success', 'تم تحديث الأقسام الخاصة بهذه الوظيفة.');
    }

    /**
     * Update the marketer profile's admin-editable fields: ad display price,
     * the self-edit permission flag, and (for influencer marketers) the
     * sample-size measurement fields.
     */
    public function updateProfile(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validate([
            'gender' => ['nullable', Rule::enum(MarketerGender::class)],
            'ad_price' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'ad_price_currency' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'can_self_edit_ad_price' => ['nullable', 'boolean'],
            'story_price' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'post_price' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'video_price' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'clothing_size' => ['nullable', 'string', 'max:20'],
            'shirt_size' => ['nullable', 'string', 'max:20'],
            'pants_size' => ['nullable', 'string', 'max:20'],
            'dress_size' => ['nullable', 'string', 'max:20'],
            'abaya_size' => ['nullable', 'string', 'max:20'],
            'shoe_size' => ['nullable', 'string', 'max:10'],
            'shoe_size_system' => ['nullable', 'in:EU,US,UK'],
            'chest_cm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'waist_cm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'hip_cm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'item_length_cm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'sleeve_from_neck_cm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'sleeve_from_shoulder_cm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'sleeve_width_cm' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'measurements_notes' => ['nullable', 'string', 'max:2000'],
            'specialty_ar' => ['nullable', 'string', 'max:150'],
            'specialty_en' => ['nullable', 'string', 'max:150'],
            'broker_category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'broker_city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'broker_serves_all_cities' => ['nullable', 'boolean'],
        ]);

        $profile = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        $data = [
            'ad_price' => $validated['ad_price'] ?? 0,
            'ad_price_currency' => $validated['ad_price_currency'] ?? null,
            'can_self_edit_ad_price' => $request->boolean('can_self_edit_ad_price'),
            'story_price' => isset($validated['story_price']) ? Money::number($validated['story_price']) : null,
            'post_price' => isset($validated['post_price']) ? Money::number($validated['post_price']) : null,
            'video_price' => isset($validated['video_price']) ? Money::number($validated['video_price']) : null,
            'specialty_ar' => $validated['specialty_ar'] ?? null,
            'specialty_en' => $validated['specialty_en'] ?? null,
        ];

        if ($request->has('gender')) {
            $data['gender'] = $validated['gender'] ?? null;
        }

        // Sample-size measurement fields only apply to influencer marketers.
        if ($marketer->isInfluencer()) {
            $data += $request->only([
                'clothing_size', 'shirt_size', 'pants_size', 'dress_size', 'abaya_size',
                'shoe_size', 'shoe_size_system', 'chest_cm', 'waist_cm', 'hip_cm', 'height_cm',
                'item_length_cm', 'sleeve_from_neck_cm', 'sleeve_from_shoulder_cm', 'sleeve_width_cm',
                'measurements_notes',
            ]);
        }

        // Broker specialization (category + city) only applies to broker marketers.
        if ($marketer->isBroker()) {
            $data['broker_category_id'] = $validated['broker_category_id'] ?? null;
            $data['broker_serves_all_cities'] = $request->boolean('broker_serves_all_cities');
            $data['broker_city_id'] = $data['broker_serves_all_cities']
                ? null
                : ($validated['broker_city_id'] ?? null);
        }

        $profile->fill($data)->save();

        return back()->with('success', 'تم حفظ بيانات البروفايل.');
    }

    /**
     * Store or update a marketer x category commission rule.
     * category_id = null means "default for all categories of that scope".
     *
     * Type rules (enforced by StoreMarketerCommissionRuleRequest): influencer = fixed amount per campaign only,
     * affiliate = percentage only, broker = fixed, percentage or both. The commission-fee override
     * (fee_percentage / fee_fixed) is optional; empty means "use the default from Marketer Settings".
     */
    public function storeCategoryCommission(StoreMarketerCommissionRuleRequest $request, Marketer $marketer)
    {
        $validated = $request->validated();
        $type = $request->marketerType();

        $rate = (float) ($validated['commission_rate'] ?? 0);
        $flat = round((float) ($validated['commission_flat_amount'] ?? 0), 2);
        if ($type === MarketerType::Influencer) {
            $rate = 0.0;
        } elseif ($type === MarketerType::Affiliate) {
            $flat = 0.0;
        }

        $mode = ($rate > 0 && $flat > 0) ? 'both' : ($flat > 0 ? 'fixed' : 'percentage');
        $minAmount = $type === MarketerType::Influencer ? 0 : (float) ($validated['commission_min_amount'] ?? 0);
        $payload = [
            'marketer_type' => $type?->value,
            'commission_mode' => $mode,
            'commission_rate' => $rate,
            'commission_flat_amount' => $flat > 0 ? $flat : null,
            'commission_min_amount' => $minAmount > 0 ? round($minAmount, 2) : null,
            'fee_percentage' => isset($validated['fee_percentage']) ? round((float) $validated['fee_percentage'], 4) : null,
            'fee_fixed' => isset($validated['fee_fixed']) ? round((float) $validated['fee_fixed'], 2) : null,
        ];

        $categoryId = $validated['category_id'] ?? null;
        $scope = $validated['scope'];
        $catClass = MarketerCommissionRule::categoryClassFor($scope);
        if ($categoryId && ! $catClass::whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['category_id' => __('validation.exists', ['attribute' => 'category_id'])]);
        }

        // "All categories except ..." only makes sense on the scope default.
        $excluded = $categoryId ? [] : array_values(array_unique($validated['excluded_category_ids'] ?? []));
        if ($excluded && $catClass::whereKey($excluded)->count() !== count($excluded)) {
            throw ValidationException::withMessages(['excluded_category_ids' => __('validation.exists', ['attribute' => 'excluded_category_ids'])]);
        }

        DB::transaction(function () use ($marketer, $scope, $categoryId, $catClass, $payload, $excluded) {
            $ruleQuery = MarketerCommissionRule::where('marketer_id', $marketer->id)->where('scope', $scope)
                ->when($categoryId, fn ($q) => $q->where('category_type', $catClass)->where('category_id', $categoryId), fn ($q) => $q->whereNull('category_id'));
            $rule = $ruleQuery->first();
            $ruleData = $payload + ['updated_by_admin_id' => auth('admin')->id(), 'excluded_category_ids' => $excluded ?: null];
            if ($rule) {
                $rule->update($ruleData);
            } else {
                MarketerCommissionRule::create($ruleData + [
                    'marketer_id' => $marketer->id, 'scope' => $scope,
                    'category_type' => $categoryId ? $catClass : null, 'category_id' => $categoryId,
                ]);
            }
        });

        return back()->with('success', __('marketer_commissions.saved'));
    }

    public function destroyCategoryCommission(Marketer $marketer, string $commission)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        MarketerCommissionRule::where('marketer_id', $marketer->id)->findOrFail($commission)->delete();

        return back()->with('success', __('marketer_commissions.deleted'));
    }

    /**
     * What an empty field resolves to for this marketer: the platform defaults from Marketer Settings
     * (influencer fixed amount per campaign + commission fee), keyed by rule scope, with per-category influencer overrides.
     *
     * @return array<string, array{influencer: float, fee_percentage: float, fee_fixed: float, categories: array<string, float>}>
     */
    private function commissionDefaultsFor(Marketer $marketer, $commissionCategories): array
    {
        $resolver = app(MarketerDefaultCommissionResolver::class);
        $countryId = $marketer->country_id;
        $out = [];

        foreach (MarketerCommissionRule::SCOPES as $scope) {
            $vertical = MarketerCommissionRule::verticalFor($scope);
            $fee = $resolver->commissionFee($countryId, $vertical);
            $categories = [];
            if ($countryId) {
                $categories = MarketerDefaultCommission::query()
                    ->where('country_id', $countryId)->where('category_type', $vertical->value)->whereNotNull('category_id')
                    ->pluck('influencer_fixed_amount', 'category_id')
                    ->map(fn ($v) => (float) $v)->all();
            }

            $out[$scope] = [
                'influencer' => $resolver->influencerFixedAmount($countryId, $vertical),
                'fee_percentage' => $fee['percentage'],
                'fee_fixed' => $fee['fixed'],
                'categories' => $categories,
            ];
        }

        return $out;
    }

    public function approve(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);
        abort_unless($marketer->global_status?->value === 'pending', 422, 'الحساب ليس في حالة معلّقة.');

        $marketer->update([
            'global_status' => 'active',
            'approved_at' => now(),
            'approved_by_admin_id' => auth('admin')->id(),
        ]);

        // Notify marketer admin (first owner)
        $owner = $marketer->marketerAdmins()->where('is_owner', true)->first();
        // TODO: send email/notification to $owner

        return back()->with('success', 'تم تفعيل حساب الماركتر بنجاح.');
    }

    public function reject(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $marketer->update([
            'global_status' => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return back()->with('success', 'تم رفض طلب الماركتر.');
    }

    public function suspend(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $marketer->update([
            'global_status' => 'suspended',
            'rejection_reason' => $request->reason,
        ]);

        return back()->with('success', 'تم تعليق الحساب.');
    }

    public function activate(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $marketer->update(['global_status' => 'active']);

        return back()->with('success', 'تم تفعيل الحساب.');
    }
}
