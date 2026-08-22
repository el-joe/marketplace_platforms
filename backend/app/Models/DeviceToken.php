<?php

namespace App\Models;

use App\Enums\DeviceTokenPlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeviceToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'token',
        'platform',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => DeviceTokenPlatform::class,
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }
}
