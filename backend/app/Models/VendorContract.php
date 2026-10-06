<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorContract extends Model
{
    use HasUuids;

    /**
     * Rendered text, variables and hash are frozen at signing. Only status and revocation fields change afterwards.
     */
    protected $fillable = [
        'vendor_id',
        'vendor_category_enrollment_id',
        'classified_category_id',
        'category_scope',
        'product_category_id',
        'contract_template_id',
        'template_version',
        'language_signed',
        'rendered_content',
        'rendered_content_hash',
        'variables',
        'signature_path',
        'signed_by_vendor_admin_id',
        'signer_name',
        'signed_ip',
        'signed_user_agent',
        'signed_at',
        'status',
        'pdf_path',
        'revoked_at',
        'revoked_reason',
    ];

    protected $casts = [
        'template_version' => 'integer',
        'variables' => 'array',
        'signed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(VendorCategoryEnrollment::class, 'vendor_category_enrollment_id');
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class);
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }

    public function signedByVendorAdmin(): BelongsTo
    {
        return $this->belongsTo(VendorAdmin::class, 'signed_by_vendor_admin_id');
    }
}
