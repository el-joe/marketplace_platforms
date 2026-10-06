# Variable Commission — CRUD Price Tiers
## Refactored Plan & Sub-Agent Prompts
**Prepared:** 2026-10-06 | **Stack:** Laravel 11 · MySQL · Blade/TailwindCSS/Alpine.js

---

## 1. Decision: Why a Separate CRUD Table

The previous plan stored one threshold + one high_rate + one floor directly on `categories`.
That only supports exactly two tiers and requires re-deploying to add a new band.

The client requirement "CRUD and editable" means:
- Admin can define **N tiers per category** (add / edit / delete rows)
- Each tier has a **price range**, a **commission rate**, and an **optional per-unit minimum**
- The UI is a table of rows inside the category form, not three flat inputs

**New table: `category_commission_tiers`**
```
id                 uuid PK
category_id        uuid FK→categories CASCADE
price_from         bigint NOT NULL DEFAULT 0    -- inclusive lower bound, base-currency BIGINT
price_to           bigint DEFAULT NULL          -- NULL = "and above"; exclusive upper bound = next tier's from
commission_rate    decimal(5,2) NOT NULL        -- % applied to line total
min_commission     bigint NOT NULL DEFAULT 0    -- per-unit floor; 0 = no floor
sort_order         int NOT NULL DEFAULT 0       -- admin ordering; engine uses price_from ASC
created_at / updated_at
```

**Resolution logic (replaces threshold column logic):**
1. Load tiers for the category ordered by `price_from ASC`.
2. Find the first tier where `unit_price >= price_from AND (price_to IS NULL OR unit_price < price_to)`.
3. If no tier matches → fall back to `categories.commission_rate` (flat rate, no floor).
4. Apply `CommissionCalculator` with the matched tier's rate + min_commission.

**What happens to the three columns already migrated (000001–000003)?**
Migrations 2026_10_06_000001/000002/000003 added flat columns — they have NOT been run on
production yet (schema dump confirmed). We drop them with a new reverting migration and replace
with the `category_commission_tiers` table.

---

## 2. Architecture

### CommissionCalculator — keep as-is, update signature
Current signature uses positional args and raw unit price. Keep the class; the engine will pass
the resolved tier's rate + min instead of category columns directly.

### CategoryCommissionTier model
- HasUuids, belongs to Category
- Scoped: `scopeForCategory(Builder $q, string $categoryId)`
- Method: `static resolveForUnitPrice(Collection $tiers, int $unitPrice): ?self`

### CategoryController changes
- Add `storeTier()`, `updateTier()`, `destroyTier()` methods (or a separate `CategoryCommissionTierController`)
- Existing `store()` / `update()` no longer save the three flat columns

### CheckoutPricingEngine
Replace the three-column lookup with a `CategoryCommissionTier::resolveForUnitPrice()` call.
The existing `CommissionCalculator::calculate()` call stays; only the inputs change.

### Admin UI
Inside the category edit form: a live Alpine.js table showing existing tiers.
Rows are added/edited/deleted via standard form POSTs (no SPA required).

---

## 3. Test Cases (unchanged — same math)

| Unit Price | Tier match | Rate | Min/unit | qty | Expected |
|---|---|---|---|---|---|
| 30 | 0–59 | 10% | 5 | 1 | `max(floor(30*10/100), 5)*1 = 5` |
| 100 | 60–∞ | 6% | 5 | 1 | `max(floor(100*6/100), 5)*1 = 6` |
| 200 | 60–∞ | 6% | 5 | 1 | `max(floor(200*6/100), 5)*1 = 12` |
| 30 | no tier | 8% (flat) | 0 | 1 | `floor(30*8/100) = 2` |

---

## 4. Execution Order

| # | Scope | Risk |
|---|---|---|
| Prompt 1 | Revert flat columns; create `category_commission_tiers` table; new model | Low |
| Prompt 2 | `CategoryCommissionTierController` CRUD + routes | Low |
| Prompt 3 | Admin Blade UI — tier table inside category form | Low |
| Prompt 4 | Wire `CheckoutPricingEngine` to use tier table | High |
| Prompt 5 | Wire `MarketerCommissionRule::resolveAmount()` minimum floor (unchanged) | Low |

---

---

# PROMPT 1 — Revert Flat Columns + Create Tiers Table + Model

