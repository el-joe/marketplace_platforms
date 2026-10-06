<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorCategoryEnrollment extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending_signature';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_RE_SIGN = 're_sign_required';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'vendor_id',
        'classified_category_id',
        'category_scope',
        'product_category_id',
        'status',
        'active_contract_id',
        'requested_at',
        'signed_at',
        'revoked_at',
        'revoked_by_admin_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'signed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }

    public function activeContract(): BelongsTo
    {
        return $this->belongsTo(VendorContract::class, 'active_contract_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(VendorContract::class, 'vendor_category_enrollment_id');
    }

    /**
     * The category this enrollment is about, whichever scope it belongs to.
     */
    public function category(): ClassifiedCategory|Category|null
    {
        return $this->category_scope === 'product' ? $this->productCategory : $this->classifiedCategory;
    }
}
