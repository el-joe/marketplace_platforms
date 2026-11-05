<?php

namespace App\Models;

use App\Enums\ContractKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassifiedContractTemplate extends Model
{
    use HasFactory;
    use HasUuids;

    public const SCOPE_CLASSIFIED = 'classified';

    public const SCOPE_PRODUCT = 'product';

    public const SCOPE_PLATFORM = 'platform';

    /** Seller <-> marketer (influencer / celebrity / broker) collaboration contracts. */
    public const SCOPE_MARKETER = 'marketer';

    /** Customer purchase contracts: signed by a customer before buying / renting a classified item. */
    public const SCOPE_CUSTOMER = 'customer';

    public const MODE_EDITOR = 'editor';

    public const MODE_FILE = 'file';

    public const ACCEPTANCE_CLICK_WRAP = 'click_wrap';

    public const ACCEPTANCE_NOTICE = 'notice';

    protected $fillable = [
        'classified_category_id', 'category_scope', 'contract_kind', 'product_category_id', 'country_id', 'is_country_default', 'show_on_public_profile',
        'name', 'content_mode', 'version', 'content_en', 'content_ar', 'files', 'is_active', 'is_published',
        'acceptance_mode', 'notice_days', 'effective_at', 'published_at', 'change_summary',
        'variables_schema', 'created_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'is_country_default' => 'boolean',
        'show_on_public_profile' => 'boolean',
        'contract_kind' => ContractKind::class,
        'variables_schema' => 'array',
        'files' => 'array',
        'notice_days' => 'integer',
        'effective_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The kind always follows the scope, so no creator has to remember to set it.
        static::saving(function (self $template) {
            $template->contract_kind = ContractKind::forScope($template->category_scope);
        });
    }

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
            '{{marketer.name}}' => 'Marketer name (seller-marketer contracts)',
            '{{marketer.type}}' => 'Marketer type: influencer / broker / affiliate (seller-marketer contracts)',
            '{{campaign.title}}' => 'Campaign title (seller-marketer contracts)',
            '{{customer.name}}' => 'Customer name (customer purchase contracts)',
            '{{customer.email}}' => 'Customer email (customer purchase contracts)',
            '{{customer.signer_name}}' => 'Name typed by the customer (filled at signing)',
            '{{listing.number}}' => 'Advertisement number (customer purchase contracts)',
            '{{listing.title}}' => 'Advertisement title (customer purchase contracts)',
            '{{listing.price}}' => 'Advertisement price with currency (customer purchase contracts)',
            '{{listing.purpose}}' => 'Sale or rent (customer purchase contracts)',
            '{{listing.country}}' => 'Advertisement country (customer purchase contracts)',
            '{{seller.name}}' => 'Seller / store name of the advertisement (customer purchase contracts)',
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function displayDestinations(): BelongsToMany
    {
        return $this->belongsToMany(ContractDisplayDestination::class, 'contract_template_display_destination', 'contract_template_id', 'destination_id')->withTimestamps();
    }

    /**
     * Specific marketers a seller-marketer template is linked to (in addition to / instead of destinations).
     */
    public function marketers(): BelongsToMany
    {
        return $this->belongsToMany(Marketer::class, 'contract_template_marketers', 'contract_template_id', 'marketer_id')->withTimestamps();
    }

    public function isMarketerScope(): bool
    {
        return $this->category_scope === self::SCOPE_MARKETER;
    }

    public function isCustomerScope(): bool
    {
        return $this->category_scope === self::SCOPE_CUSTOMER;
    }

    /**
     * Published, active and already in force (a scheduled version is not yet).
     */
    public function scopeInForce(Builder $query): Builder
    {
        return $query->enforceable()->where(fn (Builder $q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', now()));
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ContractCountryAssignment::class, 'contract_template_id');
    }

    /**
     * Templates that serve a country: its own (country_id = country) and the legacy country-agnostic ones (NULL).
     * Resolution prefers the first and only uses the second when the country has none (see ContractResolver).
     */
    public function scopeForCountryOrLegacy(Builder $query, ?string $countryId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('country_id')->when($countryId, fn (Builder $c) => $c->orWhere('country_id', $countryId)));
    }

    public function isLegacyCountryAgnostic(): bool
    {
        return $this->country_id === null;
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

    public function isFileMode(): bool
    {
        return $this->content_mode === self::MODE_FILE;
    }

    public function isPlatformScope(): bool
    {
        return $this->category_scope === self::SCOPE_PLATFORM;
    }

    /**
     * Uploaded document for a language. Arabic is the official language, so a missing language falls back to the other one.
     *
     * @return array{path: string, name: string, mime: string, size: int, sha256: string, pdf_path: ?string, language: string}|null
     */
    public function fileFor(string $language): ?array
    {
        $files = $this->files ?? [];
        $language = $language === 'ar' ? 'ar' : 'en';
        $fallback = $language === 'ar' ? 'en' : 'ar';
        $resolved = isset($files[$language]) ? $language : (isset($files[$fallback]) ? $fallback : null);

        return $resolved ? $files[$resolved] + ['language' => $resolved] : null;
    }

    /**
     * Scheduled: published for vendors but not yet in force (notice period still running).
     */
    public function isScheduled(): bool
    {
        return $this->is_published && $this->effective_at !== null && $this->effective_at->isFuture();
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
