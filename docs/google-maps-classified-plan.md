# Google Maps — Classified Listings Implementation Plan

**Date:** 2026-10-04  
**Author:** Fullstack analysis + sub-agent execution plan  
**Scope:** Partner panel map picker, Frontend detail map pin, Frontend list map view (Booking.com style)

---

## 1. Executive Summary

### What exists today
| Layer | State |
|---|---|
| `classified_listings` DB | `latitude` / `longitude` columns already exist (`decimal(10,7)`) |
| Partner panel (Blade) | Two raw `<input type="number">` fields — no interactive map |
| `Storefront/ClassifiedController::mapData()` | Already returns pins + bounds filtering (500-item limit) — but it is a web route, not API |
| API: `ClassifiedListingDetailResource` | lat/lng **intentionally omitted** (comment says "approximate area only") |
| Frontend: Classified detail page | No map component at all |
| Frontend: Classified list page | No map/list toggle |

### What we will build
1. **[Task 1]** Partner panel: Replace lat/lng text inputs → interactive Google Maps picker (search + click-to-pin + drag)
2. **[Task 2]** Backend API: Expose lat/lng in the classified detail API response + add a public map-pins API endpoint for Next.js consumption
3. **[Task 3]** Frontend detail page: Google Map pin block inside the listing detail view
4. **[Task 4]** Frontend list page: Booking.com-style map/list toggle with live bounds filtering

---

## 2. Architecture Decisions

### Google Maps API key strategy
- One key used across **both** Laravel Blade (partner panel) and Next.js (frontend)
- Key stored in:
  - Laravel: `GOOGLE_MAPS_API_KEY` env var, exposed via `config('services.google.maps_key')`
  - Next.js: `NEXT_PUBLIC_GOOGLE_MAPS_API_KEY` env var
- Libraries needed: **Maps JavaScript API** + **Places API** (for address search)

### Map library for Next.js
Use **`@vis.gl/react-google-maps`** (the official Google Maps React library).  
- Lightweight, SSR-safe with `'use client'` boundary  
- No deprecated `@react-google-maps/api` footprint  
- Install: `npm install @vis.gl/react-google-maps`

