<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\VendorDocumentCountryRequirement;
use App\Models\VendorDocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorDocumentTypeController extends Controller
{
    public function index()
    {
        $types = VendorDocumentType::with(['countryRequirements'])
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        $countries = Country::where('is_active', true)
            ->orderBy('name_en')
            ->get();

        return view('admin.vendor-document-types.index', compact('types', 'countries'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/', 'unique:vendor_document_types,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'accepted_file_types' => ['nullable', 'array'],
            'accepted_file_types.*' => ['string', 'max:20'],
            'max_file_size_kb' => ['nullable', 'integer', 'min:1'],
            'requires_expiry_date' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_active'] = true;

        $type = VendorDocumentType::create($data);

        // Seed optional requirement for every existing country
        $countries = Country::all(['id']);
        $rows = $countries->map(fn($c) => [
            'id' => Str::orderedUuid()->toString(),
            'vendor_document_type_id' => $type->id,
            'country_id' => $c->id,
            'requirement_level' => 'optional',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        VendorDocumentCountryRequirement::insert($rows);

        $type->load('countryRequirements');

        return response()->json([
            'success' => true,
            'message' => 'Document type created.',
            'data' => $type,
        ], 201);
    }

    public function update(Request $request, VendorDocumentType $type): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('vendor_document_types', 'code')->ignore($type->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'accepted_file_types' => ['nullable', 'array'],
            'accepted_file_types.*' => ['string', 'max:20'],
            'max_file_size_kb' => ['nullable', 'integer', 'min:1'],
            'requires_expiry_date' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $codeChanged = $data['code'] !== $type->code;

        $type->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Document type updated.',
            'code_changed' => $codeChanged,
            'data' => $type->fresh(),
        ]);
    }

    public function destroy(VendorDocumentType $type): JsonResponse
    {
        $inUse = $type->vendorDocuments()->exists();

        if ($inUse) {
            return response()->json([
                'success' => false,
                'message' => 'This document type has been used by vendors and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $type->countryRequirements()->delete();
        $type->delete();

        return response()->json(['success' => true, 'message' => 'Document type deleted.']);
    }

    public function toggleActive(VendorDocumentType $type): JsonResponse
    {
        $type->update(['is_active' => !$type->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $type->is_active,
            'message' => $type->is_active ? 'Document type activated.' : 'Document type deactivated.',
        ]);
    }

    public function updateRequirement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_document_type_id' => ['required', 'exists:vendor_document_types,id'],
            'country_id' => ['required', 'exists:countries,id'],
            'requirement_level' => ['required', Rule::in(['mandatory', 'optional', 'not_applicable'])],
        ]);

        $requirement = VendorDocumentCountryRequirement::updateOrCreate(
            [
                'vendor_document_type_id' => $data['vendor_document_type_id'],
                'country_id' => $data['country_id'],
            ],
            ['requirement_level' => $data['requirement_level']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Requirement saved.',
            'data' => $requirement,
        ]);
    }
}