```
You are a Laravel 11 backend developer on the marketplace_platforms monorepo.
Repo: /home/claude/repo   Backend: /home/claude/repo/backend

## Invariants
- All monetary values: BIGINT base-currency integers. Never float, never /100 except % math.
- UUID primary keys with HasUuids trait. Never $table->id().
- Never edit existing migrations — new migrations only.
- Never write to VIRTUAL GENERATED columns.

## Context
Three migrations were created earlier today (2026_10_06_000001/000002/000003) that added flat
variable-commission columns to categories/marketer_commission_rules/marketer_category_commissions.
They have NOT been run on production (confirmed via schema dump).
We now replace the flat-column approach with a proper CRUD tier table.

---

## Task 1 — Revert the three flat-column migrations

Create migration: 2026_10_06_100001_revert_flat_variable_commission_columns.php

This migration's up() must:
1. Drop from `categories`: commission_threshold_price, commission_high_rate, commission_min_amount
2. Drop from `marketer_commission_rules`: commission_threshold_price, commission_min_amount
3. Drop from `marketer_category_commissions`: commission_threshold_price, commission_min_amount

Use Schema::hasColumn() checks before each drop so the migration is safe to run even if the
earlier migrations were never applied:

Schema::table('categories', function (Blueprint $table) {
    foreach (['commission_threshold_price','commission_high_rate','commission_min_amount'] as $col) {
        if (Schema::hasColumn('categories', $col)) {
            $table->dropColumn($col);
        }
    }
});
// repeat for marketer_commission_rules and marketer_category_commissions

The down() recreates the same columns (copy from the 000001/000002/000003 migration up() methods).

---

## Task 2 — Create category_commission_tiers table

Create migration: 2026_10_06_100002_create_category_commission_tiers_table.php

Schema::create('category_commission_tiers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
    $table->bigInteger('price_from')->default(0)
          ->comment('Inclusive lower bound, base-currency BIGINT. 0 = starts from zero.');
    $table->bigInteger('price_to')->nullable()->default(null)
          ->comment('NULL = no upper bound (this tier covers price_from and above).');
    $table->decimal('commission_rate', 5, 2)->default('0.00')
          ->comment('Commission % applied when unit price falls in this tier.');
    $table->bigInteger('min_commission')->default(0)
          ->comment('Per-unit minimum commission floor, base-currency BIGINT. 0 = no floor.');
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index(['category_id', 'price_from'], 'idx_tier_category_price');
});

down(): Schema::dropIfExists('category_commission_tiers');

---

## Task 3 — Remove flat columns from Category model

File: backend/app/Models/Category.php
Read the file first.

Remove from $fillable:
  'commission_threshold_price',
  'commission_high_rate',
  'commission_min_amount',

Remove from $casts:
  'commission_threshold_price' => 'integer',
  'commission_high_rate'       => 'decimal:2',
  'commission_min_amount'      => 'integer',

Add a relationship method:
  public function commissionTiers(): HasMany
  {
      return $this->hasMany(CategoryCommissionTier::class)
                  ->orderBy('price_from');
  }

Add import: use App\Models\CategoryCommissionTier;

---

## Task 4 — Create CategoryCommissionTier model

File: backend/app/Models/CategoryCommissionTier.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class CategoryCommissionTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'category_id',
        'price_from',
        'price_to',
        'commission_rate',
        'min_commission',
        'sort_order',
    ];

    protected $casts = [
        'price_from'      => 'integer',
        'price_to'        => 'integer',
        'commission_rate' => 'decimal:2',
        'min_commission'  => 'integer',
        'sort_order'      => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Find the tier matching the given unit price.
     * Tiers must be pre-loaded/sorted by price_from ASC.
     * Returns null when no tier covers this unit price.
     */
    public static function resolveForUnitPrice(Collection $tiers, int $unitPrice): ?self
    {
        foreach ($tiers as $tier) {
            if ($unitPrice < $tier->price_from) {
                continue;
            }
            if ($tier->price_to !== null && $unitPrice >= $tier->price_to) {
                continue;
            }
            return $tier;
        }
        return null;
    }
}
```

## Output all files. Do NOT run artisan.
```

---

---

# PROMPT 2 — CategoryCommissionTierController + Routes

```
You are a Laravel 11 backend developer on the marketplace_platforms monorepo.
Backend: /home/claude/repo/backend

