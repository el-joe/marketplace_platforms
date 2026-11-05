<?php

namespace App\Models;

use App\Enums\RegistrationStepEvent as StepEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationStepEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'registration_progress_id',
        'step',
        'event',
        'occurrences',
        'occurred_at',
    ];

    protected $casts = [
        'step' => 'integer',
        'event' => StepEvent::class,
        'occurrences' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function progress(): BelongsTo
    {
        return $this->belongsTo(RegistrationProgress::class, 'registration_progress_id');
    }
}