### Lat/lng in API
- Add lat/lng to `ClassifiedListingDetailResource` (remove the "intentionally omitted" restriction — we already show city name, approximate area exposure is already there; a pin only gives the seller's chosen point, not their home address)
- Add a new **public API endpoint** `GET /api/v1/classified/map-pins` that mirrors the existing web `mapData` route but returns JSON for Next.js, with optional `bounds[south/north/east/west]`, `category`, `purpose`, `city_id` filters

---

## 3. Detailed Task Breakdown

---

### Task 1 — Partner Panel: Google Maps Picker
**Files to change:**
- `backend/resources/views/partner/classifieds/index.blade.php` — Replace the 2 lat/lng inputs with a map div + hidden inputs
- `backend/resources/js/partner/classifieds.js` — Wire Google Maps JS SDK; add Places Autocomplete, marker drag, click-to-place
- `backend/config/services.php` — Add `google.maps_key`
- `.env` / `.env.example` — Add `GOOGLE_MAPS_API_KEY`

**UX flow:**
1. In Step 3 (Location & Attributes) of the classified wizard, instead of two text inputs, render:
   - A Google Places search bar (autocomplete) at the top
   - An interactive map below it (400px height)
   - A draggable red pin in the center
2. When user searches an address → map pans + pin placed at result
3. When user clicks on map → pin moves to clicked point
4. When user drags pin → coordinates update
5. Two hidden `<input type="hidden">` fields hold `latitude` / `longitude` and are submitted with the form
6. If listing already has coordinates (edit mode), initialize the map centered on those coordinates with the pin placed

**Implementation detail:**
```html
<!-- Replace existing lat/lng block with: -->
<div id="cl-location-section" class="space-y-3">
  <label class="block text-sm font-semibold text-gray-800">الموقع على الخريطة</label>
  
  <!-- Places search -->
  <input id="cl-map-search" type="text" placeholder="ابحث عن عنوان..." 
    class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm" />
  
  <!-- Map container -->
  <div id="cl-map" style="height:380px;border-radius:12px;overflow:hidden;"></div>
  
  <!-- Coordinate display (read-only, for user confirmation) -->
  <div class="flex gap-4 text-xs text-gray-500">
    <span>Lat: <span id="cl-lat-display">—</span></span>
    <span>Lng: <span id="cl-lng-display">—</span></span>
  </div>
  
  <!-- Hidden inputs submitted with form -->
  <input type="hidden" id="cl-latitude" name="latitude">
  <input type="hidden" id="cl-longitude" name="longitude">
</div>
```

**JS initialization (in classifieds.js):**
```javascript
function initMap() {
  const defaultCenter = { lat: 24.7136, lng: 46.6753 }; // Riyadh fallback
  const existingLat = parseFloat(document.getElementById('cl-latitude').value) || null;
  const existingLng = parseFloat(document.getElementById('cl-longitude').value) || null;
  const center = (existingLat && existingLng) ? { lat: existingLat, lng: existingLng } : defaultCenter;

  const map = new google.maps.Map(document.getElementById('cl-map'), {
    zoom: existingLat ? 15 : 12,
    center,
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: false,
  });

  const marker = new google.maps.Marker({
    position: center,
    map,
    draggable: true,
  });

  if (existingLat && existingLng) setCoords(existingLat, existingLng);

  function setCoords(lat, lng) {
    document.getElementById('cl-latitude').value = lat.toFixed(7);
    document.getElementById('cl-longitude').value = lng.toFixed(7);
    document.getElementById('cl-lat-display').textContent = lat.toFixed(6);
    document.getElementById('cl-lng-display').textContent = lng.toFixed(6);
  }

  // Click on map
  map.addListener('click', (e) => {
    marker.setPosition(e.latLng);
    setCoords(e.latLng.lat(), e.latLng.lng());
  });

  // Drag marker
  marker.addListener('dragend', (e) => {
    setCoords(e.latLng.lat(), e.latLng.lng());
  });

  // Places Autocomplete
  const searchInput = document.getElementById('cl-map-search');
  const autocomplete = new google.maps.places.Autocomplete(searchInput);
  autocomplete.addListener('place_changed', () => {
    const place = autocomplete.getPlace();
    if (!place.geometry) return;
    map.setCenter(place.geometry.location);
    map.setZoom(15);
    marker.setPosition(place.geometry.location);
    setCoords(place.geometry.location.lat(), place.geometry.location.lng());
  });
}
```

**Blade: load Maps SDK in `<head>` or via `@push('scripts')`:**
```blade
<script async defer
  src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places&callback=initMap">
</script>
```

---

### Task 2 — Backend API: Expose lat/lng + New Map Pins Endpoint

**Files to change:**
- `backend/app/Http/Resources/Customer/ClassifiedListingDetailResource.php` — Add `latitude` and `longitude` to the `location` key
- `backend/routes/api_customer_v1.php` — Add new route `GET /classified/map-pins`
- `backend/app/Http/Controllers/Customer/BrowseController.php` (or new `MapController`) — Add `classifiedMapPins()` method

**2a. Detail resource change:**
```php
// In ClassifiedListingDetailResource::toArray()
'location' => [
    'city' => ...,
    'latitude'  => $this->latitude  ? (float) $this->latitude  : null,
    'longitude' => $this->longitude ? (float) $this->longitude : null,
],
```

**2b. New public API endpoint:**
```
GET /api/v1/classified/map-pins
Query params:
  - bounds[south], bounds[north], bounds[east], bounds[west]  (optional — viewport filter)
  - category    (optional — category UUID)
  - purpose     (optional — sale|rent)
  - city_id     (optional)
  - country     (optional — ISO2 code, defaults to request country header)

Response:
{
  "data": [
    { "id": "uuid", "listing_number": "CL-0001", "title": "شقة للبيع", "price": 50000000, "currency": "SAR", "purpose": "sale", "lat": 24.7136, "lng": 46.6753, "slug": "apartment-for-sale-clsf-0001", "thumbnail": "https://..." }
  ]
}
Limit: 300 pins max
```

**Route in `api_customer_v1.php`:**
```php
Route::get('classified/map-pins', [BrowseController::class, 'classifiedMapPins'])
    ->name('customer.classified.map-pins');
```

**Controller method:**
```php
public function classifiedMapPins(Request $request): JsonResponse
{
    $query = ClassifiedListing::select([
            'id', 'listing_number', 'slug', 'title_en', 'title_ar',
            'price', 'currency', 'listing_purpose', 'latitude', 'longitude',
            'classified_category_id',
        ])
        ->with(['images' => fn ($q) => $q->where('is_primary', true)->limit(1)])
        ->where('status', ClassifiedListingStatus::Active)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude');

    if ($request->filled('bounds')) {
        $b = $request->input('bounds');
        $query->whereBetween('latitude',  [(float)$b['south'], (float)$b['north']])
              ->whereBetween('longitude', [(float)$b['west'],  (float)$b['east']]);
    }
    if ($request->filled('category')) {
        $query->where('classified_category_id', $request->category);
    }
    if ($request->filled('purpose')) {
        $query->where('listing_purpose', $request->purpose);
    }
    if ($request->filled('city_id')) {
        $query->where('city_id', $request->city_id);
    }

    $isAr = app()->getLocale() === 'ar';
    $pins = $query->limit(300)->get()->map(fn ($l) => [
        'id'      => $l->id,
        'number'  => $l->listing_number,
        'slug'    => $l->slug,
        'title'   => $isAr ? $l->title_ar : $l->title_en,
        'price'   => $l->price,
        'currency'=> $l->currency,
        'purpose' => $l->listing_purpose,
        'lat'     => (float) $l->latitude,
        'lng'     => (float) $l->longitude,
        'thumbnail' => $l->images->first()
            ? asset('storage/' . $l->images->first()->file_path)
            : null,
    ]);

    return response()->json(['data' => $pins]);
}
```

---

### Task 3 — Frontend: Classified Detail Page Map Pin

**Files to change:**
- `frontend/src/features/classified/classified-view/helpers/types.ts` — Add `latitude?`, `longitude?` to `IClassified.location`
- `frontend/src/features/classified/classified-view/types.ts` — Add `latitude?`, `longitude?` to `ClassifiedDetail`
- `frontend/src/features/classified/classified-view/helpers/to-classified-detail.ts` — Map the new fields
- `frontend/src/features/classified/classified-view/classified-location-map.tsx` — **New file**: Map pin component
- `frontend/src/features/classified/classified-view/index.tsx` — Insert `<ClassifiedLocationMap>` between Description and Features
- `frontend/package.json` — Add `@vis.gl/react-google-maps`

**3a. Type update (`helpers/types.ts`):**
```typescript
export interface Location {
  city: Description;
  latitude?: number | null;
  longitude?: number | null;
}
```

**3b. ClassifiedDetail type update (`types.ts`):**
```typescript
export interface ClassifiedDetail {
  // ... existing fields ...
  latitude?: number | null;
  longitude?: number | null;
}
```

**3c. Mapper update (`to-classified-detail.ts`):**
```typescript
// Inside the return object:
latitude: listing.location?.latitude ?? null,
longitude: listing.location?.longitude ?? null,
```

**3d. New component `classified-location-map.tsx`:**
```tsx
'use client';
import { APIProvider, Map, AdvancedMarker } from '@vis.gl/react-google-maps';
import { useTranslations } from 'next-intl';

interface Props {
  latitude: number;
  longitude: number;
}

export default function ClassifiedLocationMap({ latitude, longitude }: Props) {
  const t = useTranslations('classified');
  const center = { lat: latitude, lng: longitude };

  return (
    <section className="mt-6 pt-6 border-t border-gray-100">
      <h2 className="text-base font-bold text-gray-900 mb-3">{t('locationOnMap')}</h2>
      <div className="rounded-xl overflow-hidden border border-gray-200" style={{ height: 280 }}>
        <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY!}>
          <Map
            defaultCenter={center}
            defaultZoom={14}
            mapId="classified-detail-map"
            disableDefaultUI={false}
            gestureHandling="cooperative"
          >
            <AdvancedMarker position={center} />
          </Map>
        </APIProvider>
      </div>
      <p className="text-xs text-gray-400 mt-1.5">{t('locationApproximate')}</p>
    </section>
  );
}
```

**3e. Insert into `classified-view/index.tsx`:**
```tsx
{/* 5. Location Map (if coordinates available) */}
{listing.latitude && listing.longitude && (
  <ClassifiedLocationMap
    latitude={listing.latitude}
    longitude={listing.longitude}
  />
)}
```

---

### Task 4 — Frontend: Classified List Page Map View (Booking.com Style)

**UX design (mirroring booking.com):**
- A toggle button "قائمة / خريطة" appears in the top filter bar
- **List view** (default): current grid/list of cards with sidebar
- **Map view**: 
  - Split layout: listing cards on LEFT (scrollable, ~380px wide)
  - Map fills the RIGHT (70% width, sticky, fills viewport height)
  - Price bubble pins on map (e.g. "52,000 SAR")
  - Hovering a card highlights its pin; hovering a pin highlights its card
  - Clicking a pin shows a mini popup card (image + title + price + link)
  - Google Places search bar INSIDE the map (top-left corner)
  - As user pans/zooms map → debounced re-fetch of pins with new viewport bounds
  - Filter bar (purpose, city, price) above the split layout still works

**Files to change:**
- `frontend/src/features/classified/classifiedList/index.tsx` — Add view-mode state
- `frontend/src/features/classified/classifiedList/top-filter-bar.tsx` — Add map/list toggle button
- `frontend/src/features/classified/classifiedList/classified-map-view.tsx` — **New file**: full map view component
- `frontend/src/features/classified/classifiedList/classified-map-pin-popup.tsx` — **New file**: pin popup card
- `frontend/src/features/classified/classifiedList/api/get.ts` — Add `getClassifiedMapPinsService()`

**4a. Map pins API service (`api/get.ts` addition):**
```typescript
export interface MapPin {
  id: string;
  number: string;
  slug: string;
  title: string;
  price: number;
  currency: string;
  purpose: string;
  lat: number;
  lng: number;
  thumbnail: string | null;
}

export const getClassifiedMapPinsService = (params: {
  bounds?: { south: number; north: number; east: number; west: number };
  category?: string;
  purpose?: string;
  city_id?: string;
}) =>
  fetchInstance<{ data: MapPin[] }>('/classified/map-pins', {
    method: 'GET',
    // build query string from params
  });
```

**4b. Map view component (`classified-map-view.tsx`):**
```tsx
'use client';
import { useState, useCallback, useRef } from 'react';
import { APIProvider, Map, AdvancedMarker, useMap } from '@vis.gl/react-google-maps';
import { MapPin } from './api/get';

// Price bubble marker
function PriceBubble({ pin, isActive, onClick }: { pin: MapPin; isActive: boolean; onClick: () => void }) {
  const formatted = new Intl.NumberFormat('ar-SA', { notation: 'compact' }).format(pin.price / 100);
  return (
    <AdvancedMarker position={{ lat: pin.lat, lng: pin.lng }} onClick={onClick}>
      <div className={`px-2 py-1 rounded-full text-xs font-bold shadow-md cursor-pointer transition-all
        ${isActive ? 'bg-gray-900 text-white scale-110' : 'bg-white text-gray-900 border border-gray-300'}`}>
        {formatted} {pin.currency}
      </div>
    </AdvancedMarker>
  );
}

interface Props {
  initialPins: MapPin[];
  categoryId?: string;
  onBoundsChange: (bounds: { south: number; north: number; east: number; west: number }) => void;
}

export default function ClassifiedMapView({ initialPins, categoryId, onBoundsChange }: Props) {
  const [pins, setPins] = useState(initialPins);
  const [activePin, setActivePin] = useState<MapPin | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleBoundsChanged = useCallback((bounds: google.maps.LatLngBounds) => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      onBoundsChange({
        south: bounds.getSouthWest().lat(),
        north: bounds.getNorthEast().lat(),
        west:  bounds.getSouthWest().lng(),
        east:  bounds.getNorthEast().lng(),
      });
    }, 600);
  }, [onBoundsChange]);

  return (
    <div className="relative h-[calc(100vh-120px)]">
      <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY!}>
        <Map
          mapId="classified-list-map"
          defaultZoom={11}
          defaultCenter={{ lat: 24.7136, lng: 46.6753 }}
          gestureHandling="greedy"
          disableDefaultUI={false}
          onBoundsChanged={(e) => handleBoundsChanged(e.map.getBounds()!)}
        >
          {pins.map((pin) => (
            <PriceBubble
              key={pin.id}
              pin={pin}
              isActive={activePin?.id === pin.id}
              onClick={() => setActivePin(pin)}
            />
          ))}
        </Map>

        {/* Pin popup */}
        {activePin && (
          <div className="absolute bottom-6 left-1/2 -translate-x-1/2 z-10 w-64 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
            {activePin.thumbnail && (
              <img src={activePin.thumbnail} alt={activePin.title} className="w-full h-36 object-cover" />
            )}
            <div className="p-3">
              <p className="font-bold text-sm text-gray-900 truncate">{activePin.title}</p>
              <p className="text-blue-600 font-bold text-sm mt-0.5">
                {new Intl.NumberFormat('ar-SA').format(activePin.price / 100)} {activePin.currency}
              </p>
              <a href={`/classified/${activePin.slug}`}
                className="mt-2 block text-center text-xs font-semibold text-white bg-blue-600 rounded-lg py-1.5">
                عرض الإعلان
              </a>
            </div>
            <button onClick={() => setActivePin(null)}
              className="absolute top-2 right-2 w-6 h-6 rounded-full bg-white/80 flex items-center justify-center text-gray-700 text-xs">✕</button>
          </div>
        )}
      </APIProvider>
    </div>
  );
}
```

**4c. Toggle in `top-filter-bar.tsx`:**
```tsx
// Add view mode toggle buttons:
<div className="flex border border-gray-200 rounded-lg overflow-hidden">
  <button onClick={() => setViewMode('list')}
    className={`px-3 py-1.5 text-sm font-medium ${viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'}`}>
    ☰ قائمة
  </button>
  <button onClick={() => setViewMode('map')}
    className={`px-3 py-1.5 text-sm font-medium ${viewMode === 'map' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'}`}>
    🗺️ خريطة
  </button>
</div>
```

**4d. Split-pane layout in `index.tsx` (map mode):**
```tsx
{viewMode === 'map' && (
  <div className="flex h-[calc(100vh-120px)] gap-0">
    {/* Left: scrollable cards list */}
    <div className="w-[380px] shrink-0 overflow-y-auto border-r border-gray-200 bg-white">
      {listings.map((listing) => (
        <ClassifiedCard key={listing.listing_id} listing={listing} compact />
      ))}
    </div>
    {/* Right: map */}
    <div className="flex-1">
      <ClassifiedMapView
        initialPins={mapPins}
        categoryId={categoryId}
        onBoundsChange={handleBoundsChange}
      />
    </div>
  </div>
)}
```

---

## 4. Environment Variables Required

**Laravel `.env`:**
```
GOOGLE_MAPS_API_KEY=AIza...
```

**Next.js `.env.local`:**
```
NEXT_PUBLIC_GOOGLE_MAPS_API_KEY=AIza...
```

**Google Cloud Console — APIs to enable:**
- Maps JavaScript API
- Places API
- (Optional for future) Geocoding API

**API key restrictions (recommended):**
- HTTP referrer restriction for the public Next.js key
- Laravel key: IP restriction (server IP only, if used server-side)

---

## 5. Database

No migrations needed — `latitude` and `longitude` columns already exist on `classified_listings`.

---

## 6. Sub-Agent Execution Prompts

Use these prompts to run each task as an independent sub-agent in `/var/www/marketplace`.

---

### Sub-Agent Prompt 1 — Partner Panel: Google Maps Picker

```
You are implementing Google Maps location picker in the Partner Panel classified listing wizard.

CONTEXT:
- The partner panel is a Laravel Blade/Alpine.js application at /var/www/marketplace/backend
- The file to modify: backend/resources/views/partner/classifieds/index.blade.php
- The JS file to modify: backend/resources/js/partner/classifieds.js
- The existing form is a multi-step wizard. Step 3 (id="cl-wiz-step-3") has a section with id="cl-location-section" that currently contains two <input type="number"> fields for latitude (#cl-latitude) and longitude (#cl-longitude)
- The model already stores latitude/longitude, the controller already accepts them

TASK:
1. Read backend/resources/views/partner/classifieds/index.blade.php (focus on the cl-location-section block around line 242-270)
2. Read backend/resources/js/partner/classifieds.js (understand how the form submit collects lat/lng)
3. Read backend/config/services.php

Then make these changes:

A) In backend/config/services.php, add:
   'google' => [
       'maps_key' => env('GOOGLE_MAPS_API_KEY', ''),
   ],

B) In index.blade.php, REPLACE the cl-location-section block (the two text inputs for lat/lng) with:
   - A Google Places text search input (id="cl-map-search")
   - A map div (id="cl-map", height 380px, border-radius 12px)
   - A coordinate display row (shows lat/lng as read-only text for user confirmation)
   - TWO HIDDEN inputs (id="cl-latitude", id="cl-longitude", type="hidden") — these replace the old number inputs so the form data still submits correctly with the same field names

C) In index.blade.php, in the @push('scripts') section for this page (or a new @push at the bottom of the file), add the Google Maps JS SDK script tag:
   <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places&callback=initClassifiedMap"></script>

