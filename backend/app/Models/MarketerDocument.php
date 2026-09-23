<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerDocument extends Model
{
    use HasUuids;

    public const TYPE_CV = 'cv';

    public const TYPE_CERTIFICATION = 'certification';

    protected $fillable = ['marketer_id', 'type', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    protected $casts = ['size' => 'integer'];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }
}
