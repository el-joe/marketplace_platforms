<?php

namespace App\Models;

use App\Enums\ReviewVote as ReviewVoteEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewVote extends Model
{
    protected function casts(): array
    {
        return [
            'vote' => ReviewVoteEnum::class,
        ];
    }

    protected $fillable = [
        'review_id',
        'customer_id',
        'vote',
        'created_at',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
