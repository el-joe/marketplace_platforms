<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's recorded acceptance of a customer purchase contract (kind customer_purchase) for one classified
 * advertisement. Distinct from VendorContract (seller <-> platform) and MarketerSellerContract (seller <-> marketer).
 * Rows are frozen at signing: only the status (and its cancellation note) may change afterwards.
 */
class CustomerContract extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_CANCELLED = 'cancelled';

    public const PURPOSE_PURCHASE = 'purchase';

    public const PURPOSE_RENTAL = 'rental';

    protected $fillable = [
        'customer_id', 'contract_template_id', 'template_version', 'classified_listing_id', 'classified_inquiry_id',
        'classified_category_id', 'country_id', 'purpose', 'customer_name', 'customer_email', 'listing_number', 'listing_title',
        'language_signed', 'content_mode', 'rendered_content', 'rendered_content_hash', 'variables',
        'signed_file_path', 'signed_file_name', 'signed_file_mime', 'signer_name', 'signed_ip', 'signed_user_agent',
        'signed_at', 'status', 'cancelled_at', 'cancelled_reason',
    ];

    protected $casts = [
        'variables' => 'array',
        'signed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'template_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $contract) {
            if ($contract->isDirty(['rendered_content', 'rendered_content_hash', 'variables', 'contract_template_id', 'template_version', 'signed_at', 'customer_id', 'signer_name'])) {
                throw new \LogicException('A signed customer contract is immutable.');
            }
        });
    }

    /** The contract purpose of a listing_purpose value (sale / rent). */
    public static function purposeFor(?string $listingPurpose): string
    {
        return $listingPurpose === 'rent' ? self::PURPOSE_RENTAL : self::PURPOSE_PURCHASE;
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class, 'contract_template_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ClassifiedListing::class, 'classified_listing_id')->withTrashed();
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(ClassifiedInquiry::class, 'classified_inquiry_id');
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class, 'classified_category_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function hasSignedFile(): bool
    {
        return $this->signed_file_path !== null;
    }
}
