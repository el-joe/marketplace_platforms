# PROMPT — Client Meeting Implementation (2026-09-22)
# Sub-Agent Master File · 10 Prompts · Run in order

> **How to use:** Feed each numbered section to a separate Claude Code (Sonnet) session.
> Every section is self-contained: it tells the agent exactly what already exists, what to create, and what invariants must never be violated.
> Run all `php artisan migrate` after each agent finishes before starting the next.

---

## GLOBAL INVARIANTS (apply to every prompt below — never violate)

```
1. All monetary values = BIGINT base-currency integers. Never /100 or *100 except for percentage math.
2. All PKs are UUIDs — use HasUuids trait + $table->uuid('id')->primary() in every new migration.
3. Never edit or delete existing migrations. New migrations only, signed with today's date.
4. Never run `php artisan schema:dump --prune`.
5. Append-only: InventoryMovement rows — never update or delete.
6. `quantity_available` and `quantity_remaining` are VIRTUAL GENERATED columns — never include in $fillable, never write to them.
7. Multi-currency finance: always per-currency rows, never sum across currencies.
8. No JSON blobs for structured relational data — proper relational tables with FKs.
9. Credentials always via Crypt::encryptString().
10. `flash_sale_vendor_invititions` — preserve the double-ti typo everywhere.
```

---

## PROMPT 01 — Pre-requisite: Pending Marketer Jobs Migrations

**Context (CRITICAL — read first):**
Migrations `2026_09_22_190000` through `2026_09_22_195141` exist in the repo but are NOT yet in the DB schema (confirmed: `marketer_jobs`, `marketer_job_categories`, `marketer_marketer_job` tables do NOT exist in `mysql-schema.sql`). The subsequent `2026_09_22_190500_drop_marketer_type_from_marketers_table.php` drops `marketers.marketer_type`.

**Task:**
Run the pending migrations first, then verify:
```bash
php artisan migrate --step
php artisan migrate:status | grep "2026_09_22_19"
```
All six should show `Ran`. If any fail, read the error and fix it before proceeding. This is a pre-requisite for all subsequent prompts — do not skip.

After confirming success, run:
```bash
php artisan schema:dump
```
(without `--prune` — dump only, to update `mysql-schema.sql`).

---

## PROMPT 02 — Commission System: Fixed / Percentage / Both + Open Market Path

### What exists (confirmed by code audit)
- `marketer_category_commissions` table: `marketer_id NOT NULL`, `category_id` nullable, `commission_rate decimal(5,2)` — percentage only, no `commission_mode`, no `commission_flat_amount`.
- `MarketerCategoryCommission` model: no `resolveAmount()`.
- `MarketerCampaignService::resolveDefaultCommission()` only reads product path (`MarketerCategoryCommission`). When `campaign->campaign_category === 'classified'`, it has no commission resolution logic — it falls through to `null` → admin manual approval. **This is a live bug.**
- `marketer_commission_country_settings`: only for products, references `categories` table.
- No `open_market_category_commissions` table. No `marketer_commission_rules` table.

### Critical schema note
`marketer_category_commissions.marketer_id` is currently `NOT NULL`. The plan's unified table `marketer_commission_rules` needs `marketer_id nullable` (null = platform default). This is a different table — do NOT alter the old one.

### What to build

#### 1. Migrations (new files only)

**`2026_09_23_100001_add_commission_mode_to_marketer_category_commissions_table.php`**
```php
$table->enum('commission_mode', ['fixed', 'percentage', 'both'])->default('percentage')->after('commission_rate');
$table->unsignedBigInteger('commission_flat_amount')->nullable()->after('commission_mode')
      ->comment('BIGINT base-currency. Used when commission_mode=fixed|both. No /100.');
```

**`2026_09_23_100002_create_open_market_category_commissions_table.php`**
```php
Schema::create('open_market_category_commissions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('marketer_id')->nullable()->comment('null = platform-wide default for this category');
    $table->uuid('classified_category_id')->nullable()->comment('null = default across all classified categories');
    $table->enum('commission_mode', ['fixed', 'percentage', 'both'])->default('percentage');
    $table->decimal('commission_rate', 5, 2)->default(0)->comment('Percentage, e.g. 8.00 = 8%');
    $table->unsignedBigInteger('commission_flat_amount')->nullable()->comment('BIGINT base-currency. No /100.');
    $table->uuid('updated_by_admin_id')->nullable();
    $table->timestamps();
    $table->foreign('classified_category_id')->references('id')->on('classified_categories')->onDelete('cascade');
    $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
    $table->foreign('updated_by_admin_id')->references('id')->on('admins')->onDelete('set null');
});
```

**`2026_09_23_100003_create_marketer_commission_rules_table.php`** (unified read table, dual-written)
```php
Schema::create('marketer_commission_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('marketer_id')->nullable()->comment('null = platform-wide default');
    $table->enum('scope', ['products', 'open_market', 'travel'])->default('products');
    $table->string('category_type')->nullable()->comment('App\\Models\\Category | ClassifiedCategory | TravelCategory');
    $table->uuid('category_id')->nullable()->comment('null = default for this scope');
    $table->enum('commission_mode', ['fixed', 'percentage', 'both'])->default('percentage');
    $table->decimal('commission_rate', 5, 2)->default(0);
    $table->unsignedBigInteger('commission_flat_amount')->nullable()->comment('BIGINT base-currency. No /100.');
    $table->enum('category_selection_mode', ['all', 'include', 'exclude'])->default('all');
    $table->uuid('updated_by_admin_id')->nullable();
    $table->string('rule_key', 40)->nullable()->unique()->comment('sha1 of marketer_id|scope|category_type|category_id for null-safe uniqueness');
    $table->timestamps();
    $table->foreign('updated_by_admin_id')->references('id')->on('admins')->onDelete('set null');
    $table->index(['marketer_id', 'scope']);
});
```

**`2026_09_23_100004_create_open_market_listing_prices_table.php`**
```php
Schema::create('open_market_listing_prices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('classified_category_id');
    $table->unsignedBigInteger('base_price')->comment('BIGINT base-currency. No /100. Fee the marketer pays per classified listing.');
    $table->string('currency', 3);
    $table->boolean('allow_marketer_override')->default(false)->comment('Marketer may set their own listing price');
    $table->unsignedBigInteger('min_price')->nullable()->comment('Lower bound when allow_marketer_override=true. BIGINT.');
    $table->unsignedBigInteger('max_price')->nullable()->comment('Upper bound when allow_marketer_override=true. BIGINT.');
    $table->uuid('updated_by_admin_id')->nullable();
    $table->timestamps();
    $table->unique('classified_category_id');
    $table->foreign('classified_category_id')->references('id')->on('classified_categories')->onDelete('cascade');
    $table->foreign('updated_by_admin_id')->references('id')->on('admins')->onDelete('set null');
});
```

**`2026_09_23_100005_create_marketer_campaign_category_rules_table.php`**
```php
Schema::create('marketer_campaign_category_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('marketer_id')->nullable()->comment('null = applies platform-wide');
    $table->string('category_type')->comment('product | classified');
    $table->uuid('category_id');
    $table->enum('mode', ['include', 'exclude'])->default('include');
    $table->timestamps();
    $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
    $table->index(['marketer_id', 'category_type']);
});
```

#### 2. Models

**`app/Models/MarketerCategoryCommission.php`** — add to `$fillable` and `$casts`:
```php
// Add to $fillable:
'commission_mode', 'commission_flat_amount',

// Add to casts():
'commission_flat_amount' => 'integer',

// Add method:
public function resolveAmount(int $baseAmount): int
{
    return match ($this->commission_mode ?? 'percentage') {
        'fixed'      => (int) ($this->commission_flat_amount ?? 0),
        'percentage' => (int) round($baseAmount * ((float) $this->commission_rate / 100)),
        'both'       => (int) ($this->commission_flat_amount ?? 0)
                        + (int) round($baseAmount * ((float) $this->commission_rate / 100)),
        default      => 0,
    };
}
```

