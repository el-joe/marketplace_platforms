<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryCategory extends Model
{
    use HasUuids;
    protected $table = 'country_categories';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'country_id',
        'category_id',
        'is_available',
        'commission_fbp_pct',
        'commission_fbp_fixed',
        'commission_fbn_pct',
        'commission_fbn_fixed',
        'unavailable_reason',
        'notes',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'is_available'        => 'boolean',
        'commission_fbp_pct'  => 'decimal:2',
        'commission_fbp_fixed'=> 'integer',
        'commission_fbn_pct'  => 'decimal:2',
        'commission_fbn_fixed'=> 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
