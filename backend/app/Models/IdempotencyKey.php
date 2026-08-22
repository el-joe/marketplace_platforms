<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'request_hash',
        'operation_type',
        'reference_type',
        'reference_id',
        'response_status',
        'response_body',
        'expires_at',
    ];

    //
}