**`app/Models/OpenMarketCategoryCommission.php`** (new):
```php
class OpenMarketCategoryCommission extends Model
{
    use HasUuids;
    protected $fillable = [
        'marketer_id', 'classified_category_id', 'commission_mode',
        'commission_rate', 'commission_flat_amount', 'updated_by_admin_id',
    ];
    protected function casts(): array
    {
        return ['commission_rate' => 'decimal:2', 'commission_flat_amount' => 'integer'];
    }
    public function resolveAmount(int $baseAmount): int
    {
        return match ($this->commission_mode ?? 'percentage') {
            'fixed'  => (int) ($this->commission_flat_amount ?? 0),
            'percentage' => (int) round($baseAmount * ((float) $this->commission_rate / 100)),
            'both'   => (int) ($this->commission_flat_amount ?? 0)
                        + (int) round($baseAmount * ((float) $this->commission_rate / 100)),
            default  => 0,
        };
    }
    public function classifiedCategory(): BelongsTo { return $this->belongsTo(ClassifiedCategory::class); }
    public function marketer(): BelongsTo { return $this->belongsTo(Marketer::class); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(Admin::class, 'updated_by_admin_id'); }
}
```

**`app/Models/MarketerCommissionRule.php`** (new):
```php
class MarketerCommissionRule extends Model
{
    use HasUuids;
    protected $fillable = [
        'marketer_id', 'scope', 'category_type', 'category_id',
        'commission_mode', 'commission_rate', 'commission_flat_amount',
        'category_selection_mode', 'updated_by_admin_id', 'rule_key',
    ];
    protected static function booted(): void
    {
        static::saving(function (self $rule) {
            $rule->rule_key = sha1(implode('|', [
                $rule->marketer_id ?? 'NULL',
                $rule->scope,
                $rule->category_type ?? 'NULL',
                $rule->category_id ?? 'NULL',
            ]));
        });
    }
    public function resolveAmount(int $baseAmount): int
    {
        return match ($this->commission_mode ?? 'percentage') {
            'fixed'  => (int) ($this->commission_flat_amount ?? 0),
            'percentage' => (int) round($baseAmount * ((float) $this->commission_rate / 100)),
            'both'   => (int) ($this->commission_flat_amount ?? 0)
                        + (int) round($baseAmount * ((float) $this->commission_rate / 100)),
            default  => 0,
        };
    }
}
```

**`app/Models/OpenMarketListingPrice.php`** (new):
```php
class OpenMarketListingPrice extends Model
{
    use HasUuids;
    protected $fillable = [
        'classified_category_id', 'base_price', 'currency',
        'allow_marketer_override', 'min_price', 'max_price', 'updated_by_admin_id',
    ];
    protected function casts(): array
    {
        return [
            'base_price' => 'integer', 'min_price' => 'integer',
            'max_price' => 'integer', 'allow_marketer_override' => 'boolean',
        ];
    }
}
```

#### 3. Fix `MarketerCampaignService::resolveDefaultCommission()`

Replace the entire `resolveDefaultCommission` private method:
```php
private function resolveDefaultCommission(MarketerCampaign $campaign): ?array
{
    // --- CLASSIFIED (open market) path ---
    if ($campaign->campaign_category === 'classified') {
        if (! $campaign->classified_listing_id) {
            return null;
        }
        $classifiedListing = \App\Models\ClassifiedListing::find($campaign->classified_listing_id);
        if (! $classifiedListing) {
            return null;
        }
        $catId = $classifiedListing->classified_category_id;

        $rule = \App\Models\OpenMarketCategoryCommission::where('classified_category_id', $catId)
            ->whereNull('marketer_id')
            ->first()
            ?? \App\Models\OpenMarketCategoryCommission::whereNull('classified_category_id')
            ->whereNull('marketer_id')
            ->first();

        if (! $rule) {
            return null;
        }

        $listingPrice = \App\Models\OpenMarketListingPrice::where('classified_category_id', $catId)->first();
        $baseAmount   = $listingPrice ? (int) $listingPrice->base_price : 0;

        $marketerCommission = $rule->resolveAmount($baseAmount);
        if ($marketerCommission <= 0) {
            return null;
        }
        return [
            'marketer_commission_amount'  => $marketerCommission,
            'platform_commission_amount'  => 0,
        ];
    }

    // --- PRODUCT path (existing logic, refactored) ---
    $category = $campaign->vendorListing?->productVariant?->product?->category
        ?? $campaign->adminListing?->productVariant?->product?->category;

    if (! $category) {
        return null;
    }

    $categoryRate = MarketerCategoryCommission::where('category_id', $category->id)
        ->whereNull('marketer_id')
        ->first();

    $countrySetting = MarketerCommissionCountrySetting::where('country_id', $campaign->country_id)
        ->where('category_id', $category->id)
        ->first();

    $marketerCommission = 0;
    if ($countrySetting) {
        $marketerCommission = (int) $countrySetting->affiliate_commission_amount;
    }

    if ($marketerCommission <= 0) {
        return null;
    }

    $platformCommission = $categoryRate
        ? $categoryRate->resolveAmount($marketerCommission)
        : 0;

    return [
        'marketer_commission_amount' => $marketerCommission,
        'platform_commission_amount' => $platformCommission,
    ];
}
```

#### 4. Admin UI — `Admin/MarketerController.php`
Add two new methods:
- `storeCategoryCommission(Request $request, Marketer $marketer)`: validate `commission_mode`, `commission_rate`, `commission_flat_amount`, `category_selection_mode`, and upsert `MarketerCategoryCommission` AND write dual to `MarketerCommissionRule` (scope=products).
- `storeOpenMarketCommission(Request $request, Marketer $marketer)`: validate same fields + `classified_category_id`, upsert `OpenMarketCategoryCommission` AND write dual to `MarketerCommissionRule` (scope=open_market).

Both methods must also write to `marketer_campaign_category_rules` when `category_selection_mode` is `include` or `exclude`.

#### 5. Blade view — `resources/views/admin/marketers/show.blade.php`
Add two collapsible card sections after the existing commission card:

**Products Commission Card:**
- Dropdown (all / include / exclude) — id: `products_category_selection_mode`
- Multi-select (Select2) of `categories` — visible when mode ≠ all
- Radio: Fixed / Percentage / Both
- Fields: `commission_rate` (%), `commission_flat_amount` (int, base currency) — toggle visibility per radio

**Open Market Commission Card (separate):**
- Same triple radio + fields
- Multi-select of `classified_categories`
- Separate `allow_marketer_override` toggle per category (save to `open_market_listing_prices`)

**Lang keys to add in `lang/en/admin.php` and `lang/ar/admin.php`:**
```
'commission_mode'           => 'Commission Mode',
'commission_mode_fixed'     => 'Fixed Amount',
'commission_mode_percentage'=> 'Percentage',
'commission_mode_both'      => 'Fixed + Percentage',
'commission_flat_amount'    => 'Flat Amount (base currency)',
'category_selection_all'    => 'All Categories',
'category_selection_include'=> 'Specific Categories',
'category_selection_exclude'=> 'All Except',
'open_market_commission'    => 'Open Market Commission',
'allow_marketer_price_override' => 'Allow Marketer to Set Own Price',
```
Arabic translations (add to `lang/ar/admin.php`):
```
'commission_mode'           => 'نوع العمولة',
'commission_mode_fixed'     => 'مبلغ ثابت',
'commission_mode_percentage'=> 'نسبة مئوية',
'commission_mode_both'      => 'ثابت + نسبة',
'commission_flat_amount'    => 'المبلغ الثابت (العملة الأساسية)',
'category_selection_all'    => 'كل الأقسام',
'category_selection_include'=> 'أقسام محددة',
'category_selection_exclude'=> 'كل الأقسام باستثناء',
'open_market_commission'    => 'عمولة السوق المفتوح',
'allow_marketer_price_override' => 'السماح للماركتر بتعديل السعر',
```

