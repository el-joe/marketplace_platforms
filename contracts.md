# Vendor Category Contract Acceptance — Sub-Agent Prompts

> **Stack:** Laravel 11 · MySQL · Blade/Alpine.js (partner panel) · UUIDs everywhere (HasUuids) · Money = BIGINT (no floats) · Never edit existing migrations
> **Run order:** Prompt 1 → 2 → 3 → 4 → 5 → 6 → 7

---

## Architecture Summary (read before running any prompt)

### What already exists
- `classified_contract_templates` — versioned contract template rows (per category, EN+AR content)
- `classified_categories.contract_template_id` — FK to template; already drives `pending_contract` status on listing create
- `classified_listings.contract_template_id` + `.contract_accepted_at` + `.contract_signature_data` — already stores inline per-listing acceptance
- `ClassifiedListingService::acceptContract()` — already moves listing from `pending_contract` → `pending_review`
- Partner Blade panel: `contractShow()` + `contractAccept()` endpoints already exist for per-listing contracts

### What is MISSING (the new feature)
1. **Template variables** — `{{vendor.name}}`, `{{vendor.store_name}}`, `{{category.name}}`, `{{date}}`, etc. are stored literally in content but never resolved. A `resolveVariables(Vendor $vendor, ClassifiedCategory $category): string` method is needed.
2. **`vendor_category_contract_acceptances` table** — a permanent log that vendor X has accepted contract template version Y for category Z. Currently acceptance is only stored inline on each listing; if a vendor adds listing 2 in the same category they must re-read the contract because there is no global acceptance record.
3. **Middleware** `EnforceVendorCategoryContracts` — intercepts `partner.classifieds.create` page load and shows a blocking "unsigned contracts" interstitial if the vendor has active listings in any category whose contract they have never signed.
4. **Contract version update → notify vendors** — when admin creates a new template version for a category, notify all vendors who (a) have listings in that category AND (b) have a prior acceptance on file, that they must re-accept.
5. **Admin UI enhancements** — variables helper panel in the template editor, bulk "which vendors have signed" report.
6. **`categories` table (product categories)** also needs a `contract_template_id` column — the feature request covers both product listings and classified listings.

### Key invariants
- `HasUuids` on every new model
- BIGINT for money; no floats
- Never edit existing migrations — create new ones
- Guard name: `vendor` (session), model: `VendorAdmin`; get vendor via `auth('vendor')->user()->vendor`
- Admin guard: `admin`; permission middleware: `->middleware('admin.permission:classifieds.contracts')`
- Notifications: extend `BaseDatabaseBroadcastNotification`, place in `app/Notifications/Vendor/`, broadcast on `vendor.{vendorAdmin->id}`
- Variable syntax in contract content: `{{vendor.name}}`, `{{vendor.store_name}}`, `{{vendor.email}}`, `{{category.name_en}}`, `{{category.name_ar}}`, `{{date}}`, `{{listing.title_en}}`, `{{listing.title_ar}}`

---

## PROMPT 1 — Database: New Migration + Model

```
You are a senior Laravel fullstack developer working on marketplace_platforms (Laravel 11, MySQL, UUIDs, BIGINT for money).

## Rules
- NEVER edit existing migrations — only create new ones
- All new models must use `HasUuids` trait
- File: backend/database/migrations/YYYY_MM_DD_HHMMSS_create_vendor_category_contract_acceptances_table.php
  Use today's date and a time after existing migrations.

## Task 1 — New migration

Create migration for table `vendor_category_contract_acceptances`:

```php
Schema::create('vendor_category_contract_acceptances', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('vendor_id');                          // FK → vendors.id CASCADE DELETE
    $table->uuid('classified_category_id')->nullable(); // FK → classified_categories.id SET NULL
    $table->uuid('category_id')->nullable();            // FK → categories.id SET NULL (product categories)
    $table->uuid('contract_template_id');               // FK → classified_contract_templates.id CASCADE
    $table->integer('contract_version');                // snapshot of version at time of acceptance
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent')->nullable();
    $table->string('signature_name', 255)->nullable();  // typed name or null if canvas sig
    $table->text('signature_data')->nullable();          // base64 canvas if used
    $table->timestamp('accepted_at')->useCurrent();
    $table->timestamps();

    $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
    $table->foreign('classified_category_id')->references('id')->on('classified_categories')->nullOnDelete();
    $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
    $table->foreign('contract_template_id')->references('id')->on('classified_contract_templates')->cascadeOnDelete();

    // Unique: one acceptance per vendor per template version (allows re-acceptance on new version)
    $table->unique(['vendor_id', 'contract_template_id', 'contract_version'], 'unique_vendor_template_version');
    $table->index(['vendor_id', 'classified_category_id']);
    $table->index(['vendor_id', 'category_id']);
});
```

## Task 2 — New migration: add contract_template_id to `categories` (product categories)

```php
Schema::table('categories', function (Blueprint $table) {
    $table->uuid('contract_template_id')->nullable()->after('is_active');
    $table->foreign('contract_template_id')
          ->references('id')->on('classified_contract_templates')
          ->nullOnDelete();
});
```

Note: reusing `classified_contract_templates` for both category types — no new table needed.

## Task 3 — New model

Create `backend/app/Models/VendorCategoryContractAcceptance.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorCategoryContractAcceptance extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_id',
        'classified_category_id',
        'category_id',
        'contract_template_id',
        'contract_version',
        'ip_address',
        'user_agent',
        'signature_name',
        'signature_data',
        'accepted_at',
    ];

    protected $casts = [
        'contract_version' => 'integer',
        'accepted_at'      => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class);
    }
}
```

## Task 4 — Add relationship to `Vendor` model

In `backend/app/Models/Vendor.php`, add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function categoryContractAcceptances(): HasMany
{
    return $this->hasMany(VendorCategoryContractAcceptance::class);
}
```

