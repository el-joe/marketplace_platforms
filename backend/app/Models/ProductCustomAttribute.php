<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCustomAttribute extends Model
{
    public const TYPES = ['text', 'number', 'select', 'checkbox', 'notes'];

    /** Fixed body-measurement presets (label EN/AR), all numeric in cm. */
    public const PRESETS = [
        'length' => ['Length', 'الطول'],
        'width' => ['Width', 'العرض'],
        'chest' => ['Chest', 'الصدر'],
        'sleeve' => ['Sleeve', 'الكم'],
        'sleeve_from_neck' => ['Sleeve from neck', 'الكم من الرقبة'],
    ];

    use HasUuids;

    protected $fillable = [
        'product_id',
        'label',
        'type',
        'options',
        'unit',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Customer-facing definition used by every storefront payload (PDP,
     * product cards, cart) so the add-to-cart modal can render the right input.
     *
     * @return array{id: string, label: string, unit: ?string, type: string, options: array<int, string>, is_required: bool, sort_order: int}
     */
    public function toCustomerArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'unit' => $this->unit,
            'type' => $this->type ?: 'text',
            'options' => array_values($this->options ?? []),
            'is_required' => (bool) $this->is_required,
            'sort_order' => (int) $this->sort_order,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItemValues(): HasMany
    {
        return $this->hasMany(CartItemCustomAttributeValue::class);
    }

    public function orderItemValues(): HasMany
    {
        return $this->hasMany(OrderItemCustomAttributeValue::class);
    }
}