#### 6. Marketer portal: open market listing price override
In the marketer's classified listing creation form (`resources/views/marketer/` or API), when `open_market_listing_prices.allow_marketer_override = true` for the listing's category, show a price input bounded by `min_price` / `max_price`. Store the marketer's chosen price on `classified_listings.price` (already a column).

---

## PROMPT 03 — Exclusive Contracts (Marketer × Open Market)

### What exists
- `marketers` table (FK target for `marketer_id`)
- `classified_categories`, `classified_listings` tables
- Admin panel structure at `resources/views/admin/marketers/show.blade.php`
- Existing scheduled command pattern (see `ExpireGiftCards.php` for reference)

### Client answer (confirmed): Exclusivity can be at category level OR specific listing level — both, by case.

### Migrations

**`2026_09_23_200001_create_exclusive_contracts_table.php`**
```php
Schema::create('exclusive_contracts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('marketer_id');
    $table->uuid('classified_category_id')->nullable()->comment('null = all categories');
    $table->uuid('classified_listing_id')->nullable()->comment('null = entire category; set for listing-specific exclusivity');
    $table->timestamp('starts_at');
    $table->timestamp('ends_at');
    $table->enum('status', ['pending', 'active', 'expired', 'revoked'])->default('pending');
    $table->string('contract_file_path')->nullable();
    $table->text('notes')->nullable();
    $table->uuid('created_by_admin_id');
    $table->timestamps();

    $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
    $table->foreign('classified_category_id')->references('id')->on('classified_categories')->onDelete('cascade');
    $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->onDelete('cascade');
    $table->foreign('created_by_admin_id')->references('id')->on('admins')->onDelete('restrict');

    $table->index(['classified_listing_id', 'status']);
    $table->index(['classified_category_id', 'status']);
    $table->index(['marketer_id', 'status']);
});
```

### Model — `app/Models/ExclusiveContract.php`
```php
class ExclusiveContract extends Model
{
    use HasUuids;
    protected $fillable = [
        'marketer_id', 'classified_category_id', 'classified_listing_id',
        'starts_at', 'ends_at', 'status', 'contract_file_path', 'notes', 'created_by_admin_id',
    ];
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
    public function isCurrentlyActive(): bool
    {
        return $this->status === 'active'
            && now()->between($this->starts_at, $this->ends_at);
    }
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
    public function marketer(): BelongsTo { return $this->belongsTo(Marketer::class); }
    public function classifiedCategory(): BelongsTo { return $this->belongsTo(ClassifiedCategory::class); }
    public function classifiedListing(): BelongsTo { return $this->belongsTo(ClassifiedListing::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(Admin::class, 'created_by_admin_id'); }
}
```

### Command — `app/Console/Commands/ExpireExclusiveContracts.php`
```php
// Schedule: daily at 00:05
// Logic: ExclusiveContract::where('status', 'active')->where('ends_at', '<', now())->update(['status' => 'expired']);
// Also: ExclusiveContract::where('status', 'pending')->where('starts_at', '<=', now())->where('ends_at', '>=', now())->update(['status' => 'active']);
```
Register in `routes/console.php`:
```php
Schedule::command('exclusive-contracts:expire')->dailyAt('00:05');
```

### Admin Controller — `app/Http/Controllers/Admin/ExclusiveContractController.php`
Full CRUD. `store()` must validate:
- No existing `active` exclusive contract overlapping `[starts_at, ends_at]` for the same `classified_listing_id` (when set) — a listing cannot have two simultaneous exclusive contracts.
- When `classified_listing_id` is set AND `classified_category_id` is null, auto-fill `classified_category_id` from the listing.
- `starts_at < ends_at` validation.
- `contract_file_path` upload via `Storage::disk('private')` (match existing pattern in `MarketerContractController`).

Routes in `routes/admin.php`:
```php
Route::resource('exclusive-contracts', Admin\ExclusiveContractController::class);
```

Link from `resources/views/admin/marketers/show.blade.php` — add a tab "العقود الحصرية" listing the marketer's exclusive contracts with a "+ إضافة عقد حصري" button.

### API — `app/Http/Controllers/Api/Public/ClassifiedListingDetailController.php` (or whichever handles listing detail)
In the listing detail response, add:
```php
$activeContract = ExclusiveContract::active()
    ->where(function ($q) use ($listing) {
        $q->where('classified_listing_id', $listing->id)
          ->orWhere(function ($q2) use ($listing) {
              $q2->whereNull('classified_listing_id')
                 ->where('classified_category_id', $listing->classified_category_id);
          });
    })
    ->with('marketer:id,name')
    ->first();

// Add to response:
'exclusive_contract' => $activeContract ? [
    'marketer_name' => $activeContract->marketer->name,
    'expires_at'    => $activeContract->ends_at->toIso8601String(),
] : null,
```

### Lang keys (en + ar):
```
'exclusive_contract'         => 'Exclusive Contract' / 'عقد حصري'
'exclusive_contracts'        => 'Exclusive Contracts' / 'العقود الحصرية'
'add_exclusive_contract'     => 'Add Exclusive Contract' / 'إضافة عقد حصري'
'contract_conflict_error'    => 'A conflicting active exclusive contract exists for this listing.' / 'يوجد عقد حصري نشط متعارض لهذا الإعلان.'
```

---

## PROMPT 04 — Coupons: Multi-target + Paid Participation Invitations

### What exists (confirmed)
- `coupons` table: `vendor_id` single FK, `category_id` single FK, `shipping_type_restriction` (fbn/fbp/fbm — **already done**), `coupon_products` pivot.
- `app/Models/Coupon.php`: `belongsTo(Vendor::class)`, `belongsToMany(Product::class, 'coupon_products')` — no multi-vendor, no marketer relations.
- Wallet system: `WalletService::debit()` throws `InsufficientBalanceException` on insufficient funds. `WalletOwnerType` enum supports `vendor` and `marketer`. `payment_gateways` has `bank_transfer` (`type='offline'`).
- `BankTransferGateway` handles manual bank transfer confirmation by admin.

### Sonnet gap to fix (from QC notes)
- Vendor/marketer participation pages must show wallet balance and warn on insufficient funds **before** submission (client-side + server-side).
- Vendor notifications from coupon participation must be restricted to `product_vendor` type only (not `classified_vendor`). Gate with `$vendor->vendor_type === 'product_vendor'`.

### Migrations

**`2026_09_23_300001_create_coupon_vendors_table.php`**
```php
Schema::create('coupon_vendors', function (Blueprint $table) {
    $table->uuid('coupon_id');
    $table->uuid('vendor_id');
    $table->timestamps();
    $table->primary(['coupon_id', 'vendor_id']);
    $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
    $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
});
```

**`2026_09_23_300002_create_coupon_marketers_table.php`**
```php
Schema::create('coupon_marketers', function (Blueprint $table) {
    $table->uuid('coupon_id');
    $table->uuid('marketer_id');
    $table->timestamps();
    $table->primary(['coupon_id', 'marketer_id']);
    $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
    $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
});
```

**`2026_09_23_300003_create_coupon_participation_invitations_table.php`**
```php
Schema::create('coupon_participation_invitations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('coupon_id')->nullable()->comment('Set when invitation creates a draft coupon first; can be null at creation');
    $table->integer('max_participants')->unsigned();
    $table->unsignedBigInteger('min_fee_amount')->comment('BIGINT base-currency. No /100.');
    $table->string('currency', 3);
    $table->timestamp('registration_deadline');
    $table->enum('status', ['open', 'closed', 'fulfilled', 'cancelled'])->default('open');
    $table->text('description')->nullable();
    $table->uuid('created_by_admin_id');
    $table->timestamps();
    $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
    $table->foreign('created_by_admin_id')->references('id')->on('admins');
});
```

