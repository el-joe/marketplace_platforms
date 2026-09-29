# Exclusive Contract Validation Fix

## Problem

Two bugs exist in the exclusive contract creation flow on the admin marketer profile page:

### Bug 1 — No error shown for conflicting exclusive contracts

**Root cause:** `StoreExclusiveContractRequest::withValidator()` only checks for conflicts when a `classified_listing_id` or `classified_category_id` is provided. When neither is given (a "global" contract scoped to all categories), no overlap check runs. This allows unlimited global exclusive contracts to be created with overlapping date ranges.

**Expected:** System rejects the request with an error "يوجد عقد حصري نشط متعارض لهذا الإعلان."

### Bug 2 — Error messages not visible near the form

**Root cause:** The exclusive contract form has no `@error()` directives. Validation errors only appear via the global top-right flash message (`x-flash-message`), which auto-dismisses in 5 seconds. Since the form is halfway down a long page, users often miss the flash.

Additionally, form fields don't restore old values (`old()`) after a validation failure.

---

## Fixes

### 1. Backend — `StoreExclusiveContractRequest` (sub-agent prompt)

**File:** `backend/app/Http/Requests/Admin/StoreExclusiveContractRequest.php`

Add a third branch in `withValidator()` for when both `$listingId` and `$categoryId` are empty (global contract):

```php
// After the existing if ($categoryId) block:
if (!$listingId && !$categoryId) {
    $globalOverlaps = ExclusiveContract::whereNull('classified_listing_id')
        ->whereNull('classified_category_id')
        ->whereIn('status', ['pending', 'active'])
        ->when($this->route('exclusiveContract'), fn ($q, $current) => $q->whereKeyNot($current->id))
        ->where('starts_at', '<', $endsAt)
        ->where('ends_at', '>', $startsAt)
        ->exists();

    if ($globalOverlaps) {
        $validator->errors()->add(
            'global',
            __('admin.contract_global_conflict_error')
        );
    }
}
```

Also add the missing translation key `contract_global_conflict_error` to both `lang/en/admin.php` and `lang/ar/admin.php`.

### 2. Blade view — `admin/marketers/show.blade.php`

**File:** `backend/resources/views/admin/marketers/show.blade.php`

In the exclusive contract form section:

1. Add an error summary banner directly above the form (inside the card, above the `<form>` tag) that shows all errors from the `exclusive_contract` error bag — or check `$errors->has()` for relevant keys.
2. Add `@error('classified_listing_id')`, `@error('classified_category_id')`, `@error('starts_at')`, `@error('ends_at')`, `@error('global')` inline error messages under each field.
3. Add `value="{{ old('classified_listing_id') }}"`, `old('starts_at')`, `old('ends_at')`, `old('notes')` to restore form values on validation failure.
4. Add `id="exclusive-contracts-form"` and an anchor `<a id="exclusive-contracts-error"></a>` so the redirect can scroll to the error area.

---

## Files Changed

| File | Change |
|------|--------|
| `backend/app/Http/Requests/Admin/StoreExclusiveContractRequest.php` | Add global conflict check |
| `backend/lang/en/admin.php` | Add `contract_global_conflict_error` key |
| `backend/lang/ar/admin.php` | Add `contract_global_conflict_error` key (Arabic) |
| `backend/resources/views/admin/marketers/show.blade.php` | Inline errors + old() values in contract form |
