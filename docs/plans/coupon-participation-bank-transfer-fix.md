# Fix: Coupon Participation Bank Transfer – File Upload Not Visible After Validation Error

## Problem

**Page:** `/coupon-participation` (partner & marketer panels)

When a vendor/marketer selects **Bank Transfer** as the payment method, the proof-of-payment file input appears (via Alpine.js `x-show`). If the form is submitted with a missing or invalid file, the server returns a validation error and redirects back to the page. On reload, Alpine.js re-initialises with the hardcoded default `method: 'wallet'`, so the bank-transfer proof input is hidden again by `x-show`. The user sees the red error ("إثبات التحويل البنكي مطلوب") but cannot find where to upload the file.

## Root Cause

```php
// Both partner & marketer view — hardcoded Alpine default
x-data="{ method: 'wallet', … }"
```

`old('payment_method')` is never read, so the Alpine component always starts in wallet mode after a failed submission.

## Fix (sub-agent prompt)

> You are fixing the coupon participation bank-transfer file-upload bug in a Laravel + Alpine.js application.
>
> **Files to edit**
> 1. `backend/resources/views/partner/coupon-participation/index.blade.php`
> 2. `backend/resources/views/marketer/coupon-participation/index.blade.php`
>
> **Change required in both files**
>
> In the `x-data` initialiser on the `<form>` element for each open invitation, replace the hardcoded `method: 'wallet'` with `old('payment_method', 'wallet')` so that Alpine restores the previously selected payment method when the page reloads after a validation error.
>
> **Before (both files):**
> ```php
> x-data="{ method: 'wallet', balance: {{ … }}, minFee: {{ … }} }"
> ```
>
> **After (both files):**
> ```php
> x-data="{ method: '{{ old('payment_method', 'wallet') }}', balance: {{ … }}, minFee: {{ … }} }"
> ```
>
> The invitation-specific `balance` and `minFee` expressions should remain unchanged.
>
> After editing, run `vendor/bin/pint --dirty --format agent` from the `backend/` directory and confirm the diff looks correct.

## Acceptance criteria

- After a failed bank-transfer submission the page reloads with the payment method still set to **Bank Transfer** and the file upload input is visible.
- Submitting with a valid file and `bank_transfer` selected succeeds (no change to backend logic needed).
- The partner panel at `/coupon-participation` and the marketer panel at `/marketer/coupon-participation` both behave correctly.