**`2026_09_23_300004_create_coupon_participation_requests_table.php`**
```php
Schema::create('coupon_participation_requests', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('invitation_id');
    $table->enum('participant_type', ['vendor', 'marketer']);
    $table->uuid('participant_id')->comment('vendor.id or marketer.id depending on participant_type');
    $table->unsignedBigInteger('offered_fee_amount')->comment('BIGINT base-currency. Must be >= invitation.min_fee_amount. No /100.');
    $table->enum('payment_method', ['wallet', 'bank_transfer'])->default('wallet')->comment('Client answer: choice between wallet and bank transfer');
    $table->string('bank_transfer_proof_path')->nullable()->comment('Upload proof when payment_method=bank_transfer');
    $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
    $table->timestamp('paid_at')->nullable();
    $table->uuid('reviewed_by_admin_id')->nullable();
    $table->timestamps();
    $table->foreign('invitation_id')->references('id')->on('coupon_participation_invitations')->onDelete('cascade');
    $table->foreign('reviewed_by_admin_id')->references('id')->on('admins')->onDelete('set null');
    $table->index(['invitation_id', 'status']);
    $table->index(['participant_type', 'participant_id']);
});
```

### Model updates

**`app/Models/Coupon.php`** — add:
```php
public function vendors(): BelongsToMany
{
    return $this->belongsToMany(Vendor::class, 'coupon_vendors');
}
public function marketers(): BelongsToMany
{
    return $this->belongsToMany(Marketer::class, 'coupon_marketers');
}
```
**Applicability logic** — in the coupon validation service/method (wherever `isApplicableTo` or coupon validation runs):
```php
// vendor scope check (backward-compatible):
if ($this->vendors()->exists()) {
    // new multi-vendor check
    if (! $this->vendors()->where('vendor_id', $order->vendor_id)->exists()) {
        return false;
    }
} elseif ($this->vendor_id && $this->vendor_id !== $order->vendor_id) {
    // legacy single vendor_id check
    return false;
}
```

**`app/Models/CouponParticipationInvitation.php`** (new):
```php
class CouponParticipationInvitation extends Model
{
    use HasUuids;
    protected $fillable = [
        'coupon_id', 'max_participants', 'min_fee_amount', 'currency',
        'registration_deadline', 'status', 'description', 'created_by_admin_id',
    ];
    protected function casts(): array
    {
        return ['registration_deadline' => 'datetime', 'min_fee_amount' => 'integer'];
    }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function requests(): HasMany { return $this->hasMany(CouponParticipationRequest::class, 'invitation_id'); }
    public function approvedRequests(): HasMany
    {
        return $this->requests()->whereIn('status', ['approved', 'paid']);
    }
    public function isFull(): bool
    {
        return $this->approvedRequests()->count() >= $this->max_participants;
    }
}
```

**`app/Models/CouponParticipationRequest.php`** (new) — standard model with `HasUuids`.

### Controllers

**`app/Http/Controllers/Admin/CouponParticipationInvitationController.php`**
- `index()`: list all invitations with request counts.
- `store()`: create invitation, set status=open, notify eligible vendors and marketers (product_vendor only — add guard).
- `show()`: show invitation + paginated requests.
- `approve(CouponParticipationRequest $request)`:
  - If `payment_method=wallet`: immediately debit via `WalletService::debit()`, set status=paid.
  - If `payment_method=bank_transfer`: set status=approved, show admin payment proof, admin clicks "Confirm Payment" → set status=paid.
  - On paid: link participant to `coupon_vendors` or `coupon_marketers`.
  - Check: if invitation is now full (`isFull()`), set invitation status=fulfilled and activate coupon (`$invitation->coupon->update(['is_active' => true])`).
- `reject(CouponParticipationRequest $request)`:
  - If status=paid (bank transfer confirmed but now rejecting): **refund** via `WalletService::credit()`. Client answer: refund is at admin discretion — add a boolean `refund_on_reject` flag in the reject form.

**`app/Http/Controllers/Vendor/CouponParticipationController.php`**
- `index()`: list open invitations visible to this vendor (product_vendor guard: `abort_if(auth('vendor_admin')->user()->vendor->vendor_type !== 'product_vendor', 403)`).
- `store(Request $request, CouponParticipationInvitation $invitation)`:
  - Validate `offered_fee_amount >= invitation->min_fee_amount`.
  - Validate `payment_method` in ['wallet', 'bank_transfer'].
  - If wallet: check balance upfront and return `422 ['error' => 'insufficient_balance', 'balance' => $wallet->balance]` before creating request (Sonnet gap fix).
  - Create `CouponParticipationRequest` with status=pending.

**`app/Http/Controllers/Marketer/CouponParticipationController.php`** — same logic for marketer guard.

### Wallet balance display (Sonnet gap fix)
In both vendor and marketer participation views, before the "Submit Request" button:
```blade
@php
$wallet = \App\Services\WalletService::getOrCreateWalletStatic(/* owner_type, id, currency */);
@endphp
<div class="alert alert-info">
    {{ __('partner.wallet_balance') }}: {{ number_format($wallet->balance / 100, 2) }} {{ $wallet->currency }}
</div>
@if($wallet->balance < $invitation->min_fee_amount)
<div class="alert alert-warning">{{ __('partner.insufficient_balance_warning') }}</div>
@endif
```

### Job — `app/Jobs/ActivateFulfilledCouponInvitationJob.php`
Dispatched from a scheduled command daily: check invitations where `registration_deadline < now()` and `status=open` → close them. If `approved requests >= 1`, activate coupon and set status=fulfilled.

### Notifications
**`app/Notifications/Vendor/CouponParticipationInvitationNotification.php`** (new):
Copy from `CampaignInvitationAcceptedNotification` pattern. Sends via DB + WhatsApp + email. **Only dispatch to `product_vendor` type vendors** (Sonnet gap fix).

**`app/Notifications/Marketer/CouponParticipationInvitationNotification.php`** (new): same for marketer channel.

### Lang keys (en + ar):
```
'coupon_participation'              => 'Coupon Participation' / 'الاشتراك في القسيمة'
'coupon_participation_invitations'  => 'Coupon Participation Invitations' / 'دعوات الاشتراك في القسائم'
'min_fee_amount'                    => 'Minimum Fee' / 'الحد الأدنى للرسوم'
'offered_fee_amount'                => 'Your Offered Fee' / 'الرسوم التي تعرضها'
'registration_deadline'             => 'Registration Deadline' / 'الموعد النهائي للتسجيل'
'wallet_balance'                    => 'Wallet Balance' / 'رصيد المحفظة'
'insufficient_balance_warning'      => 'Your wallet balance is insufficient for the minimum fee.' / 'رصيد محفظتك غير كافٍ للحد الأدنى للرسوم.'
'payment_method_wallet'             => 'Wallet' / 'المحفظة'
'payment_method_bank_transfer'      => 'Bank Transfer' / 'التحويل البنكي'
```

---

## PROMPT 05 — FBM: Private Shipping Companies + Bank Transfer Payment

### What exists (confirmed)
- `shipping_companies` table: no `owner_vendor_id` column.
- `vendor_listings.fulfillment_model` is already an enum `('fbm','fbn','cross_dock')` in the live schema — **plan item "unify to enum" is already done**. Skip the enum migration.
- `payment_gateways`: `bank_transfer` gateway (type=offline) already exists.
- No `FulfillmentModel` PHP Enum needed (the DB column is already enum — just add a PHP Enum to match).

### Migrations

**`2026_09_23_400001_add_owner_vendor_id_to_shipping_companies.php`**
```php
Schema::table('shipping_companies', function (Blueprint $table) {
    $table->uuid('owner_vendor_id')->nullable()->after('status')
          ->comment('null = public (visible to all); set = private to this vendor only (e.g. Carrefour internal fleet)');
    $table->foreign('owner_vendor_id')->references('id')->on('vendors')->onDelete('cascade');
    $table->index('owner_vendor_id');
});
```

