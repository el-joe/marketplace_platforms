<?php

namespace App\Models;

use App\Enums\LanguageDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Language extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'native_name',
        'english_name',
        'direction',
        'is_active',
    ];

    protected $casts = [
        'direction' => LanguageDirection::class,
        'is_active' => 'boolean',
    ];
}
