<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('faqs.view'), 403);

        $context = $request->get('context', 'seller');

        $faqs = Faq::forContext($context)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return view('admin.faqs.index', [
            'faqs' => $faqs,
            'context' => $context,
            'contexts' => Faq::CONTEXTS,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'FAQs'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('faqs.create'), 403);

        $data = $request->validate([
            'context' => 'required|string|in:' . implode(',', Faq::CONTEXTS),
            'question_en' => 'required|string|max:500',
            'question_ar' => 'required|string|max:500',
            'answer_en' => 'required|string',
            'answer_ar' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $faq = Faq::create([
            'context' => $data['context'],
            'question_en' => $data['question_en'],
            'question_ar' => $data['question_ar'],
            'answer_en' => $data['answer_en'],
            'answer_ar' => $data['answer_ar'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'updated_by_admin_id' => $admin->id,
        ]);

        return response()->json(['success' => true, 'message' => 'FAQ created.', 'faq' => $faq]);
    }

    public function update(Request $request, Faq $faq): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('faqs.edit'), 403);

        $data = $request->validate([
            'context' => 'required|string|in:' . implode(',', Faq::CONTEXTS),
            'question_en' => 'required|string|max:500',
            'question_ar' => 'required|string|max:500',
            'answer_en' => 'required|string',
            'answer_ar' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $faq->update([
            'context' => $data['context'],
            'question_en' => $data['question_en'],
            'question_ar' => $data['question_ar'],
            'answer_en' => $data['answer_en'],
            'answer_ar' => $data['answer_ar'],
            'sort_order' => (int) ($data['sort_order'] ?? $faq->sort_order),
            'is_active' => $request->boolean('is_active'),
            'updated_by_admin_id' => $admin->id,
        ]);

        return response()->json(['success' => true, 'message' => 'FAQ updated.', 'faq' => $faq->fresh()]);
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('faqs.delete'), 403);

        $faq->delete();

        return response()->json(['success' => true, 'message' => 'FAQ deleted.']);
    }

    public function toggleActive(Faq $faq): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('faqs.edit'), 403);

        $faq->update(['is_active' => !$faq->is_active, 'updated_by_admin_id' => $admin->id]);

        return response()->json(['success' => true, 'is_active' => $faq->is_active]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('faqs.edit'), 403);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $i => $id) {
                Faq::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['success' => true]);
    }
}