D) In backend/resources/js/partner/classifieds.js, add a global function `window.initClassifiedMap` that:
   - Creates a Google Map in the #cl-map div
   - Reads existing values from #cl-latitude and #cl-longitude hidden inputs (for edit mode pre-population)
   - Centers the map: if existing coords exist use them (zoom 15), else use { lat: 24.7136, lng: 46.6753 } (Riyadh, zoom 12)
   - Places a draggable AdvancedMarker (or regular Marker) at the center
   - On map click: moves marker, updates hidden inputs + coordinate display
   - On marker dragend: updates hidden inputs + coordinate display
   - Wires Google Places Autocomplete to #cl-map-search: on place_changed, pans map + moves marker + updates inputs
   - A helper setCoords(lat, lng) updates both hidden inputs and the display spans

E) Make sure the JS form submission (the part that builds FormData and POSTs to the store endpoint) already reads from #cl-latitude and #cl-longitude. If it uses getElementById and reads .value, it will still work since we kept the same IDs. Verify this and note if any changes are needed.

After making changes, confirm:
- The hidden inputs have the same IDs (cl-latitude, cl-longitude) as the old inputs so form JS still works
- The map initializes properly when Step 3 becomes visible in the wizard
- No regression to other wizard steps
```

---

### Sub-Agent Prompt 2 — Backend API: Expose lat/lng + Map Pins Endpoint

```
You are adding lat/lng to the classified detail API response and creating a new map-pins endpoint.