## Invariants
- Admin guard: `admin`. Permission middleware: ->middleware('admin.permission:categories.edit').
- UUID primary keys. Never use integer IDs.
- Read all files before editing.
- All monetary inputs are raw BIGINT integers from admin (no /100).

## Context
`category_commission_tiers` table and `CategoryCommissionTier` model exist (Prompt 1).
Category model has a `commissionTiers()` hasMany relationship.
Admin routes file is at: backend/routes/admin.php

## Task A — Create CategoryCommissionTierController

File: backend/app/Http/Controllers/Admin/CategoryCommissionTierController.php

The controller manages tier rows nested under a Category.
Methods: store(), update(), destroy()
(index/show are handled inline in the category form — no separate pages needed)

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryCommissionTier;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class CategoryCommissionTierController extends Controller
{
    public function store(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'price_from'      => 'required|integer|min:0',
            'price_to'        => 'nullable|integer|min:1|gt:price_from',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'min_commission'  => 'nullable|integer|min:0',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        $category->commissionTiers()->create([
            'price_from'      => (int) $data['price_from'],
            'price_to'        => isset($data['price_to']) ? (int) $data['price_to'] : null,
            'commission_rate' => $data['commission_rate'],
            'min_commission'  => (int) ($data['min_commission'] ?? 0),
            'sort_order'      => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('success', __('Commission tier added.'));
    }

    public function update(Request $request, Category $category, CategoryCommissionTier $tier): RedirectResponse
    {
        abort_if($tier->category_id !== $category->id, 404);

        $data = $request->validate([
            'price_from'      => 'required|integer|min:0',
            'price_to'        => 'nullable|integer|min:1|gt:price_from',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'min_commission'  => 'nullable|integer|min:0',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        $tier->update([
            'price_from'      => (int) $data['price_from'],
            'price_to'        => isset($data['price_to']) ? (int) $data['price_to'] : null,
            'commission_rate' => $data['commission_rate'],
            'min_commission'  => (int) ($data['min_commission'] ?? 0),
            'sort_order'      => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('success', __('Commission tier updated.'));
    }

    public function destroy(Category $category, CategoryCommissionTier $tier): RedirectResponse
    {
        abort_if($tier->category_id !== $category->id, 404);

        $tier->delete();

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('success', __('Commission tier deleted.'));
    }
}
```

## Task B — Register routes

File: backend/routes/admin.php
Read the file first. Locate the categories route group.

After the existing category routes, add a nested resource for commission tiers:

Route::prefix('categories/{category}/commission-tiers')
    ->middleware('admin.permission:categories.edit')
    ->name('admin.categories.commission-tiers.')
    ->group(function () {
        Route::post('/', [CategoryCommissionTierController::class, 'store'])->name('store');
        Route::put('{tier}', [CategoryCommissionTierController::class, 'update'])->name('update');
        Route::delete('{tier}', [CategoryCommissionTierController::class, 'destroy'])->name('destroy');
    });

Add import at top of the route file (where other Admin controllers are imported):
  use App\Http\Controllers\Admin\CategoryCommissionTierController;

## Output changed files only. Do NOT run artisan.
```

---

---

# PROMPT 3 — Admin Blade UI — Tiers Table inside Category Form

```
You are a Laravel 11 frontend developer (Blade + TailwindCSS + Alpine.js).
Backend: /home/claude/repo/backend

## Invariants
- Admin UI uses TailwindCSS and Alpine.js. No Vue, no React in Blade.
- All monetary inputs are BIGINT base-currency integers (the admin enters the raw number).
- Alpine.js for interactivity. Standard <form> POST/PUT/DELETE (no AJAX required).
- x-cloak requires [x-cloak]{ display:none } in global CSS — verify it exists.

## Context
Routes added in Prompt 2:
  admin.categories.commission-tiers.store   → POST  /admin/categories/{category}/commission-tiers
  admin.categories.commission-tiers.update  → PUT   /admin/categories/{category}/commission-tiers/{tier}
  admin.categories.commission-tiers.destroy → DELETE /admin/categories/{category}/commission-tiers/{tier}

CategoryController's edit() must pass tiers to the view. Check:
  grep -n "edit\|commissionTier\|commission_tier" backend/app/Http/Controllers/Admin/CategoryController.php

If edit() does not load tiers:
  $category->load('commissionTiers');
  return view('admin.categories.edit', compact('category', ...));
Add the load() call.

## Task — Blade partial

Find the category form view:
  grep -rn "commission_rate" backend/resources/views/admin/ --include="*.blade.php" -l

Read the category form file.

After the existing commission_rate / FBP / FBN fields, add a section for commission tiers.
This replaces the three flat inputs added in the previous plan (remove those if present).

Insert this section:

{{-- ================================================================ --}}
{{-- Commission Tiers (CRUD) --}}
{{-- ================================================================ --}}
<div class="mt-8 border-t border-gray-200 pt-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h4 class="text-sm font-semibold text-gray-800">{{ __('Commission Tiers') }}</h4>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ __('Unit price is matched against tiers in order. If no tier matches, the category\'s standard commission rate applies.') }}
            </p>
        </div>
        <button type="button"
                x-data
                @click="$dispatch('open-add-tier')"
                class="inline-flex items-center gap-1.5 rounded bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary/90">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('Add Tier') }}
        </button>
    </div>

    {{-- Existing tiers table --}}
    @if($category->commissionTiers->isNotEmpty())
    <div class="overflow-x-auto rounded border border-gray-200 mb-4">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Price From') }}</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Price To') }}</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Rate (%)') }}</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Min/Unit') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach($category->commissionTiers as $tier)
                <tr x-data="{ editing: false }">
                    {{-- View mode --}}
                    <td x-show="!editing" class="px-4 py-2 tabular-nums">{{ number_format($tier->price_from) }}</td>
                    <td x-show="!editing" class="px-4 py-2 tabular-nums text-gray-500">
                        {{ $tier->price_to !== null ? number_format($tier->price_to) : '∞' }}
                    </td>
                    <td x-show="!editing" class="px-4 py-2 tabular-nums">{{ $tier->commission_rate }}%</td>
                    <td x-show="!editing" class="px-4 py-2 tabular-nums text-gray-500">
                        {{ $tier->min_commission > 0 ? number_format($tier->min_commission) : '—' }}
                    </td>
                    <td x-show="!editing" class="px-4 py-2 text-right whitespace-nowrap">
                        <button type="button" @click="editing = true"
                                class="text-xs text-blue-600 hover:underline mr-3">{{ __('Edit') }}</button>
                        <form method="POST"
                              action="{{ route('admin.categories.commission-tiers.destroy', [$category, $tier]) }}"
                              class="inline"
                              onsubmit="return confirm('{{ __('Delete this tier?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">{{ __('Delete') }}</button>
                        </form>
                    </td>

                    {{-- Edit mode (inline) --}}
                    <td x-show="editing" x-cloak colspan="5" class="px-4 py-3 bg-blue-50">
                        <form method="POST"
                              action="{{ route('admin.categories.commission-tiers.update', [$category, $tier]) }}"
                              class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                            @csrf @method('PUT')
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Price From') }}</label>
                                <input type="number" name="price_from" min="0" step="1"
                                       value="{{ $tier->price_from }}"
                                       class="w-full rounded border-gray-300 text-sm focus:ring-primary focus:border-primary" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Price To') }} <span class="text-gray-400">({{ __('blank = ∞') }})</span></label>
                                <input type="number" name="price_to" min="1" step="1"
                                       value="{{ $tier->price_to }}"
                                       class="w-full rounded border-gray-300 text-sm focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Rate (%)') }}</label>
                                <input type="number" name="commission_rate" min="0" max="100" step="0.01"
                                       value="{{ $tier->commission_rate }}"
                                       class="w-full rounded border-gray-300 text-sm focus:ring-primary focus:border-primary" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Min/Unit') }}</label>
                                <input type="number" name="min_commission" min="0" step="1"
                                       value="{{ $tier->min_commission }}"
                                       class="w-full rounded border-gray-300 text-sm focus:ring-primary focus:border-primary">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        class="flex-1 rounded bg-primary px-3 py-2 text-xs font-medium text-white hover:bg-primary/90">
                                    {{ __('Save') }}
                                </button>
                                <button type="button" @click="editing = false"
                                        class="flex-1 rounded border border-gray-300 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50">
                                    {{ __('Cancel') }}
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <p class="text-sm text-gray-400 italic mb-4">{{ __('No tiers defined — standard commission rate applies to all prices.') }}</p>
    @endif

    {{-- Add new tier form — shown via Alpine event --}}
    <div x-data="{ open: false }" @open-add-tier.window="open = true">
        <div x-show="open" x-cloak class="rounded border border-blue-200 bg-blue-50 p-4">
            <h5 class="text-sm font-semibold text-blue-800 mb-3">{{ __('New Tier') }}</h5>
            <form method="POST"
                  action="{{ route('admin.categories.commission-tiers.store', $category) }}"
                  class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Price From') }}</label>
                    <input type="number" name="price_from" min="0" step="1" value="0"
                           class="w-full rounded border-gray-300 text-sm focus:ring-primary focus:border-primary" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Price To') }} <span class="text-gray-400">({{ __('blank = ∞') }})</span></label>
                    <input type="number" name="price_to" min="1" step="1"
                           class="w-full rounded border-gray-300 text-sm focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Rate (%)') }}</label>
                    <input type="number" name="commission_rate" min="0" max="100" step="0.01" value="0"
                           class="w-full rounded border-gray-300 text-sm focus:ring-primary focus:border-primary" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Min/Unit') }}</label>
                    <input type="number" name="min_commission" min="0" step="1" value="0"
                           class="w-full rounded border-gray-300 text-sm focus:ring-primary focus:border-primary">
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                            class="flex-1 rounded bg-primary px-3 py-2 text-xs font-medium text-white hover:bg-primary/90">
                        {{ __('Add') }}
                    </button>
                    <button type="button" @click="open = false"
                            class="flex-1 rounded border border-gray-300 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50">
                        {{ __('Cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

## Output changed files only. Do NOT run artisan.
```

---

---

# PROMPT 4 — Wire CheckoutPricingEngine to Use Tier Table

```
You are a Laravel 11 senior backend developer on the marketplace_platforms monorepo.
Backend: /home/claude/repo/backend

## Invariants
- All monetary values: BIGINT base-currency integers.
- Use FLOOR for percentage math. bcmath for precision.
- Read the ENTIRE CheckoutPricingEngine file before touching anything.

## Context
- `category_commission_tiers` table and `CategoryCommissionTier` model exist (Prompt 1).
- `CommissionCalculator::calculate()` already exists at App\Services\Shared\CommissionCalculator.
  Its import is already at line 21 of CheckoutPricingEngine.
- The three flat columns (commission_threshold_price, commission_high_rate, commission_min_amount)
  have been REMOVED from the categories table (Prompt 1 migration 100001).
  Do NOT reference those column names in the engine.

## Task — Edit CheckoutPricingEngine

File: backend/app/Services/Checkout/CheckoutPricingEngine.php

Read the entire file first. The engine already calls CommissionCalculator::calculate() at
approximately line 732. That call currently passes $resolved->commission_threshold_price etc.
Those columns no longer exist. We replace that lookup with a tier table query.

### Add import (if not already present):
  use App\Models\CategoryCommissionTier;

### In resolveCommission() — locate the FBP/FBN category chain fallback (Step 3):

After resolving $resolved (the Category model from the chain walk), replace the
CommissionCalculator::calculate() call so it:
1. Loads the tiers for the resolved category (eager if $resolved->relationLoaded('commissionTiers'),
   otherwise query: CategoryCommissionTier::where('category_id', $resolved->id)->orderBy('price_from')->get())
2. Calls CategoryCommissionTier::resolveForUnitPrice($tiers, $unitPrice) to find matching tier
3. If a tier is found: use $tier->commission_rate and $tier->min_commission
4. If no tier: use $resolved->commission_rate and 0 for min

The updated call:

  // CC-TIER: load category tiers; tier rate + floor override flat category rate
  $tiers = $resolved->relationLoaded('commissionTiers')
      ? $resolved->commissionTiers
      : CategoryCommissionTier::where('category_id', $resolved->id)->orderBy('price_from')->get();

  $matchedTier = CategoryCommissionTier::resolveForUnitPrice($tiers, $unitPrice);

  $tierRate = $matchedTier !== null
      ? (float)  $matchedTier->commission_rate
      : (float)  $resolved->commission_rate;
  $tierMin  = $matchedTier !== null
      ? (int)    $matchedTier->min_commission
      : 0;

  $amount = CommissionCalculator::calculate(
      base:          $base,
      unitPrice:     $unitPrice,
      standardRate:  $tierRate,
      thresholdPrice: 0,
      highRate:       0.0,
      minCommission:  $tierMin,
      quantity:       max(1, $quantity),
      flatAmount:     (int) $fixed,
      includeFlat:    true,
  );

IMPORTANT — $unitPrice:
  Look for a per-unit price variable in scope. If the engine's line object has it, use it.
  If only line total ($base) and $quantity are available:
    $unitPrice = $quantity > 0 ? (int) round($base / $quantity) : $base;

IMPORTANT — $fixed:
  This is the FBN/FBP fixed-fee-per-unit already resolved from the category. Keep it as-is.
  If it is named differently in the file, use the actual variable name.

### Performance note (add as inline comment):
  // TODO: eager-load commissionTiers on categories in CheckoutPricingEngine::loadLineData()
  // to avoid N+1 when orders have many line items across different categories.

## Output the changed file only. Do NOT run artisan or tests.
```

---

---

# PROMPT 5 — MarketerCommissionRule Minimum Floor (Unchanged Logic)

```
You are a Laravel 11 backend developer on the marketplace_platforms monorepo.
Backend: /home/claude/repo/backend

## Invariants
- All monetary values: BIGINT base-currency integers.
- Read files before editing.

## Context
MarketerCommissionRule still has commission_min_amount (nullable bigint) from migration 000002
(that column was NOT reverted — only commission_threshold_price is being dropped from it;
check if migration 100001 also dropped commission_threshold_price from marketer_commission_rules).

Run:
  grep -n "marketer_commission_rules\|marketer_category" backend/database/migrations/2026_10_06_100001_revert_flat_variable_commission_columns.php

If yes (the reverting migration drops commission_threshold_price from those tables),
confirm commission_min_amount was NOT dropped (it stays).

## Task A — Update MarketerCommissionRule::resolveAmount()

File: backend/app/Models/MarketerCommissionRule.php
Read it. Find resolveAmount(). After computing $calculated ($percent + $flat), add:

  // Per-unit minimum floor
  $minFloor = (int) ($this->commission_min_amount ?? 0);
  if ($minFloor > 0) {
      $safeQty    = max(1, $quantity);
      $perUnit    = (int) floor($calculated / $safeQty);
      $perUnit    = max($perUnit, $minFloor);
      $calculated = $perUnit * $safeQty;
  }

  return max(0, $calculated);

Do NOT add tier lookups. Marketer rules use a single rate with a floor only.

## Task B — Marketer rule admin form and controller

Run: grep -rn "commission_rate\|commission_flat" backend/resources/views/admin/ --include="*.blade.php" -l | grep -i marketer

If a form exists, add after existing rate fields:
  <div>
      <label class="block text-xs font-medium text-gray-700 mb-1">
          {{ __('Minimum Commission per Unit') }}
          <span class="text-xs text-gray-400">({{ __('base-currency int; 0 = no floor') }})</span>
      </label>
      <input type="number" name="commission_min_amount" min="0" step="1"
             value="{{ old('commission_min_amount', $rule->commission_min_amount ?? 0) }}"
             class="w-full rounded border-gray-300 text-sm">
  </div>

Also update controller store/update validation + assignment:
  'commission_min_amount' => 'nullable|integer|min:0',
  // in data array:
  'commission_min_amount' => $request->commission_min_amount ? (int) $request->commission_min_amount : null,

## Output changed files only.
```

---

---

## 5. File Index

| Prompt | New Files | Modified Files |
|--------|-----------|----------------|
| 1 | 2 migration files, CategoryCommissionTier.php | Category.php |
| 2 | CategoryCommissionTierController.php | routes/admin.php |
| 3 | — | category form blade, CategoryController.php |
| 4 | — | CheckoutPricingEngine.php |
| 5 | — | MarketerCommissionRule.php, marketer form blade (if exists) |

---

## 6. Post-Apply Verification

```bash
# Schema dump after migrations
cd /home/claude/repo/backend && php artisan schema:dump --prune

# Verify old columns gone, new table exists
grep -A5 "category_commission_tiers" backend/database/schema/mysql-schema.sql

# No flat commission columns left on categories
grep "commission_threshold\|commission_high_rate" backend/database/schema/mysql-schema.sql

# No illegal /100 in engine
grep -n "/ 100\b" backend/app/Services/Checkout/CheckoutPricingEngine.php

# Run unit tests (CommissionCalculator unchanged — should still pass)
php artisan test --filter=CommissionCalculatorTest
```