### PHP Enum — `app/Enums/FulfillmentModel.php` (new — DB already has the values)
```php
enum FulfillmentModel: string
{
    case Fbm       = 'fbm';
    case Fbn       = 'fbn';
    case CrossDock = 'cross_dock';
}
```
Add cast to `VendorListing` model: `'fulfillment_model' => FulfillmentModel::class`.

### Model — `app/Models/ShippingCompany.php`
Add scope:
```php
public function scopeVisibleTo(Builder $query, string $vendorId): Builder
{
    return $query->where(function ($q) use ($vendorId) {
        $q->whereNull('owner_vendor_id')
          ->orWhere('owner_vendor_id', $vendorId);
    });
}
public function ownerVendor(): BelongsTo
{
    return $this->belongsTo(Vendor::class, 'owner_vendor_id');
}
```

### Apply scope everywhere shipping companies are listed
Search for `ShippingCompany::` usage in controllers. In every listing call for vendor-facing views, replace `ShippingCompany::query()` with `ShippingCompany::visibleTo($vendor->id)`. Admin views keep the unfiltered list but show an "خاص بالبائع" badge on private ones.

### FBM payment gating
In the vendor portal listing creation/edit flow, when `fulfillment_model = 'fbm'`, show payment method selection. The available payment method to surface is **bank transfer** (already a gateway in `payment_gateways`). Store the vendor's preferred payment method per listing in a new migration:

**`2026_09_23_400002_add_fbm_payment_gateway_id_to_vendor_listings.php`**
```php
$table->uuid('fbm_payment_gateway_id')->nullable()->after('fulfillment_model')
      ->comment('For FBM vendors: which gateway customer pays through. Null = platform default.');
$table->foreign('fbm_payment_gateway_id')->references('id')->on('payment_gateways')->onDelete('set null');
```

In the vendor listing create/edit blade view, show this field only when `fulfillment_model = fbm`.
In checkout (customer side), when the order is for an FBM vendor, read `vendor_listing.fbm_payment_gateway_id` and restrict available payment options accordingly (or default to platform gateways if null).

### Admin shipping company form
Add `owner_vendor_id` field (vendor search/select) with note "اتركه فارغًا لشركة شحن عامة". Display "خاص بـ: [vendor name]" in the index table.

### Lang keys (en + ar):
```
'private_shipping_company'          => 'Private Shipping Company' / 'شركة شحن خاصة'
'private_to_vendor'                 => 'Private to vendor' / 'خاص بالبائع'
'fbm_payment_method'                => 'Customer Payment Method (FBM)' / 'طريقة دفع العميل (FBM)'
```

---

## PROMPT 06 — Product Price History + First-Price Snapshot

### What exists
- `vendor_listings.price` is `bigint NOT NULL`. No `first_price` column. No `product_price_history` table.
- `VendorListing` model: standard Eloquent, no price observer.

### Migrations

**`2026_09_23_500001_add_first_price_snapshot_to_vendor_listings.php`**
```php
$table->unsignedBigInteger('first_price')->nullable()->after('price')
      ->comment('BIGINT base-currency. Set once at creation, never updated. No /100.');
$table->timestamp('first_price_locked_at')->nullable()->after('first_price');
$table->boolean('disposable_by_admin')->default(false)->after('first_price_locked_at')
      ->comment('Flagged true when storage fees exceed first_price and vendor has not paid.');
```

**`2026_09_23_500002_create_product_price_history_table.php`**
```php
Schema::create('product_price_history', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('vendor_listing_id');
    $table->unsignedBigInteger('price')->comment('BIGINT base-currency. No /100.');
    $table->enum('source', ['initial', 'update'])->default('initial');
    $table->uuid('recorded_by_admin_id')->nullable()->comment('null = system/vendor self-change');
    $table->timestamp('recorded_at');
    $table->timestamps();
    $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->onDelete('cascade');
    $table->index(['vendor_listing_id', 'recorded_at']);
});
```

**`2026_09_23_500003_backfill_first_price_on_vendor_listings.php`**
```php
// Data migration — set first_price = price, first_price_locked_at = created_at for all existing listings
DB::statement("UPDATE vendor_listings SET first_price = price, first_price_locked_at = created_at WHERE first_price IS NULL");
```

### Observer — `app/Observers/VendorListingObserver.php` (new)
```php
class VendorListingObserver
{
    public function created(VendorListing $listing): void
    {
        // Lock first price on first save (including drafts)
        $listing->updateQuietly([
            'first_price'           => $listing->price,
            'first_price_locked_at' => now(),
        ]);
        ProductPriceHistory::create([
            'vendor_listing_id' => $listing->id,
            'price'             => $listing->price,
            'source'            => 'initial',
            'recorded_at'       => now(),
        ]);
    }

    public function updating(VendorListing $listing): void
    {
        if ($listing->isDirty('price')) {
            ProductPriceHistory::create([
                'vendor_listing_id' => $listing->id,
                'price'             => $listing->price,
                'source'            => 'update',
                'recorded_at'       => now(),
            ]);
            // NEVER update first_price here — it is read-only forever.
        }
    }
}
```

Register in `app/Providers/AppServiceProvider.php`:
```php
VendorListing::observe(VendorListingObserver::class);
```

### Model — `app/Models/ProductPriceHistory.php` (new)
```php
class ProductPriceHistory extends Model
{
    use HasUuids;
    public const UPDATED_AT = null;
    protected $fillable = ['vendor_listing_id', 'price', 'source', 'recorded_by_admin_id', 'recorded_at'];
    protected function casts(): array { return ['price' => 'integer', 'recorded_at' => 'datetime']; }
    public function vendorListing(): BelongsTo { return $this->belongsTo(VendorListing::class); }
}
```

### Admin view
In `resources/views/admin/vendor-listings/show.blade.php` (or wherever listings are detailed), add a "Price History" tab:
- Show `first_price` prominently (locked, not editable).
- Table of `product_price_history` ordered by `recorded_at` desc.
- Badge "⚠ قابل للتصرف" if `disposable_by_admin = true`.

### Command — `app/Console/Commands/FlagOverstoredUnpaidProducts.php` (new)
```php
// Schedule: daily at 01:00
// Logic:
// Join vendor_listings → warehouse_inventories → fbn_storage_fees
// WHERE: fbn_storage_fees.status != 'paid'
// AND vendor_listings.first_price IS NOT NULL
// AND DATEDIFF(NOW(), warehouse_inventories.created_at) > 365
// HAVING SUM(fbn_storage_fees.total_fee) > vendor_listings.first_price
// → Update vendor_listings.disposable_by_admin = true
// → Notify all admins via DB notification
```

Register in `routes/console.php`:
```php
Schedule::command('listings:flag-overstored')->dailyAt('01:00');
```

### Lang keys (en + ar):
```
'first_price'            => 'Initial Price (Locked)' / 'السعر الأولي (مثبّت)'
'price_history'          => 'Price History' / 'تاريخ الأسعار'
'disposable_by_admin'    => 'Admin Disposable' / 'قابل للتصرف من الأدمن'
'price_source_initial'   => 'Initial' / 'أولي'
'price_source_update'    => 'Update' / 'تحديث'
```

---

## PROMPT 07 — FBN Storage Fees: Volumetric Calculation + Free Periods

### What exists (confirmed)
- `GenerateFbnStorageFeesJob`: computes `total_fee = quantity_on_hand × rate_per_unit`. Uses `storage_rate_per_m3_price` as the rate name but treats it as per-unit. **This is the live logic bug.**
- `vendor_listings`: has `declared_weight_grams`, `declared_length_cm`, `declared_width_cm`, `declared_height_cm`.
- `shipping_rates.volumetric_divisor` exists (default 5000) — can reuse concept.
- `fbn_storage_fees` table: columns `units_stored`, `rate_per_unit`, `total_fee` — no volumetric columns.
- No `storage_fee_free_period_rules` table.