CONTEXT:
- Laravel backend at /var/www/marketplace/backend
- The classified detail API resource: backend/app/Http/Resources/Customer/ClassifiedListingDetailResource.php
  - Currently the 'location' key has a comment "lat/lng intentionally omitted" — we are REMOVING this restriction
- API routes file: backend/routes/api_customer_v1.php
- The BrowseController: backend/app/Http/Controllers/Customer/BrowseController.php
  - This controller already has classifiedIndex() — add a new classifiedMapPins() method here
- ClassifiedListingStatus enum: backend/app/Enums/ClassifiedListingStatus.php

TASK:

1. Read ClassifiedListingDetailResource.php
   Add latitude and longitude inside the 'location' key:
   'location' => [
       'city' => ... (keep existing),
       'latitude'  => $this->latitude  !== null ? (float) $this->latitude  : null,
       'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
   ],
   Remove or update the comment about omitting lat/lng.

2. Read BrowseController.php
   Add a new public method classifiedMapPins(Request $request): JsonResponse
   
   This method must:
   - Query ClassifiedListing with status = Active, whereNotNull latitude, whereNotNull longitude
   - Load the primary image (images relation with is_primary=true, limit 1)
   - Accept optional query parameters:
     * bounds[south], bounds[north], bounds[east], bounds[west] — whereBetween filters
     * category — filter by classified_category_id UUID
     * purpose — filter by listing_purpose (sale|rent)
     * city_id — filter by city_id
   - Return max 300 results
   - Map each listing to: { id, number (listing_number), slug, title (locale-aware: ar or en), price (integer), currency, purpose (listing_purpose), lat (float), lng (float), thumbnail (asset URL or null) }
   - Return response()->json(['data' => $pins])
   
   The country filter is optional — if a 'country' query param (ISO2 code) is passed, filter by country_id.

