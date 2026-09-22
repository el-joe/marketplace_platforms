<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\MarketerJob;
use App\Models\MarketerJobCategory;
use Illuminate\Http\Request;

class MarketerJobController extends Controller
{
    public function index()
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $marketerJobs = MarketerJob::withCount('marketers')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        return view('admin.marketer-jobs.index', compact('marketerJobs'));
    }

    public function store(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:marketer_jobs,key'],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        MarketerJob::create([
            'key' => $validated['key'],
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'],
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.marketer-jobs.index')->with('success', 'تم إنشاء الوظيفة بنجاح.');
    }

    public function edit(MarketerJob $marketerJob)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $marketerJob->load('categories');

        $productCategories = Category::orderBy('name_en')->get();
        $classifiedCategories = ClassifiedCategory::orderBy('name_en')->get();

        return view('admin.marketer-jobs.edit', compact('marketerJob', 'productCategories', 'classifiedCategories'));
    }

    public function update(Request $request, MarketerJob $marketerJob)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:marketer_jobs,key,'.$marketerJob->id],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $marketerJob->update([
            'key' => $validated['key'],
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'],
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.marketer-jobs.edit', $marketerJob)->with('success', 'تم تحديث الوظيفة بنجاح.');
    }

    public function toggleActive(MarketerJob $marketerJob)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $marketerJob->update(['is_active' => ! $marketerJob->is_active]);

        return back()->with('success', 'تم تحديث حالة الوظيفة.');
    }

    public function destroy(MarketerJob $marketerJob)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        if ($marketerJob->marketers()->exists()) {
            return back()->with('error', 'لا يمكن حذف وظيفة مرتبطة بماركترز، يمكنك تعطيلها بدلاً من ذلك.');
        }

        $marketerJob->delete();

        return redirect()->route('admin.marketer-jobs.index')->with('success', 'تم حذف الوظيفة.');
    }

    /**
     * Sync which category sources (product/classified/travel) this job supports, and
     * optionally restrict each source to a whitelist of specific categories. An empty/
     * absent category list for a given type means the job can use ALL categories of
     * that source.
     */
    public function syncCategoryTypes(Request $request, MarketerJob $marketerJob)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validate([
            'category_types' => ['nullable', 'array'],
            'category_types.*' => ['in:product,classified,travel'],
            'categories' => ['nullable', 'array'],
            'categories.product' => ['nullable', 'array'],
            'categories.product.*' => ['string'],
            'categories.classified' => ['nullable', 'array'],
            'categories.classified.*' => ['string'],
        ]);

        $categoryTypes = $validated['category_types'] ?? [];
        $categories = $validated['categories'] ?? [];

        MarketerJobCategory::where('marketer_job_id', $marketerJob->id)
            ->whereNotIn('category_type', $categoryTypes)
            ->delete();

        foreach ($categoryTypes as $categoryType) {
            $categoryIds = $categories[$categoryType] ?? [];

            MarketerJobCategory::where('marketer_job_id', $marketerJob->id)
                ->where('category_type', $categoryType)
                ->when(
                    empty($categoryIds),
                    fn ($q) => $q->whereNotNull('category_id'),
                    fn ($q) => $q->where(fn ($q2) => $q2->whereNull('category_id')->orWhereNotIn('category_id', $categoryIds)),
                )
                ->delete();

            if (empty($categoryIds)) {
                MarketerJobCategory::firstOrCreate([
                    'marketer_job_id' => $marketerJob->id,
                    'category_type' => $categoryType,
                    'category_id' => null,
                ]);
            } else {
                foreach ($categoryIds as $categoryId) {
                    MarketerJobCategory::firstOrCreate([
                        'marketer_job_id' => $marketerJob->id,
                        'category_type' => $categoryType,
                        'category_id' => $categoryId,
                    ]);
                }
            }
        }

        return back()->with('success', 'تم تحديث الفئات المتاحة لهذه الوظيفة.');
    }
}
