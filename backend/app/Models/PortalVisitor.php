<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An anonymous portal visitor. `id` is the value of the visitor cookie (a random UUID);
 * no name, email, phone or raw IP is ever stored here.
 */
class PortalVisitor extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'first_seen_at',
        'last_seen_at',
        'country_code',
        'device_type',
        'locale',
        'referrer_host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'ip_hash',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function pageViews(): HasMany
    {
        return $this->hasMany(PortalPageView::class, 'visitor_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(RegistrationProgress::class, 'visitor_id');
    }
}
