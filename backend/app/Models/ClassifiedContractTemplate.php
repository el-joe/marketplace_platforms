<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassifiedContractTemplate extends Model
{
    use HasUuids;

    public const SCOPE_CLASSIFIED = 'classified';

    public const SCOPE_PRODUCT = 'product';

    protected $fillable = [
        'classified_category_id', 'category_scope', 'product_category_id',
        'name', 'version', 'content_en', 'content_ar', 'is_active', 'is_published',
        'variables_schema', 'created_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'variables_schema' => 'array',
    ];

    /**
     * Every variable a contract may reference. Keys are the exact {{…}} tokens used in content.
     *
     * @return array<string, string>
     */
    public static function availableVariables(): array
    {
        return [
            '{{vendor.store_name}}' => 'Store / shop name',
            '{{vendor.business_name}}' => 'Business / company name',
            '{{vendor.business_type}}' => 'Business type',
            '{{vendor.registration_number}}' => 'Commercial registration number',
            '{{vendor.tax_id}}' => 'Tax ID',
            '{{vendor.contact_email}}' => 'Contact email',
            '{{vendor.contact_phone}}' => 'Contact phone',
            '{{vendor.address}}' => 'Business address',
            '{{vendor.country}}' => 'Country',
            '{{vendor.signer_name}}' => 'Name typed by the signer (filled at signing)',
            '{{platform.name_en}}' => 'Platform name (English)',
            '{{platform.name_ar}}' => 'Platform name (Arabic)',
            '{{category.name_en}}' => 'Category name (English)',
            '{{category.name_ar}}' => 'Category name (Arabic)',
            '{{contract.version}}' => 'Template version number',
            '{{contract.date}}' => 'Signing date (dd/mm/yyyy)',
        ];
    }

    /**
     * @return list<string> variable keys without braces, stored in variables_schema
     */
    public static function variableKeys(): array
    {
        return array_map(fn (string $token) => trim($token, '{}'), array_keys(self::availableVariables()));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class, 'classified_category_id');
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ClassifiedListing::class, 'contract_template_id');
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('category_scope', $scope);
    }

    /**
     * Only published, active versions are rendered for vendors.
     */
    public function scopeEnforceable(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('is_active', true);
    }

    /**
     * Stored content as editor HTML. Plain-text templates from before the editor are converted for display.
     */
    public function editorHtml(string $field): string
    {
        $content = (string) $this->{$field};

        return $content === strip_tags($content) ? nl2br(e($content)) : $content;
    }

    public function getContentAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->content_ar : $this->content_en;
    }
}
