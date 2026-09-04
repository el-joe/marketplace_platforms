<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LiveStreamComment extends Model
{
    use HasUuids;

    protected $fillable = [
        'live_stream_id', 'customer_id', 'guest_name', 'body',
    ];

    public function stream()
    {
        return $this->belongsTo(LiveStream::class, 'live_stream_id');
    }
}