## Task 5 — Add contract_template_id to Category model

In `backend/app/Models/Category.php`, add to `$fillable`:
```php
'contract_template_id',
```
Add relationship:
```php
public function contractTemplate(): BelongsTo
{
    return $this->belongsTo(ClassifiedContractTemplate::class);
}
```
```

---

## PROMPT 2 — Contract Service: Variables + Acceptance Logic

```
You are a senior Laravel fullstack developer working on marketplace_platforms.

## Task
Create `backend/app/Services/Vendor/CategoryContractService.php`.

This is the core service for all contract acceptance logic. Write it completely.

```php
<?php

namespace App\Services\Vendor;

use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\Vendor;
use App\Models\VendorCategoryContractAcceptance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CategoryContractService
{
    /**
     * Resolve template variables to their actual values.
     * Variables: {{vendor.name}}, {{vendor.store_name}}, {{vendor.email}},
     *            {{vendor.phone}}, {{vendor.business_name}},
     *            {{category.name_en}}, {{category.name_ar}},
     *            {{date}}
     *
     * For listing-context variables ({{listing.title_en}} etc.),
     * pass $listingData as an associative array.
     */
    public function resolveVariables(
        string $content,
        Vendor $vendor,
        ClassifiedCategory|Category|null $category = null,
        array $listingData = []
    ): string {
        $map = [
            '{{vendor.name}}'          => $vendor->name,
            '{{vendor.store_name}}'    => $vendor->store_name,
            '{{vendor.email}}'         => $vendor->email,
            '{{vendor.phone}}'         => $vendor->phone ?? '',
            '{{vendor.business_name}}' => $vendor->business_name ?? $vendor->store_name,
            '{{date}}'                 => Carbon::now()->format('d/m/Y'),
        ];

        if ($category instanceof ClassifiedCategory) {
            $map['{{category.name_en}}'] = $category->name_en;
            $map['{{category.name_ar}}'] = $category->name_ar;
        } elseif ($category instanceof Category) {
            $map['{{category.name_en}}'] = $category->name_en;
            $map['{{category.name_ar}}'] = $category->name_ar;
        }

        if (! empty($listingData)) {
            $map['{{listing.title_en}}'] = $listingData['title_en'] ?? '';
            $map['{{listing.title_ar}}'] = $listingData['title_ar'] ?? '';
        }

        return str_replace(array_keys($map), array_values($map), $content);
    }

    /**
     * Get all active contract templates that this vendor has NOT yet accepted
     * (or has accepted an older version of).
     * Covers both classified categories and product categories.
     *
     * Returns Collection of:
     * [
     *   'template'  => ClassifiedContractTemplate,
     *   'category'  => ClassifiedCategory|Category|null,
     *   'type'      => 'classified' | 'product',
     * ]
     */
    public function getPendingContractsForVendor(Vendor $vendor): Collection
    {
        $accepted = VendorCategoryContractAcceptance::where('vendor_id', $vendor->id)
            ->select(['contract_template_id', 'contract_version'])
            ->get()
            ->keyBy(fn($a) => $a->contract_template_id . ':' . $a->contract_version);

        $pending = collect();

        // --- Classified categories with active listings ---
        $classifiedCategoryIds = $vendor->classifiedListings()
            ->whereNotIn('status', ['draft', 'sold', 'expired', 'rejected'])
            ->pluck('classified_category_id')
            ->unique();

        ClassifiedCategory::whereIn('id', $classifiedCategoryIds)
            ->whereNotNull('contract_template_id')
            ->with('contractTemplate')
            ->get()
            ->each(function ($category) use (&$pending, $accepted) {
                $template = $category->contractTemplate;
                if (! $template || ! $template->is_active) return;

                $key = $template->id . ':' . $template->version;
                if (! $accepted->has($key)) {
                    $pending->push([
                        'template' => $template,
                        'category' => $category,
                        'type'     => 'classified',
                    ]);
                }
            });

        // --- Product categories with active vendor listings ---
        $productCategoryIds = \App\Models\VendorListing::where('vendor_id', $vendor->id)
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->unique();

        Category::whereIn('id', $productCategoryIds)
            ->whereNotNull('contract_template_id')
            ->with('contractTemplate')
            ->get()
            ->each(function ($category) use (&$pending, $accepted) {
                $template = $category->contractTemplate;
                if (! $template || ! $template->is_active) return;

                $key = $template->id . ':' . $template->version;
                if (! $accepted->has($key)) {
                    $pending->push([
                        'template' => $template,
                        'category' => $category,
                        'type'     => 'product',
                    ]);
                }
            });

        return $pending;
    }

    /**
     * Check if vendor has accepted the current active template for a given classified category.
     */
    public function hasAcceptedForClassifiedCategory(Vendor $vendor, ClassifiedCategory $category): bool
    {
        if (! $category->contract_template_id) return true; // no contract required

        $template = $category->contractTemplate;
        if (! $template || ! $template->is_active) return true;

        return VendorCategoryContractAcceptance::where('vendor_id', $vendor->id)
            ->where('contract_template_id', $template->id)
            ->where('contract_version', $template->version)
            ->exists();
    }

    /**
     * Check if vendor has accepted the current active template for a given product category.
     */
    public function hasAcceptedForProductCategory(Vendor $vendor, Category $category): bool
    {
        if (! $category->contract_template_id) return true;

        $template = $category->contractTemplate;
        if (! $template || ! $template->is_active) return true;

        return VendorCategoryContractAcceptance::where('vendor_id', $vendor->id)
            ->where('contract_template_id', $template->id)
            ->where('contract_version', $template->version)
            ->exists();
    }

    /**
     * Record the vendor's acceptance of a contract template version.
     */
    public function recordAcceptance(
        Vendor $vendor,
        ClassifiedContractTemplate $template,
        ClassifiedCategory|Category|null $category,
        string $signatureName,
        string $ipAddress,
        string $userAgent,
        ?string $signatureData = null
    ): VendorCategoryContractAcceptance {
        return VendorCategoryContractAcceptance::create([
            'vendor_id'                => $vendor->id,
            'classified_category_id'   => $category instanceof ClassifiedCategory ? $category->id : null,
            'category_id'              => $category instanceof Category ? $category->id : null,
            'contract_template_id'     => $template->id,
            'contract_version'         => $template->version,
            'ip_address'               => $ipAddress,
            'user_agent'               => $userAgent,
            'signature_name'           => $signatureName,
            'signature_data'           => $signatureData,
            'accepted_at'              => now(),
        ]);
    }

    /**
     * Get vendors who have previously accepted any version of a template
     * AND have active listings in the related category.
     * Used to notify on version update.
     */
    public function getVendorsToNotifyOnTemplateUpdate(ClassifiedContractTemplate $template): Collection
    {
        return VendorCategoryContractAcceptance::where('contract_template_id', $template->id)
            ->where('contract_version', '<', $template->version)
            ->with('vendor.vendorAdmins')
            ->get()
            ->pluck('vendor')
            ->unique('id')
            ->filter();
    }
}
```
```

---

## PROMPT 3 — Middleware: Enforce Unsigned Contracts Interstitial

```
You are a senior Laravel fullstack developer working on marketplace_platforms.

