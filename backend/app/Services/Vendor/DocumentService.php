<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorDocumentType;
use Illuminate\Http\UploadedFile;

class DocumentService
{
    public function upload(Vendor $vendor, string $typeCode, UploadedFile $file): VendorDocument
    {
        $docType = VendorDocumentType::where('code', $typeCode)->where('is_active', true)->firstOrFail();

        $path = $file->store("vendors/{$vendor->id}/documents", 'public');

        // Replace existing document of the same type, or create new.
        $doc = VendorDocument::firstOrNew([
            'vendor_id'               => $vendor->id,
            'vendor_document_type_id' => $docType->id,
        ]);

        $doc->fill([
            'file_path'        => $path,
            'status'           => 'pending',
            'rejection_reason' => null,
        ])->save();

        return $doc->fresh();
    }
}
