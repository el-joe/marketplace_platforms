<?php

namespace App\Models;

use App\Enums\SupportTicketRequesterRole;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SupportTicket extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'requester_role' => SupportTicketRequesterRole::class,
            'status' => SupportTicketStatus::class,
        ];
    }

    protected $fillable = [
        'id',
        'ticket_number',
        'requester_user_id',
        'requester_role',
        'category',
        'priority',
        'status',
        'subject',
        'description',
        'assigned_to_admin_id',
        'related_order_id',
        'related_product_id',
        'first_response_at',
        'resolved_at',
        'satisfaction_rating',
        'satisfaction_comment',
    ];

    /**
     * Requester is a Customer (requester_role = 'customer')
     * or a Vendor (requester_role = 'seller').
     * Use the relevant helper based on requester_role.
     */
    public function requesterCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'requester_user_id');
    }

    public function requesterVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'requester_user_id');
    }

    public function requesterTravelAgency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class, 'requester_user_id');
    }

    public function assignedToAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to_admin_id');
    }

    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
