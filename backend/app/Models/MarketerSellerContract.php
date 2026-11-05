<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller's signature on a seller-marketer collaboration contract (kind seller_marketer). Distinct from VendorContract
 * (seller <-> platform) and from MarketerContractAcceptance (marketer onboarding contract). Rows are frozen at signing:
 * only the status may change afterwards.
 */
class MarketerSellerContract extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'contract_template_id', 'template_version', 'vendor_id', 'marketer_id', 'marketer_campaign_id', 'parent_id', 'country_id',
        'marketer_job_key', 'language_signed', 'content_mode', 'rendered_content', 'rendered_content_hash', 'variables',
        'signed_file_path', 'signed_file_name', 'signed_file_mime', 'signed_by_vendor_admin_id', 'signer_name', 'signed_ip',
        'signed_user_agent', 'signed_at', 'status', 'revoked_at', 'revoked_reason',
    ];

    protected $casts = [
        'variables' => 'array',
        'signed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'template_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $contract) {
            if ($contract->isDirty(['rendered_content', 'rendered_content_hash', 'variables', 'contract_template_id', 'template_version', 'signed_at'])) {
                throw new \LogicException('A signed collaboration contract is immutable.');
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class, 'contract_template_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class, 'marketer_campaign_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(VendorAdmin::class, 'signed_by_vendor_admin_id');
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