## Task
Create `backend/app/Http/Middleware/EnforceVendorCategoryContracts.php`.

This middleware runs on the `GET partner.classifieds.create` route and any route tagged `vendor.contract.check`.
It checks if the authenticated vendor has any unsigned (or outdated-version) category contracts.
If yes, it redirects to a "pending contracts" page before letting them proceed.

```php
<?php

namespace App\Http\Middleware;

use App\Services\Vendor\CategoryContractService;
use Closure;
use Illuminate\Http\Request;

class EnforceVendorCategoryContracts
{
    public function __construct(private readonly CategoryContractService $contractService) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! auth('vendor')->check()) {
            return $next($request);
        }

        $vendor = auth('vendor')->user()->vendor;

        if (! $vendor) {
            return $next($request);
        }

        $pending = $this->contractService->getPendingContractsForVendor($vendor);

        if ($pending->isEmpty()) {
            return $next($request);
        }

        // Store pending info in session for the interstitial page
        session(['pending_contracts_count' => $pending->count()]);

        // Don't redirect if already on the contracts page (avoid loop)
        if ($request->routeIs('partner.contracts.pending')) {
            return $next($request);
        }

        return redirect()->route('partner.contracts.pending');
    }
}
```

Register in `bootstrap/app.php` (Laravel 11 style):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        // ... existing aliases ...
        'vendor.contracts.enforce' => \App\Http\Middleware\EnforceVendorCategoryContracts::class,
    ]);
})
```

Apply to the classified listing create route in `routes/partner.php`:

```php
Route::get('/classifieds/create', [ClassifiedListingController::class, 'create'])
    ->name('partner.classifieds.create')
    ->middleware('vendor.contracts.enforce');
```

Also create the routes for the pending contracts page and acceptance endpoint.
Add to `routes/partner.php`:

```php
Route::prefix('contracts')->name('partner.contracts.')->group(function () {
    Route::get('pending', [\App\Http\Controllers\Partner\VendorCategoryContractController::class, 'pending'])
        ->name('pending');
    Route::post('{templateId}/accept', [\App\Http\Controllers\Partner\VendorCategoryContractController::class, 'accept'])
        ->name('accept');
    Route::get('{templateId}/preview', [\App\Http\Controllers\Partner\VendorCategoryContractController::class, 'preview'])
        ->name('preview');
    Route::get('history', [\App\Http\Controllers\Partner\VendorCategoryContractController::class, 'history'])
        ->name('history');
});
```
```

---

## PROMPT 4 — Partner Controller + Blade Views

