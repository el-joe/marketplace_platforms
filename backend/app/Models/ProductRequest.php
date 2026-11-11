<?php

namespace App\Models;

use App\Enums\ProductRequestStatus;
use Database\Factories\ProductRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A vendor's or marketer's request to add a new product to the catalog.
 *
 * Approval happens by an admin completing the full product form
 * (Admin\ProductController::store with product_request_id), which links the
 * created product through product_id. See App\Services\Catalog\ProductRequestService.
 *
 * @property string $id
 * @property string $request_number
 * @property string $requester_type
 * @property ?string $vendor_id
 * @property ?string $marketer_id
 * @property ?string $requested_by_type
 * @property ?string $requested_by_id
 * @property ?string $country_id
 * @property string $name_en
 * @property string $name_ar
 * @property string $category_id
 * @property ?string $brand_id
 * @property ?string $brand_name
 * @property ?string $gtin
 * @property ?string $model_number
 * @property string $description
 * @property ?string $variants_note
 * @property ?string $reference_url
 * @property ?string $notes
 * @property array<int, string> $images
 * @property ProductRequestStatus $status
 * @property ?string $rejection_reason
 * @property ?string $reviewed_by
 * @property ?Carbon $reviewed_at
 * @property ?string $product_id
 */
class ProductRequest extends Model
{
    /** @use HasFactory<ProductRequestFactory> */
    use HasFactory, HasUuids;

    public const REQUESTER_VENDOR = 'vendor';

    public const REQUESTER_MARKETER = 'marketer';

    /** Public disk folder holding each request's images: product-requests/{id}/ */
    public const IMAGE_DIRECTORY = 'product-requests';

    protected $fillable = [
        'request_number',
        'requester_type',
        'vendor_id',
        'marketer_id',
        'requested_by_type',
        'requested_by_id',
        'country_id',
        'name_en',
        'name_ar',
        'category_id',
        'brand_id',
        'brand_name',
        'gtin',
        'model_number',
        'description',
        'variants_note',
        'reference_url',
        'notes',
        'images',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'product_id',
    ];

    protected $attributes = [
        'status' => 'pending',
        'images' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductRequestStatus::class,
            'images' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $productRequest): void {
            $productRequest->request_number ??= static::generateRequestNumber();
        });
    }

    /**
     * Human-friendly reference, e.g. PR-20261111-7KQ2M. Retries on the rare collision;
     * the unique index on request_number is the final guard.
     */
    public static function generateRequestNumber(): string
    {
        do {
            $number = 'PR-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (static::query()->where('request_number', $number)->exists());

        return $number;
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    /**
     * The VendorAdmin or MarketerAdmin who submitted the request.
     */
    public function requestedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ProductRequestStatus::Pending->value);
    }

    public function scopeForVendor(Builder $query, string $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeForMarketer(Builder $query, string $marketerId): Builder
    {
        return $query->where('marketer_id', $marketerId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === ProductRequestStatus::Pending;
    }

    public function isFromVendor(): bool
    {
        return $this->requester_type === self::REQUESTER_VENDOR;
    }

    public function isFromMarketer(): bool
    {
        return $this->requester_type === self::REQUESTER_MARKETER;
    }

    /**
     * Public URLs of the request images, in submission order.
     *
     * @return Collection<int, string>
     */
    public function imageUrls(): Collection
    {
        return collect($this->images ?? [])
            ->filter()
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->values();
    }

    /**
     * The reference link, only when it is a plain http(s) URL and therefore safe to use as an href.
     */
    public function linkableReferenceUrl(): ?string
    {
        $url = trim((string) $this->reference_url);

        return preg_match('~^https?://~i', $url) === 1 ? $url : null;
    }

    /**
     * Requested product name in the current locale, falling back to the other language.
     */
    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar'
            ? ($this->name_ar ?: $this->name_en)
            : ($this->name_en ?: $this->name_ar);
    }

    /**
     * Store name of the requesting vendor or display name of the requesting marketer.
     */
    public function requesterName(): string
    {
        return $this->isFromVendor()
            ? (string) ($this->vendor?->store_name ?? '—')
            : (string) ($this->marketer?->name ?? '—');
    }
}
