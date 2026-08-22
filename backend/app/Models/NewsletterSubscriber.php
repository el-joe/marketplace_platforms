<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSubscriber extends Model
{
    use HasUuids;

    protected $fillable = [
        'email', 'country_id', 'customer_id', 'source',
        'locale', 'status', 'unsubscribe_token', 'ip_address',
        'unsubscribed_at',
    ];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Generate a unique unsubscribe token for a subscriber.
     */
    public static function generateUnsubscribeToken(): string
    {
        return hash('sha256', uniqid('nl_', true) . random_bytes(16));
    }
}