3. Read api_customer_v1.php
   Add this route (public, no auth):
   Route::get('classified/map-pins', [BrowseController::class, 'classifiedMapPins'])
       ->name('customer.classified.map-pins');
   
   Place it near the other classified routes (around line 102-103 where GET 'classified' exists).

4. Add necessary imports to BrowseController (ClassifiedListing, ClassifiedListingStatus, Country, JsonResponse, Request).

Verify:
- The detail API response now includes latitude/longitude inside the 'location' key
- The new /classified/map-pins endpoint is accessible without authentication
- The bounds filtering correctly uses whereBetween with float casting
```

---

### Sub-Agent Prompt 3 — Frontend: Classified Detail Page Map Pin

```
You are adding a Google Maps location pin to the classified listing detail page in Next.js.

CONTEXT:
- Next.js frontend at /var/www/marketplace/frontend
- Classified detail view: frontend/src/features/classified/classified-view/
- Key files:
  * helpers/types.ts — IClassified interface (API response shape)
  * types.ts — ClassifiedDetail interface (component data shape)
  * helpers/to-classified-detail.ts — maps API response to component shape
  * index.tsx — the main ClassifiedView component that composes sub-components
- Google Maps React library: @vis.gl/react-google-maps (install it)
- Env var for the API key: NEXT_PUBLIC_GOOGLE_MAPS_API_KEY (already in .env.local or to be added)