### Client answer (confirmed):
- Use **max(actual_weight, volumetric_weight)** as chargeable weight.
- Admin configures free-day rules (not hardcoded).

### Migrations

**`2026_09_23_600001_create_storage_fee_free_period_rules_table.php`**
```php
Schema::create('storage_fee_free_period_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->unsignedInteger('min_weight_grams')->default(0);
    $table->unsignedInteger('max_weight_grams')->nullable()->comment('null = no upper bound');
    $table->unsignedInteger('free_days');
    $table->uuid('updated_by_admin_id')->nullable();
    $table->timestamps();
    $table->foreign('updated_by_admin_id')->references('id')->on('admins')->onDelete('set null');
    $table->index(['min_weight_grams', 'max_weight_grams']);
});
// Seed two default rules:
DB::table('storage_fee_free_period_rules')->insert([
    ['id' => (string) Str::uuid(), 'min_weight_grams' => 0,    'max_weight_grams' => 999,  'free_days' => 60, 'created_at' => now(), 'updated_at' => now()],
    ['id' => (string) Str::uuid(), 'min_weight_grams' => 1000, 'max_weight_grams' => null, 'free_days' => 30, 'created_at' => now(), 'updated_at' => now()],
]);
```

**`2026_09_23_600002_add_volumetric_columns_to_fbn_storage_fees.php`**
```php
$table->unsignedInteger('declared_weight_grams')->nullable()->after('units_stored');
$table->unsignedInteger('volumetric_weight_grams')->nullable()->after('declared_weight_grams');
$table->unsignedInteger('chargeable_weight_grams')->nullable()->after('volumetric_weight_grams');
$table->unsignedInteger('free_days_applied')->nullable()->after('chargeable_weight_grams');
$table->unsignedInteger('days_in_storage')->nullable()->after('free_days_applied');
$table->boolean('within_free_period')->default(false)->after('days_in_storage');
```

### Model — `app/Models/StorageFeeFreePeriodRule.php` (new)
```php
class StorageFeeFreePeriodRule extends Model
{
    use HasUuids;
    protected $fillable = ['min_weight_grams', 'max_weight_grams', 'free_days', 'updated_by_admin_id'];
    
    public static function freeDaysForWeight(int $weightGrams): int
    {
        $rule = static::where('min_weight_grams', '<=', $weightGrams)
            ->where(function ($q) use ($weightGrams) {
                $q->whereNull('max_weight_grams')
                  ->orWhere('max_weight_grams', '>=', $weightGrams);
            })
            ->orderBy('min_weight_grams', 'desc')
            ->first();
        return $rule ? $rule->free_days : 0;
    }
}
```

### Rewrite `GenerateFbnStorageFeesJob::handle()`
Replace the inner loop with:
```php
foreach ($inventories as $inv) {
    $rateCents = (int) ($inv->rate_per_unit ?? 0);
    if ($rateCents <= 0 || ! $inv->currency) { $skipped++; continue; }

    // --- volumetric calculation ---
    $actualWeightGrams     = (int) ($inv->declared_weight_grams ?? 0);
    $l = (float) ($inv->declared_length_cm ?? 0);
    $w = (float) ($inv->declared_width_cm  ?? 0);
    $h = (float) ($inv->declared_height_cm ?? 0);
    $volumetricWeightGrams = ($l > 0 && $w > 0 && $h > 0)
        ? (int) round(($l * $w * $h) / 5000 * 1000) // /5000 → kg, ×1000 → grams
        : 0;
    $chargeableWeightGrams = max($actualWeightGrams, $volumetricWeightGrams);

    // --- free period check ---
    $freeDays      = StorageFeeFreePeriodRule::freeDaysForWeight($chargeableWeightGrams);
    $inventoryAge  = (int) now()->diffInDays($inv->first_stocked_at ?? now());
    $withinFree    = $inventoryAge <= $freeDays;

    // --- fee calculation ---
    // rate is per m³ (from warehouse.storage_rate_per_m3_price)
    // chargeable volume in m³ = (l_cm × w_cm × h_cm) / 1_000_000
    $volumeM3   = ($l > 0 && $w > 0 && $h > 0) ? ($l * $w * $h / 1_000_000) : 0;
    $totalCents = $withinFree ? 0 : (int) round($volumeM3 * $rateCents * $inv->quantity_on_hand);

    FbnStorageFee::updateOrCreate(
        ['vendor_id' => $inv->vendor_id, 'warehouse_inventory_id' => $inv->inventory_id, 'month' => $month],
        [
            'units_stored'            => $inv->quantity_on_hand,
            'rate_per_unit'           => $rateCents,
            'total_fee'               => $totalCents,
            'currency'                => $inv->currency,
            'declared_weight_grams'   => $actualWeightGrams,
            'volumetric_weight_grams' => $volumetricWeightGrams,
            'chargeable_weight_grams' => $chargeableWeightGrams,
            'free_days_applied'       => $freeDays,
            'days_in_storage'         => $inventoryAge,
            'within_free_period'      => $withinFree,
        ]
    );
}
```

**Note:** `warehouse_inventories` has no `first_stocked_at`. Add via migration:

**`2026_09_23_600003_add_first_stocked_at_to_warehouse_inventories.php`**
```php
$table->timestamp('first_stocked_at')->nullable()->after('bin_location')
      ->comment('Timestamp when inventory was first received — used for free storage period calculation');
```
And in the inbound shipment processing code (wherever `quantity_on_hand` first increases from 0), set `first_stocked_at = now()` if not already set.

### Select query update
Add to the `$inventories` query:
```php
->addSelect([
    'vendor_listings.declared_weight_grams',
    'vendor_listings.declared_length_cm',
    'vendor_listings.declared_width_cm',
    'vendor_listings.declared_height_cm',
    'warehouse_inventories.first_stocked_at',
])
```

### Admin UI — Storage Free Period Rules
Add CRUD in admin panel at `admin/storage-fee-rules` with columns: min weight, max weight, free days. Add link from `resources/views/admin/warehouses/` settings area.

### Lang keys (en + ar):
```
'volumetric_weight'         => 'Volumetric Weight (g)' / 'الوزن الحجمي (غ)'
'chargeable_weight'         => 'Chargeable Weight (g)' / 'الوزن المحاسب (غ)'
'free_days_applied'         => 'Free Days Applied' / 'أيام مجانية مطبّقة'
'within_free_period'        => 'Within Free Period' / 'ضمن الفترة المجانية'
'storage_fee_rules'         => 'Storage Free Period Rules' / 'قواعد أيام التخزين المجانية'
```

---

## PROMPT 08 — Bookable Units: Daily Reservation Calendar (Chalets / Hotels)

### Client answers (confirmed)
- Need professional UX integration between flight bookings, travel packages (fixed date), AND bookable units (daily calendar).
- Reference app: "مسرة" — key UX patterns to replicate: date-range picker with blocked-day visualization, per-day pricing display, morning/evening slot toggle.
- All three booking types (flight, package, bookable unit) should be unified in customer's "My Bookings" page.

### What exists
- `travel_packages`: fixed `departure_date` / `return_date` — do NOT touch.
- `travel_bookings`: linked to travel packages.
- `TravelAgencyPortal/BookingController`, `TravelAgencyPortal/PackageController` exist.
- `travel_agencies` table, `TravelAgency` model.
- No `bookable_units` or related tables.

### Migrations (all new — no touch to travel_packages)

**`2026_09_23_700001_create_bookable_units_table.php`**
```php
Schema::create('bookable_units', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('travel_agency_id');
    $table->string('name');
    $table->string('name_ar')->nullable();
    $table->enum('type', ['chalet', 'hotel_room', 'apartment', 'other']);
    $table->unsignedInteger('capacity')->default(1)->comment('Max guests');
    $table->text('description_en')->nullable();
    $table->text('description_ar')->nullable();
    $table->string('location_en')->nullable();
    $table->string('location_ar')->nullable();
    $table->json('amenities')->nullable()->comment('Array of amenity keys');
    $table->json('images')->nullable()->comment('Array of image paths');
    $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft');
    $table->uuid('approved_by_admin_id')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamps();
    $table->foreign('travel_agency_id')->references('id')->on('travel_agencies')->onDelete('cascade');
    $table->foreign('approved_by_admin_id')->references('id')->on('admins')->onDelete('set null');
    $table->index(['travel_agency_id', 'status']);
});
```