```
You are a senior Laravel fullstack developer working on marketplace_platforms (Laravel 11, Blade, Alpine.js, TailwindCSS).

## Task
Create `backend/app/Http/Controllers/Partner/VendorCategoryContractController.php` and all related Blade views.

### Controller

```php
<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\Category;
use App\Services\Vendor\CategoryContractService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class VendorCategoryContractController extends Controller
{
    public function __construct(private readonly CategoryContractService $contractService) {}

    private function vendor(): \App\Models\Vendor
    {
        return auth('vendor')->user()->vendor;
    }

    /**
     * Show all pending (unsigned / outdated) contracts for this vendor.
     */
    public function pending(): View
    {
        $vendor  = $this->vendor();
        $pending = $this->contractService->getPendingContractsForVendor($vendor);

        // Resolve variables in each template's content for display
        $pending = $pending->map(function ($item) use ($vendor) {
            $item['resolved_content_en'] = $this->contractService->resolveVariables(
                $item['template']->content_en,
                $vendor,
                $item['category']
            );
            $item['resolved_content_ar'] = $this->contractService->resolveVariables(
                $item['template']->content_ar,
                $vendor,
                $item['category']
            );
            return $item;
        });

        return view('partner.contracts.pending', compact('pending'));
    }

    /**
     * Preview a single contract template (resolved variables).
     */
    public function preview(string $templateId): View
    {
        $template = ClassifiedContractTemplate::findOrFail($templateId);
        $vendor   = $this->vendor();

        $category = ClassifiedCategory::where('contract_template_id', $template->id)->first()
                 ?? Category::where('contract_template_id', $template->id)->first();

        $resolvedEn = $this->contractService->resolveVariables($template->content_en, $vendor, $category);
        $resolvedAr = $this->contractService->resolveVariables($template->content_ar, $vendor, $category);

        return view('partner.contracts.preview', compact('template', 'resolvedEn', 'resolvedAr', 'category'));
    }

    /**
     * Record acceptance of a contract template.
     */
    public function accept(Request $request, string $templateId): RedirectResponse
    {
        $request->validate([
            'signature_name' => 'required|string|max:255',
            'agreed'         => 'accepted',
        ]);

        $template = ClassifiedContractTemplate::findOrFail($templateId);
        $vendor   = $this->vendor();

        $category = ClassifiedCategory::where('contract_template_id', $template->id)->first()
                 ?? Category::where('contract_template_id', $template->id)->first();

        $this->contractService->recordAcceptance(
            vendor:        $vendor,
            template:      $template,
            category:      $category,
            signatureName: $request->signature_name,
            ipAddress:     $request->ip(),
            userAgent:     $request->userAgent() ?? '',
        );

        // Check if more pending contracts remain
        $remaining = $this->contractService->getPendingContractsForVendor($vendor)->count();

        if ($remaining > 0) {
            return redirect()->route('partner.contracts.pending')
                ->with('success', __('partner.contracts.accepted_one_more_pending'));
        }

        // All signed — go back to where they were trying to go
        $intendedUrl = session()->pull('url.intended', route('partner.classifieds.create'));
        return redirect($intendedUrl)
            ->with('success', __('partner.contracts.all_accepted'));
    }

    /**
     * Contract acceptance history for this vendor.
     */
    public function history(): View
    {
        $vendor = $this->vendor();

        $acceptances = \App\Models\VendorCategoryContractAcceptance::where('vendor_id', $vendor->id)
            ->with(['contractTemplate', 'classifiedCategory', 'category'])
            ->orderByDesc('accepted_at')
            ->paginate(20);

        return view('partner.contracts.history', compact('acceptances'));
    }
}
```

### Blade View 1 — `resources/views/partner/contracts/pending.blade.php`

Create this view. It shows ALL pending/unsigned contracts with an accordion — one card per contract. Each card shows:
- Category name (EN + AR)
- Contract name and version number
- Scrollable contract body (resolved content, `max-h-64 overflow-y-auto`)
- A form at the bottom: `signature_name` text input + `agreed` checkbox + Submit button labeled "I have read and accept"
- Language tab switcher (EN / AR) using Alpine.js `x-data="{ lang: 'en' }"`

If `$pending->isEmpty()`, show a success state with link to go back.

Use the existing partner panel Blade layout (`@extends('layouts.partner')`).

### Blade View 2 — `resources/views/partner/contracts/history.blade.php`

Create this view. It shows a paginated table:
| # | Contract Name | Version | Category | Accepted At | IP Address |
Each row links to preview.

### Blade View 3 — `resources/views/partner/contracts/preview.blade.php`

Create this view. Full-page reading of a single contract with resolved variables. Language toggle (Alpine.js). Print button.

### Also: Update the classified listing create flow

In `backend/app/Http/Controllers/Partner/ClassifiedListingController.php`:

In the `create()` method, after determining the category:
If the category has a `contract_template_id` AND the vendor has NOT yet accepted it (call `CategoryContractService::hasAcceptedForClassifiedCategory()`), do NOT redirect — instead pass a flag to the view:

```php
$requiresContract = $category ? 
    ! app(CategoryContractService::class)->hasAcceptedForClassifiedCategory($vendor, $category) 
    : false;
$contractTemplate = $requiresContract ? $category->contractTemplate : null;

return view('partner.classifieds.create', compact(
    // ... existing compact vars ...
    'requiresContract', 'contractTemplate'
));
```

In the Blade create view (`resources/views/partner/classifieds/create.blade.php`):

Before the `<form>` submit button, add an Alpine.js modal that fires when `$requiresContract`:

```html
@if($requiresContract && $contractTemplate)
<div
  x-data="contractModal()"
  x-init="open = true"
