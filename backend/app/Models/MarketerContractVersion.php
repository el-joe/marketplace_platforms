<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketerContractVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_contract_id',
        'version_number',
        'content_type',
        'file_url',
        'text_content',
        'title_en',
        'title_ar',
        'is_active',
        'uploaded_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MarketerContract::class, 'marketer_contract_id');
    }

    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(MarketerContractAcceptance::class, 'marketer_contract_version_id');
    }
}