**`2026_09_23_700002_create_bookable_unit_availability_table.php`**
```php
Schema::create('bookable_unit_availability', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('bookable_unit_id');
    $table->date('date');
    $table->boolean('is_available')->default(true);
    $table->unsignedBigInteger('price_day_only')->comment('BIGINT base-currency. No /100.');
    $table->unsignedBigInteger('price_with_overnight')->comment('BIGINT base-currency. No /100.');
    $table->string('currency', 3);
    $table->unsignedInteger('capacity_override')->nullable();
    $table->timestamps();
    $table->unique(['bookable_unit_id', 'date']);
    $table->foreign('bookable_unit_id')->references('id')->on('bookable_units')->onDelete('cascade');
    $table->index(['bookable_unit_id', 'date', 'is_available']);
});
```

**`2026_09_23_700003_create_bookable_unit_time_slots_table.php`**
```php
Schema::create('bookable_unit_time_slots', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('bookable_unit_id');
    $table->enum('slot_type', ['morning', 'evening', 'custom']);
    $table->time('starts_at');
    $table->time('ends_at');
    $table->unsignedBigInteger('price')->comment('BIGINT base-currency. No /100. Overrides daily price when slot booked.');
    $table->string('currency', 3);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->foreign('bookable_unit_id')->references('id')->on('bookable_units')->onDelete('cascade');
});
```

**`2026_09_23_700004_create_bookable_unit_reservations_table.php`**
```php
Schema::create('bookable_unit_reservations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('reservation_number', 30)->unique();
    $table->uuid('bookable_unit_id');
    $table->uuid('customer_id');
    $table->date('date_from');
    $table->date('date_to');
    $table->uuid('time_slot_id')->nullable()->comment('Set for period (morning/evening) bookings');
    $table->boolean('includes_overnight')->default(false);
    $table->unsignedBigInteger('total_price')->comment('BIGINT base-currency. No /100.');
    $table->string('currency', 3);
    $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
    $table->text('customer_notes')->nullable();
    $table->uuid('confirmed_by_admin_id')->nullable();
    $table->timestamp('confirmed_at')->nullable();
    $table->timestamps();
    $table->foreign('bookable_unit_id')->references('id')->on('bookable_units')->onDelete('restrict');
    $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
    $table->foreign('time_slot_id')->references('id')->on('bookable_unit_time_slots')->onDelete('set null');
    $table->foreign('confirmed_by_admin_id')->references('id')->on('admins')->onDelete('set null');
    $table->index(['bookable_unit_id', 'date_from', 'date_to']);
    $table->index(['customer_id', 'status']);
});
```

### Models (all new)

**`app/Models/BookableUnit.php`**, **`BookableUnitAvailability.php`**, **`BookableUnitTimeSlot.php`**, **`BookableUnitReservation.php`** — standard Eloquent with `HasUuids`. Include relationships as implied by the schema.

`BookableUnitReservation` must use `booted()` to auto-generate `reservation_number`:
```php
static::creating(fn ($r) => $r->reservation_number ??= 'BU-' . strtoupper(Str::random(8)));
```

### Controllers

**`app/Http/Controllers/TravelAgencyPortal/BookableUnitController.php`** (new):
- `index()`: list the agency's bookable units.
- `store()` / `update()`: CRUD with images upload.
- `calendar(BookableUnit $unit, Request $request)`: returns availability for a given month. Creates missing `bookable_unit_availability` rows with defaults.
- `setAvailability(BookableUnit $unit, Request $request)`: bulk-update dates (is_available, price_day_only, price_with_overnight).

**`app/Http/Controllers/Api/Customer/BookableUnitAvailabilityController.php`** (new):
- `show(BookableUnit $unit, Request $request)`: return month calendar with each day's status and prices.
- `reserve(BookableUnit $unit, Request $request)`: create reservation with `lockForUpdate()`:
```php
DB::transaction(function () use ($unit, $request) {
    $dates = BookableUnitAvailability::where('bookable_unit_id', $unit->id)
        ->whereBetween('date', [$request->date_from, $request->date_to])
        ->where('is_available', true)
        ->lockForUpdate()
        ->get();
    // Validate all dates are available
    // Mark as unavailable
    // Create reservation
});
```

**`app/Http/Controllers/Admin/BookableUnitController.php`** (new):
- `index()`: list all bookable units across agencies with filters.
- `approve(BookableUnit $unit)`: set status=active.

### مسرة UX integration points
Key UX patterns to implement on the customer-facing Next.js frontend (for a future frontend prompt):
1. **Date-range picker with visual blocking**: grey out unavailable dates, show price on hover per day.
2. **Price breakdown**: day rate × number of nights + overnight supplement if `includes_overnight=true`.
3. **Period toggle**: if unit has time slots, show "صباحي / مسائي" tabs above the calendar.
4. **My Bookings integration**: in customer's "حجوزاتي" page, add a third tab "الوحدات" alongside existing "حزم السفر" and "رحلات الطيران" tabs.

### Admin views
`resources/views/admin/bookable-units/` — index and show views (approval queue + calendar overview).

### Lang keys (en + ar):
```
'bookable_units'         => 'Bookable Units' / 'الوحدات القابلة للحجز'
'chalet'                 => 'Chalet' / 'شاليه'
'hotel_room'             => 'Hotel Room' / 'غرفة فندقية'
'apartment'              => 'Apartment' / 'شقة'
'date_from'              => 'Check-in Date' / 'تاريخ الوصول'
'date_to'                => 'Check-out Date' / 'تاريخ المغادرة'
'includes_overnight'     => 'Includes Overnight' / 'يشمل المبيت'
'price_day_only'         => 'Day Rate' / 'سعر اليوم'
'price_with_overnight'   => 'Overnight Rate' / 'سعر المبيت'
'morning_slot'           => 'Morning Period' / 'الفترة الصباحية'
'evening_slot'           => 'Evening Period' / 'الفترة المسائية'
'reservation_confirmed'  => 'Reservation Confirmed' / 'تم تأكيد الحجز'
'unit_not_available'     => 'Unit not available for selected dates.' / 'الوحدة غير متاحة في التواريخ المختارة.'
```

---

## PROMPT 09 — Unified Travel Booking Experience (Flight + Package + Bookable Unit)

### Context
Three booking types now exist:
1. **Travel packages** (`travel_packages` + `travel_bookings`) — fixed departure/return date.
2. **Bookable units** (`bookable_unit_reservations`) — built in PROMPT 08.
3. **Flights** — check if a `flight_bookings` or similar table exists first:
```bash
python3 -c "import re; f=open('database/schema/mysql-schema.sql').read(); print([t for t in re.findall(r'CREATE TABLE \`(\w+)\`', f) if 'flight' in t or 'airline' in t])"
```

### Task
**Before writing any code**, run the above command and read the result. Then:

**If no flight booking table exists:**
- Create `2026_09_23_800001_create_flight_bookings_table.php`:
```php
Schema::create('flight_bookings', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('booking_number', 30)->unique();
    $table->uuid('customer_id');
    $table->uuid('travel_agency_id')->nullable();
    $table->string('airline_name')->nullable();
    $table->string('flight_number')->nullable();
    $table->string('origin_city');
    $table->string('destination_city');
    $table->timestamp('departure_at');
    $table->timestamp('arrival_at')->nullable();
    $table->integer('passengers_count')->default(1);
    $table->unsignedBigInteger('total_price')->comment('BIGINT base-currency.');
    $table->string('currency', 3);
    $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->foreign('customer_id')->references('id')->on('customers');
    $table->foreign('travel_agency_id')->references('id')->on('travel_agencies')->onDelete('set null');
});
```