>
  <!-- Blocking modal -->
  <div x-show="open" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full flex flex-col max-h-[90vh]">
      <div class="p-6 border-b flex justify-between items-center">
        <div>
          <h2 class="text-lg font-bold text-gray-900">{{ __('partner.contracts.required_title') }}</h2>
          <p class="text-sm text-gray-500 mt-1">{{ $contractTemplate->name }} · v{{ $contractTemplate->version }}</p>
        </div>
        <!-- Language toggle -->
        <div class="flex gap-2" x-data="{ lang: 'en' }">
          <button @click="lang='en'" :class="lang==='en' ? 'bg-blue-600 text-white' : 'bg-gray-100'" class="px-3 py-1 rounded text-sm font-medium">EN</button>
          <button @click="lang='ar'" :class="lang==='ar' ? 'bg-blue-600 text-white' : 'bg-gray-100'" class="px-3 py-1 rounded text-sm font-medium">AR</button>
          <div x-show="lang==='en'" class="overflow-y-auto max-h-64 border rounded p-4 text-sm prose" style="direction:ltr">
            {!! nl2br(e($contractTemplate->content_en)) !!}
          </div>
          <div x-show="lang==='ar'" class="overflow-y-auto max-h-64 border rounded p-4 text-sm prose text-right" style="direction:rtl">
            {!! nl2br(e($contractTemplate->content_ar)) !!}
          </div>
        </div>
      </div>
      <form method="POST" action="{{ route('partner.contracts.accept', $contractTemplate->id) }}" class="p-6 flex flex-col gap-4">
        @csrf
        <input type="hidden" name="redirect_after" value="{{ url()->current() }}">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.contracts.full_name_label') }}</label>
          <input type="text" name="signature_name" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" placeholder="{{ auth('vendor')->user()->name }}" />
        </div>
        <label class="flex items-start gap-3">
          <input type="checkbox" name="agreed" value="1" required class="mt-1" />
          <span class="text-sm text-gray-700">{{ __('partner.contracts.agree_label') }}</span>
        </label>
        <button type="submit" class="w-full bg-blue-600 text-white rounded-lg py-2.5 font-semibold hover:bg-blue-700 transition">
          {{ __('partner.contracts.accept_button') }}
        </button>
      </form>
    </div>
  </div>
</div>
@endif
```

Add lang keys to `lang/en/partner.php` and `lang/ar/partner.php`:
```php
// en
'contracts' => [
    'required_title'        => 'Contract Acceptance Required',
    'full_name_label'       => 'Your Full Name (Signature)',
    'agree_label'           => 'I have read and fully agree to the terms of this contract.',
    'accept_button'         => 'Accept & Continue',
    'accepted_one_more_pending' => 'Contract accepted. Please review the remaining contracts.',
    'all_accepted'          => 'All contracts accepted. You may now submit your listing.',
],

// ar
'contracts' => [
    'required_title'        => 'مطلوب قبول العقد',
    'full_name_label'       => 'اسمك الكامل (التوقيع)',
    'agree_label'           => 'لقد قرأت وأوافق بالكامل على شروط هذا العقد.',
    'accept_button'         => 'قبول والمتابعة',
    'accepted_one_more_pending' => 'تم قبول العقد. يرجى مراجعة العقود المتبقية.',
    'all_accepted'          => 'تم قبول جميع العقود. يمكنك الآن تقديم إعلانك.',
],
```
```

---

## PROMPT 5 — Admin: Variables Helper + Contract Template Update Notification

```
You are a senior Laravel fullstack developer working on marketplace_platforms.

## Task 1 — Update `ClassifiedContractTemplate` model — add variable resolver

In `backend/app/Models/ClassifiedContractTemplate.php`, add:

```php
use App\Models\Vendor;
use App\Models\ClassifiedCategory;
use App\Models\Category;
use Carbon\Carbon;

/**
 * Available variable keys (shown in admin UI helper panel).
 */
public static function availableVariables(): array
{
    return [
        '{{vendor.name}}'          => 'Vendor contact name',
        '{{vendor.store_name}}'    => 'Store / shop name',
        '{{vendor.email}}'         => 'Vendor email address',
        '{{vendor.phone}}'         => 'Vendor phone number',
        '{{vendor.business_name}}' => 'Business / company name',
        '{{category.name_en}}'     => 'Category name (English)',
        '{{category.name_ar}}'     => 'Category name (Arabic)',
        '{{date}}'                 => 'Acceptance date (dd/mm/yyyy)',
        '{{listing.title_en}}'     => 'Listing title (English)',
        '{{listing.title_ar}}'     => 'Listing title (Arabic)',
    ];
}
```

## Task 2 — Update Admin Contract Template Controller to notify vendors on new version

In `backend/app/Http/Controllers/Admin/ClassifiedContractTemplateController.php`:

In the `update()` method, when `$contentChanged === true` and a new template version is created, AFTER `$template = ClassifiedContractTemplate::create($validated)`, add:

```php
// Notify vendors who had accepted the old version and have active listings
$vendorsToNotify = app(\App\Services\Vendor\CategoryContractService::class)
    ->getVendorsToNotifyOnTemplateUpdate($template);

