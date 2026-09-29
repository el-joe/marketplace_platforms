# Storage Fees — Storage Days Calculated from Arrival Date

**Date:** 2026-09-29  
**Scope:** Backend (PHP/Laravel) + Admin panel (Blade)  
**URL under test:** https://admin.noon.codefanz.com/fbn/storage-fees

---

## Problem Statement

The client asked: *"How are storage days calculated starting from the product's arrival date?"*

The intended behaviour is:
> The "storage days" counter starts from the date the product **arrives at the warehouse** (i.e. when the inbound request is marked **Received**), NOT from the date the vendor listing was created.

### Root-cause audit

| # | Finding | File / Line | Impact |
|---|---------|-------------|--------|
| 1 | `FbnController::receiveInbound()` calls `$inventory->increment('quantity_on_hand', …)`, which runs a raw SQL `UPDATE` and **bypasses the Eloquent `saving` observer**. The observer is the only place `first_stocked_at` gets stamped. | `FbnController.php:200` | `first_stocked_at` stays `NULL`; fee job falls back to `warehouse_inventories.created_at` (listing creation date) — wrong anchor. |
| 2 | `fbn_inbound_requests` has no `received_at` timestamp column — there is no audit record of when the shipment physically arrived. | `FbnInboundRequest` model | Can't distinguish "when received" from "when record created". |
| 3 | `fbn_storage_fees` has no `stored_since` column — the admin UI cannot show admins what date the storage clock started from. | `FbnStorageFee` model | Admins have no visibility; hard to audit. |
| 4 | `storageFeesDatatable()` only joins `vendors` — no product name column. | `FbnController.php:~230` | Storage fees table missing product context. |

---

## Solution Plan

### Step 1 — Migration: add `received_at` to `fbn_inbound_requests`

New nullable `timestamp` column `received_at` — stamped when admin clicks "Receive".

File: `backend/database/migrations/2026_09_29_210000_add_received_at_to_fbn_inbound_requests.php`

### Step 2 — Migration: add `stored_since` to `fbn_storage_fees`

New nullable `date` column `stored_since` — snapshot of `first_stocked_at` captured at fee generation time.

File: `backend/database/migrations/2026_09_29_210001_add_stored_since_to_fbn_storage_fees.php`

### Step 3 — Fix `FbnController::receiveInbound()`

Replace `$inventory->increment('quantity_on_hand', …)` with a proper Eloquent `fill + save` call **and** explicitly stamp `first_stocked_at` and `received_at`:

```php
// Instead of:
$inventory->increment('quantity_on_hand', $data['quantity_received']);
$inventory->decrement('quantity_inbound', …);

// Use:
$inboundRequest->update([
    …,
    'received_at' => now(),
]);

$inventory->quantity_on_hand += $data['quantity_received'];
$inventory->quantity_inbound = max(0, $inventory->quantity_inbound - $data['quantity_received']);
if ($inventory->first_stocked_at === null) {
    $inventory->first_stocked_at = now();
}
$inventory->save();
```

File: `backend/app/Http/Controllers/Admin/FbnController.php`

### Step 4 — Update `GenerateFbnStorageFeesJob` to persist `stored_since`

Add `stored_since` to the `updateOrCreate` payload so the fee record carries its own snapshot of the arrival date.

File: `backend/app/Jobs/GenerateFbnStorageFeesJob.php`

### Step 5 — Update `FbnStorageFee` model

Add `stored_since` to `$fillable` and `$casts` (`'date'`).

File: `backend/app/Models/FbnStorageFee.php`

### Step 6 — Update `storageFeesDatatable()` to join product name + expose `stored_since`

Add join to `warehouse_inventories → vendor_listings → product_variants → products` and include `stored_since` and product name in the datatable response.

File: `backend/app/Http/Controllers/Admin/FbnController.php`

### Step 7 — Update admin Blade view

- Add `stored_since` ("Arrival Date") column to the table header + DataTables columns config.
- Add a small info note above the table: *"Storage days are counted from the date the product arrived at the warehouse (Arrival Date column), not from the listing creation date."*
- Show product name column.

File: `backend/resources/views/admin/fbn/storage-fees/index.blade.php`

### Step 8 — Update lang files (EN + AR)

Add translation keys: `arrival_date`, `product`, `storage_days_note`.

Files: `backend/lang/en/admin.php`, `backend/lang/ar/admin.php`

---

## Sub-agent Prompts

Use these prompts to delegate each step to a sub-agent:

### Agent A — Migrations
```
Create two Laravel migration files in backend/database/migrations/:

1. File: 2026_09_29_210000_add_received_at_to_fbn_inbound_requests.php
   - Add nullable timestamp column `received_at` after `quantity_received` to table `fbn_inbound_requests`
   - down() drops it

2. File: 2026_09_29_210001_add_stored_since_to_fbn_storage_fees.php
   - Add nullable date column `stored_since` after `days_in_storage` to table `fbn_storage_fees`
   - down() drops it
```

### Agent B — Fix receiveInbound() + update FbnInboundRequest model
```
In backend/app/Http/Controllers/Admin/FbnController.php, method receiveInbound():
- Replace the two increment/decrement calls with proper Eloquent saves so the model observer fires
- Set first_stocked_at on inventory (only if null) to now()
- Set received_at on the inbound request to now()

In backend/app/Models/FbnInboundRequest.php:
- Add `received_at` to $fillable
- Add `'received_at' => 'datetime'` to $casts
```

### Agent C — Update job + model
```
In backend/app/Jobs/GenerateFbnStorageFeesJob.php:
- Add `stored_since` => $storedSince->toDateString() to the updateOrCreate() attributes array

In backend/app/Models/FbnStorageFee.php:
- Add `stored_since` to $fillable
- Add `'stored_since' => 'date'` to $casts
```

### Agent D — Datatable, Blade view, and lang keys
```
In backend/app/Http/Controllers/Admin/FbnController.php, method storageFeesDatatable():
- Add joins: warehouse_inventories, vendor_listings, product_variants, products
- Add `products.name_en as product_name` to the select
- Add `fbn_storage_fees.stored_since` to the select
- Return `product_name` and `stored_since` (formatted as 'd M Y') in the datatable callback row

In backend/resources/views/admin/fbn/storage-fees/index.blade.php:
- Add an info alert above the table: "Storage days are counted from the product's arrival date at the warehouse (shown in the Arrival Date column), not from the listing creation date."
- Add table header columns: Product (after Vendor), Arrival Date (after Days in Storage)
- Add DataTables columns: { data: 'product_name', orderable: false }, { data: 'stored_since', orderable: false }

In backend/lang/en/admin.php, inside the fbn_section array:
- 'product' => 'Product'
- 'arrival_date' => 'Arrival Date'
- 'storage_days_note' => 'Storage days are counted from the product\'s arrival date at the warehouse, not from the listing creation date.'

In backend/lang/ar/admin.php, inside the fbn_section array:
- 'product' => 'المنتج'
- 'arrival_date' => 'تاريخ الوصول'
- 'storage_days_note' => 'أيام التخزين تُحسب من تاريخ وصول المنتج إلى المستودع، وليس من تاريخ إنشاء القائمة.'
```