TASK:

1. Install the package:
   cd /var/www/marketplace/frontend && npm install @vis.gl/react-google-maps

2. Edit helpers/types.ts:
   In the Location interface, add:
     latitude?: number | null;
     longitude?: number | null;

3. Edit types.ts:
   In ClassifiedDetail interface, add:
     latitude?: number | null;
     longitude?: number | null;

4. Edit helpers/to-classified-detail.ts:
   In the return object of toClassifiedDetail(), add:
     latitude: listing.location?.latitude ?? null,
     longitude: listing.location?.longitude ?? null,

5. Create new file: frontend/src/features/classified/classified-view/classified-location-map.tsx
   This is a 'use client' component. It receives { latitude: number, longitude: number } props.
   
   It renders:
   - A section heading (translated: "الموقع على الخريطة" / "Location on Map")
   - A div with height 280px, border-radius 12px, overflow hidden, border
   - Inside: APIProvider wrapping a Map component with:
     * defaultCenter={{ lat: latitude, lng: longitude }}
     * defaultZoom={14}
     * mapId="classified-detail-map"
     * gestureHandling="cooperative"
     * disableDefaultUI={false}
   - Inside the Map: an AdvancedMarker at the same position
   - Below the map: a small disclaimer text "الموقع تقريبي" / "Approximate location"
   
   Import: APIProvider, Map, AdvancedMarker from '@vis.gl/react-google-maps'
   Use process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY! for the API key