foreach ($vendorsToNotify as $vendor) {
    $vendor->vendorAdmins->each(function ($vendorAdmin) use ($template) {
        $vendorAdmin->notify(new \App\Notifications\Vendor\ContractTemplateUpdated($template));
    });
}
```

Add relationship to `Vendor` model (if not already present):
```php
public function vendorAdmins(): HasMany
{
    return $this->hasMany(\App\Models\VendorAdmin::class);
}
```

## Task 3 — Create notification class

Create `backend/app/Notifications/Vendor/ContractTemplateUpdated.php`:

```php
<?php

namespace App\Notifications\Vendor;

use App\Models\ClassifiedContractTemplate;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class ContractTemplateUpdated extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ClassifiedContractTemplate $template) {}

    public function notificationType(): string
    {
        return 'contract_template_updated';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'        => __('notifications.vendor.contract_updated_title'),
            'message'      => __('notifications.vendor.contract_updated_message', [
                'name'    => $this->template->name,
                'version' => $this->template->version,
            ]),
            'template_id'  => $this->template->id,
            'version'      => $this->template->version,
            'action_url'   => route('partner.contracts.pending'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('vendor.' . $this->notifiable->id)];
    }
}
```

Add to `lang/en/notifications.php`:
```php
'vendor' => [
    'contract_updated_title'   => 'Contract Updated — Action Required',
    'contract_updated_message' => 'The contract ":name" has been updated to version :version. Please review and re-accept.',
],
```

Add to `lang/ar/notifications.php`:
```php
'vendor' => [
    'contract_updated_title'   => 'تحديث العقد — يتطلب إجراءً',
    'contract_updated_message' => 'تم تحديث العقد ":name" إلى الإصدار :version. يرجى مراجعته وإعادة قبوله.',
],
```

## Task 4 — Admin UI: Add variables helper panel to contract template form

In `resources/views/admin/classified-contract-templates/index.blade.php` (or wherever the template create/edit modal lives):

Add a collapsible "Available Variables" helper panel next to the content_en/content_ar textareas:

```html
<div x-data="{ open: false }" class="mb-3">
    <button type="button" @click="open = !open" class="text-sm text-blue-600 underline">
        {{ __('admin.contract_templates.variables_helper') }} ▾
    </button>
    <div x-show="open" class="mt-2 bg-gray-50 border rounded p-3 grid grid-cols-2 gap-2">
        @foreach(\App\Models\ClassifiedContractTemplate::availableVariables() as $key => $desc)
        <div class="flex items-center justify-between text-xs border rounded px-2 py-1 bg-white">
            <code class="text-blue-700 select-all cursor-pointer"
                  @click="navigator.clipboard.writeText('{{ $key }}')">{{ $key }}</code>
            <span class="text-gray-500 ml-2">{{ $desc }}</span>
        </div>
        @endforeach
    </div>
</div>
```

Add lang key: `'variables_helper' => 'Available Variables (click to copy)'`

## Task 5 — Admin: vendor contract acceptances list

Add to admin routes (`routes/admin.php`):
```php
Route::prefix('vendors/{vendor}/contracts')->name('admin.vendors.contracts.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\VendorContractAcceptanceController::class, 'index'])
        ->name('index')
        ->middleware('admin.permission:vendors.view');
});
```

Create `backend/app/Http/Controllers/Admin/VendorContractAcceptanceController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorCategoryContractAcceptance;
use Illuminate\View\View;

class VendorContractAcceptanceController extends Controller
{
    public function index(Vendor $vendor): View
    {
        $acceptances = VendorCategoryContractAcceptance::where('vendor_id', $vendor->id)
            ->with(['contractTemplate', 'classifiedCategory', 'category'])
            ->orderByDesc('accepted_at')
            ->paginate(30);

        return view('admin.vendors.contracts.index', compact('vendor', 'acceptances'));
    }
}
```

Create `resources/views/admin/vendors/contracts/index.blade.php` — a simple table:
Columns: Contract Name | Version | Category | Category Type | Signature Name | Accepted At | IP Address
```

---

## PROMPT 6 — Pre-Submit Modal in Classified Listing Create (Alpine.js)

```
You are a senior Laravel fullstack developer working on marketplace_platforms (Blade, Alpine.js, TailwindCSS).

## Context
When a vendor is creating a new classified listing and selects a category that has a contract template they have NOT yet accepted, we must intercept the form submission with a modal.

The approach: the category selector triggers an AJAX call to check if a contract is required. If yes, the modal appears. The form submit button is disabled until the contract is accepted.

## Task 1 — Add API endpoint to check contract requirement

In `routes/partner.php`, add:
```php
Route::get('/contracts/check-category', [\App\Http\Controllers\Partner\VendorCategoryContractController::class, 'checkCategory'])
    ->name('partner.contracts.check-category');
