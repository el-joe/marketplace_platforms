<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A place a seller-marketer contract is shown ("Display On"). Database-driven: a destination is either the general
 * contracts section, the seller profile, or the profile of every marketer holding a marketer job (influencer /
 * celebrity, broker, affiliate). See the create_contract_display_destinations migration.
 */
class ContractDisplayDestination extends Model
{
    use HasUuids;

    public const TARGET_GENERAL = 'general';

    public const TARGET_MARKETER_JOB = 'marketer_job';

    public const TARGET_VENDOR_PROFILE = 'vendor_profile';

    protected $fillable = ['key', 'target_type', 'marketer_job_key', 'name_en', 'name_ar', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(ClassifiedContractTemplate::class, 'contract_template_display_destination', 'destination_id', 'contract_template_id')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