6. Edit index.tsx (ClassifiedView):
   - Import ClassifiedLocationMap
   - After <ClassifiedDescription> and before <ClassifiedFeatures>, insert:
     {listing.latitude && listing.longitude && (
       <ClassifiedLocationMap latitude={listing.latitude} longitude={listing.longitude} />
     )}

7. Check if there is an existing i18n key file for classified-view translations and add the keys:
   - 'locationOnMap': 'الموقع على الخريطة' / 'Location on Map'
   - 'locationApproximate': 'الموقع تقريبي' / 'Approximate location'
   Look in frontend/locale/ or frontend/i18n/ directories for the right file.

Verify TypeScript types are correct and no imports are missing.
```

---

### Sub-Agent Prompt 4 — Frontend: Classified List Page Map View

```
You are implementing a Booking.com-style map view toggle on the classified listings page in Next.js.

CONTEXT:
- Next.js frontend at /var/www/marketplace/frontend
- Classified list feature: frontend/src/features/classified/classifiedList/
- Key files:
  * index.tsx — main ClassifiedsList server component
  * top-filter-bar.tsx — the filter bar at the top (currently has dropdowns)
  * classified-card.tsx — renders one listing card
  * api/get.ts — API fetch helpers
  * helpers/types.ts — ClassifiedItem type
- Google Maps React library: @vis.gl/react-google-maps (should be installed from Task 3)
- The backend now exposes: GET /api/v1/classified/map-pins with optional bounds/category/purpose/city_id params

TASK:

1. Edit api/get.ts — add:
   
   export interface MapPin {
     id: string;
     number: string;
     slug: string;
     title: string;
     price: number;
     currency: string;
     purpose: string;
     lat: number;
     lng: number;
     thumbnail: string | null;
   }
   
   export const getClassifiedMapPinsService = (params?: {
     bounds?: { south: number; north: number; east: number; west: number };
     category?: string;
     purpose?: string;
     city_id?: string;
   }) => {
     const searchParams = new URLSearchParams();
     if (params?.bounds) {
       searchParams.set('bounds[south]', String(params.bounds.south));
       searchParams.set('bounds[north]', String(params.bounds.north));
       searchParams.set('bounds[east]',  String(params.bounds.east));
       searchParams.set('bounds[west]',  String(params.bounds.west));
     }
     if (params?.category) searchParams.set('category', params.category);
     if (params?.purpose)  searchParams.set('purpose', params.purpose);
     if (params?.city_id)  searchParams.set('city_id', params.city_id);
     const qs = searchParams.toString();
     return fetchInstance<{ data: MapPin[] }>(`/classified/map-pins${qs ? '?' + qs : ''}`);
   };

2. Create new file: frontend/src/features/classified/classifiedList/classified-map-view.tsx
   
   This is a 'use client' component.
   Props: { initialPins: MapPin[]; onBoundsChange: (bounds: { south: number; north: number; east: number; west: number }) => void; activeListingId?: string | null; onPinHover?: (id: string | null) => void }
   
   Implementation:
   - Uses APIProvider + Map + AdvancedMarker from @vis.gl/react-google-maps
   - Renders price bubble markers for each pin (a small white pill showing formatted price)
   - The active pin (activeListingId matches pin.id OR hoveredPin matches) gets a dark background
   - When user clicks a pin, show a popup card (image + title + price + "عرض الإعلان" link)
   - Close popup with an X button or by clicking elsewhere on map
   - Debounce (600ms) the onBoundsChange callback when map bounds change
   - Default center: { lat: 24.7136, lng: 46.6753 }, defaultZoom: 11
   - Map fills full height of its container (h-full)
   - The price is in integer base units (÷100 to display) — format with Intl.NumberFormat
   - gestureHandling: "greedy" so mobile scrolling works inside the map

3. Convert index.tsx from a pure server component to a hybrid:
   - Keep the data fetching (getClassifiedsService, getClassifiedCategoriesService) as async server operations
   - Make the view-mode toggle and map a 'use client' child component called ClassifiedsPageClient
   - Or: keep index.tsx as server component and pass initialPins down; add a new ClassifiedsPageClient wrapper that handles the viewMode state
   
   The cleanest approach:
   a) Keep index.tsx as server component — fetch listings AND initial map pins (getClassifiedMapPinsService with category filter but no bounds)
   b) Create classified-page-client.tsx as 'use client' — receives initialListings, initialPins, categories, categoryId as props
   c) classified-page-client.tsx manages viewMode state ('list' | 'map') and pin hover state
   d) In map mode: renders a split layout (left 380px card list, right map)
   e) In list mode: renders the existing layout (sidebar + cards)

