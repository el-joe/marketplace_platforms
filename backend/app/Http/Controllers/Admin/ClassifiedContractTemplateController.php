<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassifiedContractTemplateController extends Controller
{
    public function index(): View
    {
        $templates = ClassifiedContractTemplate::orderBy('name')->orderByDesc('version')->get();

        return view('admin.classified-contract-templates.index', compact('templates'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:200',
            'content_en' => 'required|string',
            'content_ar' => 'required|string',
            'is_active'  => 'boolean',
        ]);

        // Auto-increment version per name
        $maxVersion = ClassifiedContractTemplate::where('name', $validated['name'])->max('version') ?? 0;
        $validated['version']             = $maxVersion + 1;
        $validated['created_by_admin_id'] = auth('admin')->id();

        $template = ClassifiedContractTemplate::create($validated);

        return response()->json(['message' => __('admin.classified_contract_templates.created'), 'template' => $template], 201);
    }

    public function update(Request $request, ClassifiedContractTemplate $contractTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:200',
            'content_en' => 'required|string',
            'content_ar' => 'required|string',
            'is_active'  => 'boolean',
        ]);

        $contentChanged = $validated['content_en'] !== $contractTemplate->content_en
            || $validated['content_ar'] !== $contractTemplate->content_ar;

        if ($contentChanged) {
            // Create a new version row to preserve audit trail for already-signed contracts
            $maxVersion = ClassifiedContractTemplate::where('name', $validated['name'])->max('version') ?? $contractTemplate->version;
            $validated['version']             = $maxVersion + 1;
            $validated['created_by_admin_id'] = auth('admin')->id();

            // Deactivate old version
            $contractTemplate->update(['is_active' => false]);

            $template = ClassifiedContractTemplate::create($validated);

            return response()->json([
                'message'     => __('admin.classified_contract_templates.new_version_created'),
                'template'    => $template,
                'new_version' => true,
            ], 201);
        }

        // Only name/is_active changed — safe to update in place
        $contractTemplate->update([
            'name'      => $validated['name'],
            'is_active' => $validated['is_active'] ?? $contractTemplate->is_active,
        ]);

        return response()->json([
            'message'     => __('admin.classified_contract_templates.updated'),
            'template'    => $contractTemplate->fresh(),
            'new_version' => false,
        ]);
    }

    public function destroy(ClassifiedContractTemplate $contractTemplate): JsonResponse
    {
        $categoryCount = ClassifiedCategory::where('contract_template_id', $contractTemplate->id)->count();
        if ($categoryCount > 0) {
            return response()->json([
                'message' => __('admin.classified_contract_templates.cannot_delete_has_categories', ['count' => $categoryCount]),
            ], 422);
        }

        $listingCount = \App\Models\ClassifiedListing::where('contract_template_id', $contractTemplate->id)->count();
        if ($listingCount > 0) {
            return response()->json([
                'message' => __('admin.classified_contract_templates.cannot_delete_has_listings', ['count' => $listingCount]),
            ], 422);
        }

        $contractTemplate->delete();

        return response()->json(['message' => __('admin.classified_contract_templates.deleted')]);
    }
}
