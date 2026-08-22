<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\WalletOwnerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Customer extends Authenticatable implements JWTSubject
{
    use HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'phone',
        'phone_verified_at',
        'password',
        'country_id',
        'status',
        'locale',
        'marketing_email_enabled',
        'marketing_sms_enabled',
        'marketing_whatsapp_enabled',
        'date_of_birth',
        'last_login_at',
        'last_login_ip',
        'total_orders',
        'total_spent',
        'referral_code',
        'referred_by',
        'loyalty_points',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'loyalty_points' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'password' => 'hashed',
            'status' => CustomerStatus::class,
            'marketing_email_enabled' => 'boolean',
            'marketing_sms_enabled' => 'boolean',
            'marketing_whatsapp_enabled' => 'boolean',
        ];
    }

    // ── JWTSubject ────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'guard' => 'customer',
            'country_id' => $this->country_id ?? null,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Customer::class, 'referred_by');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function receivers(): HasMany
    {
        return $this->hasMany(CustomerReceiver::class);
    }

    public function otpTokens(): HasMany
    {
        return $this->hasMany(CustomerOtpToken::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'requester_user_id');
    }

    public function classifiedListings(): MorphMany
    {
        return $this->morphMany(ClassifiedListing::class, 'seller');
    }

    public function classifiedInquiries(): HasMany
    {
        return $this->hasMany(ClassifiedInquiry::class);
    }

    public function travelBookings(): HasMany
    {
        return $this->hasMany(TravelBooking::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'owner_id')->where('owner_type', WalletOwnerType::Customer);
    }

    public function customerWallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class, 'customer_id');
    }

    public function warrantyClaims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    public function purchasedGiftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'purchased_by_customer_id');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'tokenable');
    }
}
