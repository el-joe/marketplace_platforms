<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarrantyClaim extends Model
{
    use HasUuids;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RESOLVED = 'resolved';

    public const LISTING_TYPE_VENDOR = 'vendor_listing';
    public const LISTING_TYPE_ADMIN = 'admin_listing';

    public const ISSUE_TYPE_DEFECTIVE = 'defective';
    public const ISSUE_TYPE_NOT_WORKING = 'not_working';
    public const ISSUE_TYPE_PHYSICAL_DAMAGE = 'physical_damage';
    public const ISSUE_TYPE_MISSING_PARTS = 'missing_parts';
    public const ISSUE_TYPE_SOFTWARE_ISSUE = 'software_issue';
    public const ISSUE_TYPE_OTHER = 'other';

    public const RESOLUTION_REPAIR = 'repair';
    public const RESOLUTION_REPLACE = 'replace';
    public const RESOLUTION_REFUND = 'refund';
    public const RESOLUTION_NO_ACTION = 'no_action';

    protected function casts(): array
    {
        return [
            'evidence_files' => 'array',
            'purchase_date' => 'date',
            'warranty_expires_at' => 'date',
            'covered_by_platform_warranty' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    protected $fillable = [
        'id',
        'claim_number',
        'customer_id',
        'order_item_id',
        'product_id',
        'vendor_id',
        'listing_type',
        'issue_type',
        'issue_description',
        'purchase_date',
        'warranty_expires_at',
        'covered_by_platform_warranty',
        'evidence_files',
        'status',
        'resolution',
        'resolution_notes',
        'resolved_by_admin_id',
        'vendor_response',
        'resolved_at',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function adminListing(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            AdminListing::class,
            ProductVariant::class,
            'product_id',
            'product_variant_id',
            'product_id',
            'id'
        );
    }

    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WarrantyClaimMessage::class);
    }
}
