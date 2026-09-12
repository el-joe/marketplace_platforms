<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCategoryCommission;
use Illuminate\Http\Request;

class MarketerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $marketers = Marketer::query()
            ->with(['country', 'approvedBy'])
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->type, fn ($q) => $q->where('marketer_type', $request->type))
            ->when($request->status, fn ($q) => $q->where('global_status', $request->status))
            ->withCount('invitations')
            ->latest()
            ->paginate(20);

        $pendingCount = Marketer::where('global_status', 'pending')->count();

        return view('admin.marketers.index', compact('marketers', 'pendingCount'));
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
        ]);

        $categories = \App\Models\Category::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);

        return view('admin.marketers.show', compact('marketer', 'categories'));
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
            'ad_price'                => ['nullable', 'integer', 'min:0'],
            'ad_price_currency'       => ['nullable', 'string', 'size:3'],
            'can_self_edit_ad_price'  => ['nullable', 'boolean'],
            'clothing_size'           => ['nullable', 'string', 'max:20'],
            'shirt_size'              => ['nullable', 'string', 'max:20'],
            'pants_size'              => ['nullable', 'string', 'max:20'],
            'dress_size'              => ['nullable', 'string', 'max:20'],
            'abaya_size'              => ['nullable', 'string', 'max:20'],
            'shoe_size'               => ['nullable', 'string', 'max:10'],
            'shoe_size_system'        => ['nullable', 'in:EU,US,UK'],
            'chest_cm'                => ['nullable', 'numeric', 'min:0'],
            'waist_cm'                => ['nullable', 'numeric', 'min:0'],
            'height_cm'               => ['nullable', 'numeric', 'min:0'],
            'measurements_notes'      => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        $data = [
            'ad_price'               => $validated['ad_price'] ?? 0,
            'ad_price_currency'      => $validated['ad_price_currency'] ?? null,
            'can_self_edit_ad_price' => $request->boolean('can_self_edit_ad_price'),
        ];

        // Sample-size measurement fields only apply to influencer marketers.
        if ($marketer->isInfluencer()) {
            $data += $request->only([
                'clothing_size', 'shirt_size', 'pants_size', 'dress_size', 'abaya_size',
                'shoe_size', 'shoe_size_system', 'chest_cm', 'waist_cm', 'height_cm', 'measurements_notes',
            ]);
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
            'category_id'     => ['nullable', 'uuid', 'exists:categories,id'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $categoryId = $validated['category_id'] ?? null;

        $existing = MarketerCategoryCommission::where('marketer_id', $marketer->id)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId), fn ($q) => $q->whereNull('category_id'))
            ->first();

        if ($existing) {
            $existing->update([
                'commission_rate'      => $validated['commission_rate'],
                'updated_by_admin_id'  => auth('admin')->id(),
            ]);
        } else {
            MarketerCategoryCommission::create([
                'marketer_id'          => $marketer->id,
                'category_id'          => $categoryId,
                'commission_rate'      => $validated['commission_rate'],
                'updated_by_admin_id'  => auth('admin')->id(),
            ]);
        }

        return back()->with('success', 'تم حفظ نسبة العمولة.');
    }

    public function destroyCategoryCommission(Marketer $marketer, MarketerCategoryCommission $commission)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);
        abort_unless($commission->marketer_id === $marketer->id, 404);

        $commission->delete();

        return back()->with('success', 'تم حذف نسبة العمولة.');
    }

    public function approve(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);
        abort_unless($marketer->global_status?->value === 'pending', 422, 'الحساب ليس في حالة معلّقة.');

        $marketer->update([
            'global_status'        => 'active',
            'approved_at'          => now(),
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
            'global_status'    => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return back()->with('success', 'تم رفض طلب الماركتر.');
    }

    public function suspend(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $marketer->update([
            'global_status'    => 'suspended',
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
