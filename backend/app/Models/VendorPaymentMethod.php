<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FBM (vendor-owned shipping) — which platform payment gateways a vendor
 * has enabled for their own customers at checkout. Only meaningful/editable
 * for FBM vendors; see App\Http\Controllers\Partner\PaymentMethodController.
 */
class VendorPaymentMethod extends Model
{
    protected $fillable = [
        'vendor_id',
        'payment_gateway_id',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }
}
