<?php

namespace App\Models;

use App\Enums\AdvertiseInquiryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdvertiseInquiry extends Model
{
    use HasUuids;

    protected $casts = [
        'status' => AdvertiseInquiryStatus::class,
    ];

    protected $fillable = [
        'country', 'name', 'email', 'company_name', 'phone', 'description', 'status',
    ];
}
