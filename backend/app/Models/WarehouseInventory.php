<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class WarehouseInventory extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_listing_id',
        'admin_listing_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        // quantity_available is GENERATED VIRTUAL — NEVER in fillable
        'quantity_inbound',
        'quantity_damaged',
        'bin_location',
        'reorder_point',
        'last_counted_at',
    ];

    protected $casts = [
        'last_counted_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class, 'vendor_listing_id');
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->whereNotNull('reorder_point')
            ->whereRaw('quantity_on_hand <= reorder_point');
    }

    public function scopeOutOfStock(Builder $q): Builder
    {
        return $q->whereRaw('(quantity_on_hand - quantity_reserved) <= 0');
    }

    public function scopeForWarehouse(Builder $q, string $warehouseId): Builder
    {
        return $q->where('warehouse_inventories.warehouse_id', $warehouseId);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getProductNameAttribute(): string
    {
        return $this->vendorListing?->productVariant?->product?->name_en ?? 'Unknown Product';
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $img = $this->vendorListing?->productVariant?->product
                ?->images()->where('is_primary', 1)->first();

        return $img ? Storage::disk($img->disk)->url($img->path) : null;
    }
}
