<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelPackageMedia extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'travel_package_id',
        'media_type',
        'file_path',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    public function url(): string
    {
        return asset('storage/' . $this->file_path);
    }
}
