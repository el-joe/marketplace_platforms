# Bookable Unit Photo Upload

**Date:** 2026-09-29  
**Status:** Ready to implement

## Problem

The create/edit forms for bookable units (`/bookable-units/create`, `/bookable-units/{id}/edit`) have no photo upload capability. Agencies cannot attach images to their units, and the unit detail/show page has no gallery display.

## Scope

- **DB:** New `bookable_unit_photos` table (uuid pk, bookable_unit_id, file_path, position, is_primary, created_at)
- **Model:** `BookableUnitPhoto` + `photos()` relationship on `BookableUnit`, `getPrimaryPhotoUrlAttribute()`
- **Controller:** `storePhotos` (POST) + `destroyPhoto` (DELETE) actions in `BookableUnitController`
- **Routes:** Two new named routes under the bookable-units prefix
- **Views:** Photo grid with upload input on `create.blade.php` and `edit.blade.php`; photo gallery in `show.blade.php`
- **Lang:** Add `photos`, `add_photos`, `photo_deleted`, `confirm_delete_photo_title`, `confirm_delete_photo_text` keys under `bookable_units`
- **Storage:** `public` disk, path `bookable-unit-photos/{unit_id}/{filename}`

## Sub-agent Prompts

### Agent 1 — Migration + Model

**Context:** We are adding photo upload to the `bookable_units` system in a Laravel 11 app. The pattern follows `ClassifiedListingImage` (uuid pk, no updated_at, `file_path`, `position`, `is_primary` boolean).

**Task:**

1. Create `/var/www/marketplace/backend/database/migrations/2026_09_29_200000_create_bookable_unit_photos_table.php`:
   - uuid primary key
   - `foreignUuid('bookable_unit_id')->constrained('bookable_units')->cascadeOnDelete()`
   - `string('file_path')`
   - `unsignedSmallInteger('position')->default(0)`
   - `boolean('is_primary')->default(false)`
   - `timestamp('created_at')->useCurrent()`
   - NO `updated_at`

2. Create `/var/www/marketplace/backend/app/Models/BookableUnitPhoto.php`:
   - `HasUuids`, no timestamps (`public $timestamps = false`, keep `const CREATED_AT = 'created_at'`)
   - fillable: `bookable_unit_id`, `file_path`, `position`, `is_primary`
   - casts: `is_primary => boolean`, `created_at => datetime`
   - `belongsTo(BookableUnit::class)`

3. Edit `/var/www/marketplace/backend/app/Models/BookableUnit.php`:
   - Add `use Illuminate\Support\Facades\Storage;`
   - Add `photos(): HasMany` relationship → `BookableUnitPhoto::class` ordered by `position`
   - Add `getPrimaryPhotoUrlAttribute(): ?string` — returns `Storage::url($photo->file_path)` of the first `is_primary` photo, falling back to first photo, or `null`

Run the migration: `cd /var/www/marketplace/backend && php artisan migrate`

---

### Agent 2 — Controller + Routes

**Context:** `BookableUnitController` is at `/var/www/marketplace/backend/app/Http/Controllers/TravelAgencyPortal/BookableUnitController.php`. It already has `storeTimeSlot` and `destroyTimeSlot` as examples. Routes are in `/var/www/marketplace/backend/routes/travel.php` under `Route::prefix('bookable-units')->name('bookable-units.')`.

**Task:**

1. Add two new methods to `BookableUnitController`:

```php
// ── Photos ─────────────────────────────────────────────────────────────

public function storePhotos(Request $request, BookableUnit $bookableUnit): RedirectResponse
{
    $this->authorise($bookableUnit);

    $request->validate([
        'photos'   => ['required', 'array', 'max:10'],
        'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
    ]);

    $position = $bookableUnit->photos()->max('position') ?? 0;

    foreach ($request->file('photos') as $file) {
        $path = $file->store("bookable-unit-photos/{$bookableUnit->id}", 'public');
        $position++;
        $bookableUnit->photos()->create([
            'file_path' => $path,
            'position'  => $position,
            'is_primary' => $bookableUnit->photos()->count() === 0,
        ]);
    }

    return back()->with('success', __('travel.bookable_units.photos_saved'));
}

public function destroyPhoto(BookableUnit $bookableUnit, BookableUnitPhoto $photo): \Illuminate\Http\JsonResponse
{
    $this->authorise($bookableUnit);
    abort_if($photo->bookable_unit_id !== $bookableUnit->id, 404);

    Storage::disk('public')->delete($photo->file_path);
    $photo->delete();

    // promote next photo to primary if needed
    if ($photo->is_primary) {
        $bookableUnit->photos()->orderBy('position')->first()?->update(['is_primary' => true]);
    }

    return response()->json(['message' => __('travel.bookable_units.photo_deleted')]);
}
```

2. Add required imports at the top of the controller:
   - `use App\Models\BookableUnitPhoto;`
   - `use Illuminate\Support\Facades\Storage;`

3. In `/var/www/marketplace/backend/routes/travel.php`, inside the `bookable-units` group add:
```php
Route::post('/{bookableUnit}/photos', [BookableUnitController::class, 'storePhotos'])->name('photos.store');
Route::delete('/{bookableUnit}/photos/{photo}', [BookableUnitController::class, 'destroyPhoto'])->name('photos.destroy');
```

---

### Agent 3 — Views + Lang