```

In `VendorCategoryContractController`, add:
```php
public function checkCategory(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
{
    $request->validate(['category_id' => 'required|uuid']);

    $category = \App\Models\ClassifiedCategory::find($request->category_id);
    if (! $category || ! $category->contract_template_id) {
        return response()->json(['requires_contract' => false]);
    }

    $vendor   = $this->vendor();
    $template = $category->contractTemplate;

    $accepted = app(CategoryContractService::class)->hasAcceptedForClassifiedCategory($vendor, $category);

    if ($accepted) {
        return response()->json(['requires_contract' => false]);
    }

    return response()->json([
        'requires_contract' => true,
        'template_id'       => $template->id,
        'template_name'     => $template->name,
        'version'           => $template->version,
        'content_en'        => app(CategoryContractService::class)->resolveVariables(
            $template->content_en, $vendor, $category
        ),
        'content_ar'        => app(CategoryContractService::class)->resolveVariables(
            $template->content_ar, $vendor, $category
        ),
        'accept_url'        => route('partner.contracts.accept', $template->id),
    ]);
}
```

## Task 2 — Update the classified listing create Blade view

In `resources/views/partner/classifieds/create.blade.php`, add the following Alpine.js component wrapping the entire form:

```html
<div
    x-data="listingCreateForm()"
    @submit.prevent="handleSubmit"
>

<!-- Existing form fields here — category select gets @change handler -->
<select name="classified_category_id" @change="onCategoryChange($event.target.value)" ...>
    ...
</select>

<!-- Contract Modal -->
<div x-show="contractModal.open" x-cloak
     class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full flex flex-col max-h-[90vh] overflow-hidden">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold" x-text="contractModal.name"></h2>
                <span class="text-xs text-gray-500">v<span x-text="contractModal.version"></span></span>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="contractModal.lang='en'" :class="contractModal.lang==='en'?'bg-blue-600 text-white':'bg-gray-100 text-gray-700'" class="px-2 py-1 rounded text-xs font-medium">EN</button>
                <button type="button" @click="contractModal.lang='ar'" :class="contractModal.lang==='ar'?'bg-blue-600 text-white':'bg-gray-100 text-gray-700'" class="px-2 py-1 rounded text-xs font-medium">AR</button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-4 text-sm leading-relaxed prose max-w-none"
             :class="contractModal.lang==='ar' ? 'text-right' : 'text-left'"
             :dir="contractModal.lang==='ar' ? 'rtl' : 'ltr'"
             x-html="contractModal.lang==='en' ? contractModal.content_en : contractModal.content_ar">
        </div>
        <div class="px-6 py-4 border-t flex flex-col gap-3">
            <input type="text"
                   x-model="contractModal.signatureName"
                   placeholder="{{ __('partner.contracts.full_name_label') }}"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
            <label class="flex items-start gap-2">
                <input type="checkbox" x-model="contractModal.agreed" class="mt-1" />
                <span class="text-sm text-gray-700">{{ __('partner.contracts.agree_label') }}</span>
            </label>
            <button
                type="button"
                @click="submitContractAcceptance()"
                :disabled="!contractModal.agreed || !contractModal.signatureName || contractModal.loading"
                class="w-full bg-blue-600 disabled:opacity-50 text-white rounded-lg py-2.5 font-semibold hover:bg-blue-700 transition">
                <span x-show="!contractModal.loading">{{ __('partner.contracts.accept_button') }}</span>
                <span x-show="contractModal.loading">{{ __('common.saving') }}…</span>
            </button>
        </div>
    </div>
</div>

<!-- Submit button — disabled until contract is cleared -->
<button type="submit"
        :disabled="contractPending"
        :title="contractPending ? '{{ __('partner.contracts.must_accept_first') }}' : ''"
        class="btn-primary disabled:opacity-50">
    {{ __('partner.classifieds.submit_listing') }}
</button>

</div>

<script>
function listingCreateForm() {
    return {
        contractPending: false,
        contractModal: {
            open: false, lang: 'en', name: '', version: '',
            content_en: '', content_ar: '',
            templateId: null, acceptUrl: '',
            signatureName: '', agreed: false, loading: false,
        },

        async onCategoryChange(categoryId) {
            if (! categoryId) return;
            const res = await fetch(`{{ route('partner.contracts.check-category') }}?category_id=${categoryId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.requires_contract) {
                this.contractPending = true;
                this.contractModal = {
                    ...this.contractModal,
                    open: true,
                    name: data.template_name,
                    version: data.version,
                    content_en: data.content_en,
                    content_ar: data.content_ar,
                    templateId: data.template_id,
                    acceptUrl: data.accept_url,
                };
            } else {
                this.contractPending = false;
            }
        },

        async submitContractAcceptance() {
            this.contractModal.loading = true;
            const res = await fetch(this.contractModal.acceptUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    signature_name: this.contractModal.signatureName,
                    agreed: '1',
                }),
            });
            const data = await res.json();
            this.contractModal.loading = false;
            if (res.ok) {
                this.contractPending = false;
                this.contractModal.open = false;
            } else {
                alert(data.message ?? 'Error accepting contract.');
            }
        },

        handleSubmit(e) {
            if (this.contractPending) return;
            e.target.closest('form').submit();
        },
    };
}
</script>
```

Update `VendorCategoryContractController::accept()` to return JSON when the request is AJAX:

```php
public function accept(Request $request, string $templateId): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
{
    // ... same validation and logic ...

    if ($request->expectsJson() || $request->ajax()) {
        return response()->json(['success' => true, 'message' => __('partner.contracts.all_accepted')]);
    }

    // ... existing redirect logic ...
}
```

Add lang key: `'must_accept_first' => 'You must accept the category contract before submitting a listing.'`
```

---

## PROMPT 7 — Contract Acceptance in Listing API (JWT / Mobile)

```
You are a senior Laravel fullstack developer working on marketplace_platforms.

## Context
The vendor mobile app uses the JWT API (`guard: vendor_api`). The API endpoint for accepting a classified listing contract is:
`POST /api/vendor/v1/classifieds/{id}/contract/accept`
in `Vendor\ClassifiedListingController::acceptContract()`

Currently it stores acceptance inline on the listing only. We need it to ALSO write a row to `vendor_category_contract_acceptances`.

## Task 1 — Update `Vendor\ClassifiedListingController::acceptContract()`

In `backend/app/Http/Controllers/Vendor/ClassifiedListingController.php`, find `acceptContract()`.

After `$this->listingService->acceptContract($listing, $request->signature_data)`, add:

```php
// Also record global category-level acceptance
$vendor   = auth('vendor_api')->user()->vendor;
$category = $listing->classifiedCategory;
$template = $category?->contractTemplate;

if ($vendor && $template) {
    app(\App\Services\Vendor\CategoryContractService::class)->recordAcceptance(
        vendor:        $vendor,
        template:      $template,
        category:      $category,
        signatureName: '', // canvas sig — no typed name
        ipAddress:     $request->ip(),
        userAgent:     $request->userAgent() ?? '',
        signatureData: $request->signature_data,
    );
}
```

## Task 2 — Add API endpoint to check pending contracts

In `routes/api_vendor.php`, add:

```php
Route::get('contracts/pending', [\App\Http\Controllers\Vendor\ContractController::class, 'pending'])
    ->name('vendor.contracts.pending');

Route::post('contracts/{templateId}/accept', [\App\Http\Controllers\Vendor\ContractController::class, 'accept'])
    ->name('vendor.contracts.accept');
```

Create `backend/app/Http/Controllers/Vendor/ContractController.php`:

```php
<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedContractTemplate;
use App\Services\Vendor\CategoryContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(private readonly CategoryContractService $service) {}

    public function pending(): JsonResponse
    {
        $vendor  = auth('vendor_api')->user()->vendor;
        $pending = $this->service->getPendingContractsForVendor($vendor);

        $data = $pending->map(function ($item) use ($vendor) {
            return [
                'template_id'   => $item['template']->id,
                'name'          => $item['template']->name,
                'version'       => $item['template']->version,
                'category_type' => $item['type'],
                'category_name' => $item['category']?->name_en ?? null,
                'content_en'    => $this->service->resolveVariables(
                    $item['template']->content_en, $vendor, $item['category']
                ),
                'content_ar'    => $this->service->resolveVariables(
                    $item['template']->content_ar, $vendor, $item['category']
                ),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function accept(Request $request, string $templateId): JsonResponse
    {
        $request->validate([
            'signature_name' => 'nullable|string|max:255',
            'signature_data' => 'nullable|string',
            'agreed'         => 'accepted',
        ]);

        $vendor   = auth('vendor_api')->user()->vendor;
        $template = ClassifiedContractTemplate::findOrFail($templateId);

        $category = \App\Models\ClassifiedCategory::where('contract_template_id', $template->id)->first()
                 ?? \App\Models\Category::where('contract_template_id', $template->id)->first();

        $this->service->recordAcceptance(
            vendor:        $vendor,
            template:      $template,
            category:      $category,
            signatureName: $request->signature_name ?? '',
            ipAddress:     $request->ip(),
            userAgent:     $request->userAgent() ?? '',
            signatureData: $request->signature_data,
        );

        $remaining = $this->service->getPendingContractsForVendor($vendor)->count();

        return response()->json([
            'success'           => true,
            'remaining_pending' => $remaining,
            'message'           => $remaining > 0
                ? 'Contract accepted. More contracts pending.'
                : 'All contracts accepted.',
        ]);
    }
}
```
```

---

## Execution Order Checklist

- [ ] **Prompt 1** — Migrations + models (run `php artisan migrate` after)
- [ ] **Prompt 2** — `CategoryContractService` (no DB changes)
- [ ] **Prompt 3** — `EnforceVendorCategoryContracts` middleware + route registration
- [ ] **Prompt 4** — Blade controller + views + create form modal
- [ ] **Prompt 5** — Admin helpers + version-update notifications
- [ ] **Prompt 6** — Alpine.js pre-submit modal in create form
- [ ] **Prompt 7** — JWT API endpoints for mobile

---

## Full Lifecycle Summary

```
ADMIN creates/updates contract template
  → assigns template to ClassifiedCategory (or Category)
  → on UPDATE (new version): notifies all vendors with prior acceptances via ContractTemplateUpdated notification

VENDOR logs into partner panel
  → EnforceVendorCategoryContracts middleware fires on classifieds.create
  → if they have active listings in categories with unsigned/outdated contracts → redirect to /contracts/pending
  → vendor reads + types name + checks agree → POST /contracts/{templateId}/accept
  → VendorCategoryContractAcceptance row written (with IP, user agent, signature name, version snapshot)
  → if more pending → loop; if all done → redirect to intended URL

VENDOR creates new listing
  → category selector @change fires AJAX to /contracts/check-category
  → if contract required: modal appears in-page → vendor accepts → contractPending cleared
  → submit button enabled → form POST proceeds normally
  → ClassifiedListingService::create() sets status = pending_contract (if still needed) OR pending_review (already accepted)

MOBILE (JWT)
  → GET /api/vendor/v1/contracts/pending → list of pending contracts with resolved content
  → POST /api/vendor/v1/contracts/{templateId}/accept → record acceptance
  → then POST listing creation proceeds

ADMIN AUDIT
  → /admin/vendors/{vendor}/contracts → full acceptance log with version, IP, date
```