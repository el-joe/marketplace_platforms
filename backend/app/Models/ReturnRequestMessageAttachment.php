<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestMessageAttachment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'return_request_message_id',
        'file_path',
        'file_type',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ReturnRequestMessage::class, 'return_request_message_id');
    }
}
