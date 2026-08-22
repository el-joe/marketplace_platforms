<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin-editable FAQ entries rendered on the public portal FAQ sections.
 *
 * `context` groups FAQs by which portal partial consumes them, e.g.
 * 'seller' (portal/partials/faq.blade.php), 'product_ads'
 * (portal/partials/product-faq.blade.php), 'display_ads'
 * (portal/partials/display-faq.blade.php).
 */
class Faq extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const CONTEXTS = ['seller', 'product_ads', 'display_ads'];

    protected $fillable = [
        'context',
        'question_en',
        'question_ar',
        'answer_en',
        'answer_ar',
        'sort_order',
        'is_active',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForContext(Builder $query, string $context): Builder
    {
        return $query->where('context', $context);
    }

    public function localizedQuestion(): string
    {
        return session('locale', 'ar') === 'ar' ? $this->question_ar : $this->question_en;
    }

    public function localizedAnswer(): string
    {
        return session('locale', 'ar') === 'ar' ? $this->answer_ar : $this->answer_en;
    }
}