4. Add the list/map toggle button to top-filter-bar.tsx (or inside classified-page-client.tsx directly above the content area):
   A simple two-button toggle: "☰ قائمة" | "🗺️ خريطة"
   Pass viewMode and setViewMode as props from the client wrapper.

5. When map view is active, the left-panel card list should use a compact variant of ClassifiedCard
   (or just use the existing card at a smaller scale — no new variants needed unless the existing card is too wide).

6. Cross-highlight behavior:
   - Hovering a card in the left list → sets hoveredPinId → the matching pin bubble turns dark
   - Hovering a pin on the map → scrolls or highlights the matching card in the left list
   This requires passing onMouseEnter/onMouseLeave callbacks from the card and a shared state in classified-page-client.tsx.

7. When bounds change (user pans/zooms map), fetch new pins:
   In classified-page-client.tsx, handle onBoundsChange by calling getClassifiedMapPinsService with the new bounds (client-side fetch) and updating the pins state.

Verify:
- No TypeScript errors
- The server component still renders in SSR (no 'use client' at the top of index.tsx)
- The map only mounts client-side (APIProvider is inside a 'use client' component)
- Price display correctly divides by 100 (integers are stored in base currency units)
```

---

## 7. Testing Checklist

### Partner Panel (Task 1)
- [ ] Create new classified → Step 3 shows Google Maps (not text inputs)
- [ ] Places search returns suggestions and pins the location
- [ ] Click on map places a pin and updates the coordinate display
- [ ] Drag pin updates coordinates
- [ ] Coordinates are submitted with the form (check network request)
- [ ] Edit existing classified with lat/lng → map initializes at the correct location

### Backend API (Task 2)
- [ ] `GET /api/v1/listings/classified/{slug}` response includes `location.latitude` and `location.longitude`
- [ ] `GET /api/v1/classified/map-pins` returns pins array
- [ ] Bounds filtering works: only pins within the viewport are returned
- [ ] Category filter works
- [ ] Endpoint returns 200 without authentication

### Frontend Detail Page (Task 3)
- [ ] Listing WITH lat/lng: map section appears below description
- [ ] Listing WITHOUT lat/lng: map section is absent (no empty box)
- [ ] Map renders with a single pin at the correct location
- [ ] Map is interactive but doesn't interfere with page scroll

### Frontend List Page (Task 4)
- [ ] Default load shows "قائمة" (list) mode
- [ ] Clicking "خريطة" switches to map/list split view
- [ ] Price bubbles appear on map for listings with coordinates
- [ ] Clicking a pin shows the mini popup card
- [ ] Closing the popup works
- [ ] Panning/zooming the map re-fetches pins and updates the bubbles
- [ ] Hovering a card highlights its pin
- [ ] Switching back to list mode shows the normal layout

---

## 8. Files Summary

| File | Action | Task |
|---|---|---|
| `backend/config/services.php` | Add google.maps_key | 1 |
| `backend/resources/views/partner/classifieds/index.blade.php` | Replace lat/lng inputs with map | 1 |
| `backend/resources/js/partner/classifieds.js` | Add initClassifiedMap() | 1 |
| `backend/app/Http/Resources/Customer/ClassifiedListingDetailResource.php` | Expose lat/lng in location | 2 |
| `backend/app/Http/Controllers/Customer/BrowseController.php` | Add classifiedMapPins() | 2 |
| `backend/routes/api_customer_v1.php` | Add GET /classified/map-pins route | 2 |
| `frontend/src/features/classified/classified-view/helpers/types.ts` | Add lat/lng to Location | 3 |
| `frontend/src/features/classified/classified-view/types.ts` | Add lat/lng to ClassifiedDetail | 3 |
| `frontend/src/features/classified/classified-view/helpers/to-classified-detail.ts` | Map lat/lng fields | 3 |
| `frontend/src/features/classified/classified-view/classified-location-map.tsx` | **NEW** — map pin component | 3 |
| `frontend/src/features/classified/classified-view/index.tsx` | Insert map pin component | 3 |
| `frontend/src/features/classified/classifiedList/api/get.ts` | Add MapPin type + getClassifiedMapPinsService | 4 |
| `frontend/src/features/classified/classifiedList/classified-map-view.tsx` | **NEW** — map view component | 4 |
| `frontend/src/features/classified/classifiedList/classified-page-client.tsx` | **NEW** — client wrapper with view toggle | 4 |
| `frontend/src/features/classified/classifiedList/index.tsx` | Integrate page client component | 4 |

**No database migrations needed** — lat/lng columns already exist.