**Context:**
- Views are Blade, styled with Tailwind.
- Pattern for media deletion: packages use a JS modal (`media-delete-modal`) with fetch DELETE + page reload.
- Lang file: `/var/www/marketplace/backend/lang/en/travel.php` — add under `bookable_units` array.
- Arabic lang: `/var/www/marketplace/backend/lang/ar/travel.php` — same keys in Arabic.
- `create.blade.php` has no unit yet (no delete capability on create — photos upload after save on the edit page; on create just add the upload UI with a note).
- `edit.blade.php` shows existing unit `$unit`.
- `show.blade.php` displays unit detail.

**Task:**

1. **Lang EN** — add inside `bookable_units` array in `/var/www/marketplace/backend/lang/en/travel.php`:
```php
'photos' => 'Photos',
'add_photos' => 'Add photos',
'photos_saved' => 'Photos saved.',
'photo_deleted' => 'Photo deleted.',
'confirm_delete_photo_title' => 'Delete photo?',
'confirm_delete_photo_text' => 'This action cannot be undone.',
'no_photos' => 'No photos yet.',
```

2. **Lang AR** — same keys in Arabic in `/var/www/marketplace/backend/lang/ar/travel.php`.

3. **`create.blade.php`** — add a note section after the description field, before the submit button:
```html
<p class="text-xs text-gray-400">{{ __('travel.bookable_units.add_photos') }} — {{ __('common.available_after_save') }}</p>
```
If `common.available_after_save` doesn't exist, add it to both lang files as "Available after saving." / "متاح بعد الحفظ."; alternatively just inline the text.

4. **`edit.blade.php`** — add a full photo management section below the form (outside the `<form>` tag, before `@endsection`):

```html
{{-- ── Photos ──────────────────────────────────────────────────────────── --}}
<div class="bg-white p-6 rounded-xl border border-gray-100 space-y-4">
    <h3 class="font-bold text-gray-800">{{ __('travel.bookable_units.photos') }}</h3>

    {{-- existing photos grid --}}
    @if($unit->photos->count())
    <div class="flex flex-wrap gap-3" id="photos-grid">
        @foreach($unit->photos as $photo)
        <div class="relative group w-28 h-28">
            <img src="{{ Storage::url($photo->file_path) }}" class="w-full h-full object-cover rounded-lg">
            <button type="button"
                data-photo-delete-url="{{ route('travel-agency.bookable-units.photos.destroy', [$unit, $photo]) }}"
                class="photo-delete-btn absolute top-1 left-1 hidden group-hover:flex items-center justify-center bg-red-500 text-white rounded-full w-6 h-6 text-sm">×</button>
        </div>
        @endforeach
    </div>
    @else
    <p class="text-sm text-gray-400">{{ __('travel.bookable_units.no_photos') }}</p>
    @endif

    {{-- upload form --}}
    <form method="POST" action="{{ route('travel-agency.bookable-units.photos.store', $unit) }}" enctype="multipart/form-data" class="space-y-2">
        @csrf
        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"
               class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        @error('photos') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
        @error('photos.*') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-400">
            {{ __('travel.bookable_units.add_photos') }}
        </button>
    </form>
</div>

{{-- delete modal --}}
<div id="photo-delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
    <div class="bg-white rounded-xl p-6 max-w-sm w-full space-y-4">
        <h3 class="font-bold text-gray-800">{{ __('travel.bookable_units.confirm_delete_photo_title') }}</h3>
        <p class="text-sm text-gray-500">{{ __('travel.bookable_units.confirm_delete_photo_text') }}</p>
        <div class="flex justify-end gap-3">
            <button type="button" id="photo-delete-cancel" class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">{{ __('common.cancel') }}</button>
            <button type="button" id="photo-delete-confirm" class="px-4 py-2 text-sm rounded-lg bg-red-500 text-white hover:bg-red-600">{{ __('common.delete') }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('photo-delete-modal');
    let deleteUrl = null, targetEl = null;

    function openModal(el, url) { deleteUrl = url; targetEl = el; modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); deleteUrl = null; targetEl = null; }

    document.getElementById('photo-delete-cancel').addEventListener('click', closeModal);

    document.getElementById('photo-delete-confirm').addEventListener('click', function () {
        if (!deleteUrl) return;
        fetch(deleteUrl, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
        })
        .then(r => r.ok ? (targetEl?.closest('.relative.group')?.remove(), closeModal()) : Promise.reject())
        .catch(() => alert('Delete failed.'));
    });

    document.querySelectorAll('.photo-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn, btn.dataset.photoDeleteUrl); });
    });
})();
</script>
@endpush
```

5. **`show.blade.php`** — add a photo gallery section at the top of the content (after the unit name/type heading block), before the calendar section:

```html
{{-- ── Photos ─────────────────────────────────────────────────────── --}}
@if($unit->photos->count())
<div class="flex flex-wrap gap-3">
    @foreach($unit->photos as $photo)
    <img src="{{ Storage::url($photo->file_path) }}" class="w-32 h-32 object-cover rounded-xl border border-gray-100">
    @endforeach
</div>
@endif
```

Make sure the `$unit` is loaded with `photos` relation in the `show()` method of the controller (add `->load('photos')` or eager load it). Also load `photos` in `edit()`.

---

## Checklist

- [ ] Agent 1: migration + models
- [ ] Agent 2: controller + routes  
- [ ] Agent 3: views + lang
- [ ] Verify: visit `/bookable-units/create` → no broken page
- [ ] Verify: create a unit → edit page shows photo upload section
- [ ] Verify: upload 1-2 photos → they appear in the grid
- [ ] Verify: delete a photo via modal → it disappears
- [ ] Verify: show page displays photos
