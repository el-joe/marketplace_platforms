# Timezones

## Rules

| Layer | Timezone | How |
|---|---|---|
| Database / PHP | **UTC** | `config('app.timezone') = UTC`. Never store local time. |
| Admin panel | **Oman — Asia/Muscat (GMT+4)** | `config('app.admin_timezone')` (`ADMIN_TIMEZONE`). Shown in the top bar. |
| Seller panel | Seller's country | `countries.timezone` of the vendor's country. |
| Storefront (Next.js) | Visited country | `countries.timezone`, exposed as `timezone` on `GET /api/customer/v1/countries`. |

`App\Support\Timezones` is the single source of these rules.

## Backend helpers (`app/Helpers/timezone.php`)

| Helper | Use |
|---|---|
| `admin_tz($date)` | Show a stored UTC date in the admin panel. |
| `vendor_tz($date)` | Show a stored UTC date in the seller panel (web guard). |
| `country_tz($date, $country)` | Show a stored UTC date in a country's local time (emails, PDFs, …). |
| `format_tz($date)` / `tz_label($date)` | Add the offset, e.g. `07/11/2026 03:11 (GMT+04:00)`. |
| `admin_input_value($date, $format = 'Y-m-d H:i')` | Pre-fill an admin date-time input (Oman time). |
| `admin_day_start($ymd)` / `admin_day_end($ymd)` | Admin date filters on UTC columns: `->where('created_at', '>=', admin_day_start($v))`. |
| `admin_day_range($ymd = today in Oman)` | "Today" counters: `->whereBetween('created_at', admin_day_range())`. |

Do **not** use `whereDate($timestampColumn, …)` in the admin panel: it cuts days at UTC midnight (04:00 Oman).
`whereDate` stays correct for real `DATE` columns.

## Admin input

The `admin.timezone` middleware (`ConvertAdminDateTimeInputs`, on every authenticated admin route) converts
every posted `Y-m-d H:i[:s]` / `Y-m-dTH:i` value — including nested and JSON input — from Oman time to UTC
before validation. Date-only values (`Y-m-d`), ISO strings with an offset, and free text are untouched.
On validation failure, `old()` gets back what the admin typed.

So admin forms must pre-fill date-times in Oman time: pass the Carbon value to `<x-form.date-picker>` (it
converts) or use `admin_input_value()` for raw inputs. Never echo a raw UTC `Y-m-d H:i:s` into a field that is
posted back to an admin route.

## Storefront (frontend)

- `<LocalDateTime value={iso} />` — renders in the visited country's timezone (`format` prop for custom fields).
- `useCountryDateFormatter()` — same, as a string (for `t()` interpolation).
- `useCountryTimezone()` — the IANA zone itself.

Do not use `toLocaleString()` / date-fns `format(new Date(iso))` for API timestamps: they use the browser's or
the server's (UTC) timezone.

## Database session timezone

MySQL's session timezone defaults to the server's system timezone. All `TIMESTAMP` columns are written and read
through it, so the application sees UTC as long as it never changes; only database-side defaults
(`CURRENT_TIMESTAMP`, `NOW()`) follow the system clock instead of UTC.

`DB_TIMEZONE` (unset by default) sets it explicitly. Set `DB_TIMEZONE=+00:00` **only** when
`SELECT @@system_time_zone` is already UTC on that server; on any other server it shifts how every existing
`TIMESTAMP` value is read and needs a planned data migration first.

## Existing data

Before this change, admin date-times were stored as typed (Oman wall time treated as UTC), i.e. 4 hours late.
After it, those records show 4 hours later in their edit forms. Review active schedules (flash sales, coupons,
vouchers, banners, page-builder visibility, live streams) after deploy; no automatic data migration is applied
because it cannot tell which values were already entered with the offset in mind.

## Follow-ups

- Storefront date-only displays still using the browser timezone (order history/list, invoice, checkout
  success, reviews, chat list, credits, payments, classifieds, travel cards) → move to `<LocalDateTime>`.
- Admin JS-built tables that format dates in the browser (page-builder lists) → format server-side with `admin_tz`.
- Notifications/emails that print times → `country_tz($date, $order->country)`.