**In all cases**, create a unified read DTO/resource:

**`app/DTOs/UnifiedBookingDTO.php`** (new):
```php
readonly class UnifiedBookingDTO
{
    public function __construct(
        public string $id,
        public string $type,           // 'travel_package' | 'bookable_unit' | 'flight'
        public string $bookingNumber,
        public string $title,
        public string $dateFrom,
        public string $dateTo,
        public int    $totalPrice,
        public string $currency,
        public string $status,
        public ?string $agencyName,
        public ?string $thumbnailUrl,
    ) {}
}
```

**`app/Http/Controllers/Api/Customer/MyBookingsController.php`** (new or extend existing):
```php
public function index(Request $request): JsonResponse
{
    $customer = auth('api_customer')->user();
    $bookings = collect();

    // Travel packages
    $bookings = $bookings->merge(
        TravelBooking::with('travelPackage.travelAgency')
            ->where('customer_id', $customer->id)
            ->get()
            ->map(fn ($b) => new UnifiedBookingDTO(
                id: $b->id, type: 'travel_package',
                bookingNumber: $b->booking_number ?? $b->id,
                title: $b->travelPackage->title_ar,
                dateFrom: $b->travelPackage->departure_date,
                dateTo: $b->travelPackage->return_date,
                totalPrice: (int) $b->total_price,
                currency: $b->travelPackage->currency,
                status: $b->status->value ?? $b->status,
                agencyName: $b->travelPackage->travelAgency->name ?? null,
                thumbnailUrl: null,
            ))
    );

    // Bookable units
    $bookings = $bookings->merge(
        BookableUnitReservation::with('bookableUnit.travelAgency')
            ->where('customer_id', $customer->id)
            ->get()
            ->map(fn ($r) => new UnifiedBookingDTO(
                id: $r->id, type: 'bookable_unit',
                bookingNumber: $r->reservation_number,
                title: $r->bookableUnit->name_ar ?? $r->bookableUnit->name,
                dateFrom: $r->date_from,
                dateTo: $r->date_to,
                totalPrice: (int) $r->total_price,
                currency: $r->currency,
                status: $r->status,
                agencyName: $r->bookableUnit->travelAgency->name ?? null,
                thumbnailUrl: null,
            ))
    );

    // Flights (if table exists)
    $bookings = $bookings->merge(
        FlightBooking::where('customer_id', $customer->id)
            ->get()
            ->map(fn ($f) => new UnifiedBookingDTO(
                id: $f->id, type: 'flight',
                bookingNumber: $f->booking_number,
                title: "{$f->origin_city} → {$f->destination_city}",
                dateFrom: $f->departure_at,
                dateTo: $f->arrival_at ?? $f->departure_at,
                totalPrice: (int) $f->total_price,
                currency: $f->currency,
                status: $f->status,
                agencyName: $f->airline_name,
                thumbnailUrl: null,
            ))
    );

    return response()->json([
        'data' => $bookings->sortByDesc('dateFrom')->values(),
    ]);
}
```

Add route in `routes/api_customer.php`:
```php
Route::get('/my-bookings', [\App\Http\Controllers\Api\Customer\MyBookingsController::class, 'index']);
```

---

## PROMPT 10 — QC: Fix Sonnet's Reported Gaps + i18n Completeness Pass

This prompt fixes all four gaps Sonnet reported during testing, plus enforces i18n across all new features.

### Gap 1: Hardcoded Arabic strings
**Search command:**
```bash
grep -rn "ارابي\|'اشتراك\|'دعوة\|'عقد\|'ثابت\|'نسبة\|'محفظة\|'تحويل" app/Http/Controllers/ resources/views/ --include="*.php" | grep -v lang/
```
For every hardcoded Arabic or English string found in controllers and views, replace with `__('admin.key')` or `__('partner.key')`. All strings belong in the language files built in PROMPTS 02–09. Do a full pass over every new file created in PROMPTS 02–09.

### Gap 2: Wallet balance display + insufficient-balance warning
Verify the fix from PROMPT 04 is applied in **both** the vendor and marketer participation request views. Ensure:
- The wallet balance is fetched via `WalletService::getOrCreateWallet('vendor'/$marketerOwnType, $id, $invitation->currency)`.
- The page shows `balance` formatted as a human-readable number (NOT raw bigint — use `number_format($balance, 2)` with the correct scale for display only; the DB stores bigint).
- The "Submit" button is disabled via Alpine.js when balance < min_fee and payment_method=wallet:
```html
<div x-data="{ method: 'wallet', balance: {{ $wallet->balance }}, minFee: {{ $invitation->min_fee_amount }} }">
    <button :disabled="method === 'wallet' && balance < minFee" @click.prevent="method === 'wallet' && balance < minFee ? null : submitForm()">
        {{ __('partner.submit_participation') }}
    </button>
</div>
```

### Gap 3: Draft coupon + zero value issue (Sonnet noted: coupon_id on invitation = draft with value 0)
**Fix:** In `Admin/CouponParticipationInvitationController::store()`, require the admin to link an already-configured (but inactive) coupon when creating the invitation:
- `coupon_id` is required on invitation creation (not nullable for new invitations).
- Validate: `$coupon->is_active === false` (the coupon must be inactive/draft — will be activated when invitation is fulfilled).
- Validate: `$coupon->value > 0` — reject if value is 0.
- Add validation rule to `store()`:
```php
'coupon_id' => ['required', 'uuid', 'exists:coupons,id', function ($attr, $value, $fail) {
    $coupon = \App\Models\Coupon::find($value);
    if (!$coupon) return $fail('Coupon not found.');
    if ($coupon->is_active) return $fail('Coupon must be inactive (will be activated on fulfillment).');
    if ($coupon->value <= 0) return $fail('Coupon must have a value greater than zero.');
}],
```

### Gap 4: Vendor notifications reaching classified vendors
**Fix:** In `CouponParticipationInvitationController::store()`, when dispatching vendor notifications:
```php
// Only notify product vendors
Vendor::where('vendor_type', 'product_vendor')
    ->whereHas('vendorAdmins')
    ->get()
    ->each(fn ($v) => $v->vendorAdmins->each(fn ($va) => $va->notify(new CouponParticipationInvitationNotification($invitation))));
```
Also add `abort_if` guard at the top of `Vendor/CouponParticipationController::index()`:
```php
$vendor = auth('vendor_admin')->user()->vendor;
abort_if($vendor->vendor_type !== 'product_vendor', 403, __('partner.product_vendors_only'));
```
Add lang key:
```
'product_vendors_only' => 'This feature is available to product vendors only.' / 'هذه الميزة متاحة للبائعين من نوع المنتجات فقط.'
```

### Final verification checklist
After applying PROMPTS 02–10, run:
```bash
# 1. No /100 or *100 outside percentage math
grep -rn "/ 100\b\|* 100\b" app/ --include="*.php" | grep -v "commission_rate\|fee_pct\|vat\|percentage\|rate\|/100.*comment"

# 2. No hardcoded Arabic in controllers/views
grep -rn "[ا-ي]" app/Http/Controllers/ --include="*.php" | grep -v "comment\|//"

# 3. All new migrations have UUID PKs
grep -rn "increments\|bigIncrements\|\$table->id()" database/migrations/2026_09_23_* --include="*.php"

# 4. php -l on all new files
find app/ database/migrations/ -name "*.php" -newer database/migrations/2026_09_22_190000_create_marketer_jobs_table.php | xargs php -l

# 5. Route list sanity check
php artisan route:list --path=exclusive-contracts
php artisan route:list --path=coupon-participation
php artisan route:list --path=bookable-units

# 6. Check no new migration edits old migration files
git diff --name-only database/migrations/ | grep -v "2026_09_23_\|2026_09_24_\|2026_09_25_"
```

All checks must pass before committing.