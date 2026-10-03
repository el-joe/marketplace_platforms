# Branded QR Code System — Architecture Plan
**Project:** Nawi (ناوي) Marketplace  
**Date:** 2026-10-03

---

## 1. Library / Package Choices

| Concern | Package | Status |
|---------|---------|--------|
| QR Code generation | `endroid/qr-code` ^6.0 | ✅ Already installed |
| Image composition | PHP **GD** (gdimage) | ✅ Available on server |
| Frontend download UI | `qrcode.react` | ✅ Already in frontend |

**Why GD over Intervention Image?** GD is built into PHP, already confirmed on the server (`php -m | grep gd`). Adding Intervention Image would be a heavier dependency for the same result. `imagick` is also available as a fallback.

---

## 2. Entities & URL Routing

### Scan Redirect Routes (web.php — scanned by camera)
```
GET /qr/product/{slug}     → redirect to frontend /[locale]/product/{slug}
GET /qr/vendor/{slug}      → redirect to frontend /[locale]/store/{slug}
GET /qr/marketer/{slug}    → redirect to frontend /[locale]/marketer/{slug}
```

### QR Image API Routes (authenticated downloads)
```
GET /api/admin/v1/qr/product/{id}      → download branded QR PNG (Admin)
GET /api/vendor/v1/qr/my-products/{id} → download branded QR PNG (Vendor)
GET /api/marketer/v1/qr/me             → download branded QR PNG (Marketer)
GET /api/admin/v1/qr/vendor/{id}       → download vendor QR PNG (Admin)
```

---

## 3. Database Schema

No new tables needed. QR codes are generated on-demand and streamed. If caching is needed later, a `qr_code_path` column can be added to `products`, `vendors`, `marketers`. Out of scope for now.

---

## 4. Image Composition Design

```
┌─────────────────────────┐
│  [QR Code — 400×400px]  │  ← endroid/qr-code PNG output
│      [Nawi Logo]        │  ← centered 80×80px overlay
└─────────────────────────┘
   Product #12345 / Name    ← GD imagestring / imagettftext below QR
```

Steps in `BrandedQrService::generate(string $url, string $label): \GdImage`:
1. Generate QR PNG bytes (endroid, ECC=H for logo overlap tolerance)
2. `imagecreatefromstring()` → GD resource
3. Load Nawi logo PNG, resize to ~20% of QR size, overlay center
4. Expand canvas height by 60px below QR
5. Render Arabic/Latin label text using `imagettftext` with bundled font
6. Return PNG stream

---

## 5. Step-by-Step Execution Plan

| Task | Description |
|------|-------------|
| **Task 1** | Create `BrandedQrService` — core image generation service |
| **Task 2** | Create `QrCodeController` with product / vendor / marketer actions |
| **Task 3** | Register web redirect routes + API download routes |
| **Task 4** | Copy/prepare Nawi logo asset into `backend/public/images/` |
| **Task 5** | Commit each task separately |

---

## 6. Key Design Decisions

- **ECC Level H** on the QR ensures the embedded logo doesn't break scannability (up to 30% of QR can be obscured).
- **On-demand generation** — no storage needed; PNG is streamed with `Content-Disposition: attachment`.
- **Locale-aware redirect** — scan routes read `Accept-Language` header or default to `ar` to build the correct frontend URL.
- **Auth on download routes** — QR images are generated only for authenticated users (vendor for their own products, admin for any entity).
