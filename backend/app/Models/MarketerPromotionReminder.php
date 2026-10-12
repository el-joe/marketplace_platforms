<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Marks that the last-week reminder was sent for marketer/category/month (unique key = idempotency). */
class MarketerPromotionReminder extends Model
{
    use HasUuids;

    protected $fillable = ['marketer_id', 'promotion_category', 'period', 'done', 'target', 'whatsapp_sent'];

    protected $casts = ['whatsapp_sent' => 'boolean'];
}
