<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketerCommissionDiscountType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCategoryCommission;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\MarketerMarketerJobCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MarketerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $marketers = Marketer::query()
            ->with(['country', 'approvedBy', 'marketerJobs'])
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->type, fn ($q) => $q->whereHas('marketerJobs', fn ($jq) => $jq->where('key', $request->type)))
            ->when($request->status, fn ($q) => $q->where('global_status', $request->status))
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
            'categoryCommissions.category',
            'marketerJobs',
            'marketerJobAssignments.marketerJob.categories',
            'marketerJobAssignments.categoryScopes',
            'exclusiveContracts' => fn ($q) => $q->latest('starts_at'),
            'exclusiveContracts.classifiedCategory',
            'exclusiveContracts.classifiedListing',
            'exclusiveContracts.createdBy',
        ]);

        $categories = Category::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $cities = City::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $classifiedCategories = ClassifiedCategory::orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);

        $travelCategories = \App\Models\TravelCategory::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $commissionRules = \App\Models\MarketerCommissionRule::where('marketer_id', $marketer->id)
            ->with('category')->orderBy('scope')->get();
        $commissionCategories = collect([
            'products' => $categories,
            'open_market' => ClassifiedCategory::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']),
            'travel' => $travelCategories,
        ]);
        $commissionCurrency = $marketer->country?->currency_code ?: config('app.currency', 'SAR');

        return view('admin.marketers.show', compact('marketer', 'categories', 'cities', 'classifiedCategories', 'commissionRules', 'commissionCategories', 'commissionCurrency'));
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
            'ad_price' => ['nullable', 'integer', 'min:0'],
            'ad_price_currency' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'can_self_edit_ad_price' => ['nullable', 'boolean'],
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
            'commission_discount_type' => ['nullable', Rule::enum(MarketerCommissionDiscountType::class)],
            'commission_discount_flat' => ['nullable', 'integer', 'min:0'],
            'commission_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_discount_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        $data = [
            'ad_price' => $validated['ad_price'] ?? 0,
            'ad_price_currency' => $validated['ad_price_currency'] ?? null,
            'can_self_edit_ad_price' => $request->boolean('can_self_edit_ad_price'),
            'commission_discount_type' => $validated['commission_discount_type'] ?? 'none',
            'commission_discount_flat' => $validated['commission_discount_flat'] ?? 0,
            'commission_discount_percentage' => $validated['commission_discount_percentage'] ?? 0,
            'commission_discount_notes' => $validated['commission_discount_notes'] ?? null,
            'specialty_ar' => $validated['specialty_ar'] ?? null,
            'specialty_en' => $validated['specialty_en'] ?? null,
        ];

        // Sample-size measurement fields only apply to influencer marketers.
        if ($marketer->isInfluencer()) {
            $data += $request->only([
                'clothing_size', 'shirt_size', 'pants_size', 'dress_size', 'abaya_size',
                'shoe_size', 'shoe_size_system', 'chest_cm', 'waist_cm', 'hip_cm', 'height_cm',
                'item_length_cm', 'sleeve_from_neck_cm', 'sleeve_from_shoulder_cm', 'sleeve_width_cm',
                'measurements_notes',
            ]);
        }

        // Broker specialization (category + city) only applies to affiliate marketers.
        if ($marketer->isAffiliate()) {
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
     * Store or update a marketer × category commission override.
     * category_id = null means "default rate for all categories".
     */
    public function storeCategoryCommission(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validate([
            'scope' => ['nullable', 'in:products,open_market,travel'],
            'category_id' => ['nullable', 'uuid'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_flat_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $rate = (float) ($validated['commission_rate'] ?? 0);
        $flat = (int) ($validated['commission_flat_amount'] ?? 0);
        if ($rate <= 0 && $flat <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'commission_rate' => __('admin.marketer_commission_required'),
            ]);
        }
        $mode = ($rate > 0 && $flat > 0) ? 'both' : ($flat > 0 ? 'fixed' : 'percentage');
        $payload = [
            'commission_mode' => $mode,
            'commission_rate' => $rate,
            'commission_flat_amount' => $flat > 0 ? $flat : null,
        ];

        $categoryId = $validated['category_id'] ?? null;
        $scope = $validated['scope'] ?? 'products';
        $catClass = \App\Models\MarketerCommissionRule::categoryClassFor($scope);
        if ($categoryId && ! $catClass::whereKey($categoryId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['category_id' => __('validation.exists', ['attribute' => 'category_id'])]);
        }

        $ruleQuery = \App\Models\MarketerCommissionRule::where('marketer_id', $marketer->id)->where('scope', $scope)
            ->when($categoryId, fn ($q) => $q->where('category_type', $catClass)->where('category_id', $categoryId), fn ($q) => $q->whereNull('category_id'));
        $rule = $ruleQuery->first();
        $ruleData = $payload + ['updated_by_admin_id' => auth('admin')->id()];
        if ($rule) {
            $rule->update($ruleData);
        } else {
            \App\Models\MarketerCommissionRule::create($ruleData + [
                'marketer_id' => $marketer->id, 'scope' => $scope,
                'category_type' => $categoryId ? $catClass : null, 'category_id' => $categoryId,
            ]);
        }

        if ($scope === 'travel') {
            return back()->with('success', 'تم حفظ نسبة العمولة.');
        }
        if ($scope === 'open_market') {
            \App\Models\OpenMarketCategoryCommission::updateOrCreate(
                ['marketer_id' => $marketer->id, 'classified_category_id' => $categoryId],
                $ruleData
            );

            return back()->with('success', 'تم حفظ نسبة العمولة.');
        }

        $existing = MarketerCategoryCommission::where('marketer_id', $marketer->id)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId), fn ($q) => $q->whereNull('category_id'))
            ->first();

        if ($existing) {
            $existing->update($payload + ['updated_by_admin_id' => auth('admin')->id()]);
        } else {
            MarketerCategoryCommission::create($payload + [
                'marketer_id' => $marketer->id,
                'category_id' => $categoryId,
                'updated_by_admin_id' => auth('admin')->id(),
            ]);
        }

        return back()->with('success', 'تم حفظ نسبة العمولة.');
    }

    public function destroyCategoryCommission(Marketer $marketer, string $commission)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $rule = \App\Models\MarketerCommissionRule::where('marketer_id', $marketer->id)->find($commission);
        if ($rule) {
            $catId = $rule->category_id;
            if ($rule->scope === 'products') {
                MarketerCategoryCommission::where('marketer_id', $marketer->id)
                    ->when($catId, fn ($q) => $q->where('category_id', $catId), fn ($q) => $q->whereNull('category_id'))->delete();
            } elseif ($rule->scope === 'open_market') {
                \App\Models\OpenMarketCategoryCommission::where('marketer_id', $marketer->id)
                    ->when($catId, fn ($q) => $q->where('classified_category_id', $catId), fn ($q) => $q->whereNull('classified_category_id'))->delete();
            }
            $rule->delete();

            return back()->with('success', 'تم حذف نسبة العمولة.');
        }

        // Backward compatible: legacy row id.
        $legacy = MarketerCategoryCommission::where('marketer_id', $marketer->id)->findOrFail($commission);
        \App\Models\MarketerCommissionRule::where('marketer_id', $marketer->id)->where('scope', 'products')
            ->when($legacy->category_id, fn ($q) => $q->where('category_id', $legacy->category_id), fn ($q) => $q->whereNull('category_id'))->delete();
        $legacy->delete();

        return back()->with('success', 'تم حذف نسبة العمولة.');
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
