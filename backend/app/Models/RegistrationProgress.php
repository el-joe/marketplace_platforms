<?php

namespace App\Models;

use App\Enums\RegistrationFlow;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How far one visitor got in one registration wizard. Completing the wizard sets
 * `completed_at` and links the created vendor / travel agency.
 */
class RegistrationProgress extends Model
{
    use HasUuids;

    protected $table = 'registration_progress';

    public $timestamps = false;

    protected $fillable = [
        'visitor_id',
        'flow',
        'country_code',
        'highest_step_reached',
        'last_step_completed',
        'started_at',
        'first_submitted_at',
        'last_activity_at',
        'completed_at',
        'vendor_id',
        'travel_agency_id',
    ];

    protected $casts = [
        'flow' => RegistrationFlow::class,
        'highest_step_reached' => 'integer',
        'last_step_completed' => 'integer',
        'started_at' => 'datetime',
        'first_submitted_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(PortalVisitor::class, 'visitor_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function travelAgency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RegistrationStepEvent::class);
    }
}
