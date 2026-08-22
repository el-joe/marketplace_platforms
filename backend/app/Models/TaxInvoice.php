<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TaxInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'order_id',
        'vendor_id',
        'customer_id',
        'subtotal',
        'tax',
        'total',
        'currency',
        'pdf_media_id',
        'submitted_to_authority',
        'authority_reference',
        'submitted_at',
        'issued_at',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** PDF stored as a file morph */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
