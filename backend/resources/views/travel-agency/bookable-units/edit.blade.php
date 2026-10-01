@extends('layouts.travel-agency')

@section('title', __('travel.bookable_units.edit_unit'))

@section('content')
    <div class="max-w-xl space-y-5">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.bookable_units.edit_unit') }}</h1>

        <form method="POST" action="{{ route('travel-agency.bookable-units.update', $unit) }}" class="space-y-4 bg-white p-6 rounded-xl border border-gray-100">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.name') }} (EN)</label>
                <input type="text" name="name" value="{{ old('name', $unit->name) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.name') }} (AR)</label>
                <input type="text" name="name_ar" value="{{ old('name_ar', $unit->name_ar) }}" dir="rtl" class="w-full rounded-lg border-gray-300 text-sm">
                @error('name_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.type') }}</label>
                <select name="type" class="w-full rounded-lg border-gray-300 text-sm">
                    @foreach(['chalet', 'hotel_room', 'apartment', 'other'] as $type)
                        <option value="{{ $type }}" {{ old('type', $unit->type->value ?? $unit->type) === $type ? 'selected' : '' }}>
                            {{ __('travel.bookable_units.types.' . $type) }}
                        </option>
                    @endforeach
                </select>
                @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.capacity') }}</label>
                <input type="number" name="capacity" min="1" value="{{ old('capacity', $unit->capacity) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                @error('capacity') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.description') }}</label>
                <textarea name="description" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('description', $unit->description) }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.package_optional') }}</label>
                <select name="travel_package_id" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">— {{ __('travel.bookable_units.no_package') }} —</option>
                    @foreach($packages as $pkg)
                        <option value="{{ $pkg->id }}" {{ (old('travel_package_id', $unit->travel_package_id) === $pkg->id) ? 'selected' : '' }}>
                            {{ $pkg->title_ar ?: $pkg->title_en }}
                        </option>
                    @endforeach
                </select>
                @error('travel_package_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="px-5 py-2.5 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-400 transition-colors">
                {{ __('common.save') }}
            </button>
        </form>

        {{-- ── Photos ─────────────────────────────────────────────────────────── --}}
        <div class="bg-white p-6 rounded-xl border border-gray-100 space-y-4">
            <h3 class="font-bold text-gray-800">{{ __('travel.bookable_units.photos') }}</h3>

            @if($unit->photos->count())
                <div class="flex flex-wrap gap-3">
                    @foreach($unit->photos as $photo)
                        <div class="relative group w-28 h-28" data-photo-id="{{ $photo->id }}">
                            <img src="{{ Storage::url($photo->file_path) }}" class="w-full h-full object-cover rounded-lg {{ $photo->is_primary ? 'ring-2 ring-blue-500' : '' }}">
                            @if($photo->is_primary)
                                <span class="absolute bottom-1 left-1 bg-blue-500 text-white text-[10px] px-1.5 py-0.5 rounded font-medium">Cover</span>
                            @else
                                <button type="button"
                                    data-photo-primary-url="{{ route('travel-agency.bookable-units.photos.set-primary', [$unit, $photo]) }}"
                                    class="photo-primary-btn absolute bottom-1 left-1 hidden group-hover:flex items-center justify-center bg-white/90 text-blue-600 text-[10px] px-1.5 py-0.5 rounded font-medium border border-blue-200 hover:bg-blue-50">Cover</button>
                            @endif
                            <button type="button"
                                data-photo-delete-url="{{ route('travel-agency.bookable-units.photos.destroy', [$unit, $photo]) }}"
                                class="photo-delete-btn absolute top-1 right-1 hidden group-hover:flex items-center justify-center bg-red-500 text-white rounded-full w-6 h-6 text-sm leading-none">×</button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400">{{ __('travel.bookable_units.no_photos') }}</p>
            @endif

            <form method="POST" action="{{ route('travel-agency.bookable-units.photos.store', $unit) }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                @error('photos') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                @error('photos.*') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-400 transition-colors">
                    {{ __('travel.bookable_units.add_photos') }}
                </button>
            </form>
        </div>
    </div>

    {{-- ── Photo delete modal ──────────────────────────────────────────────────── --}}
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
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const modal = document.getElementById('photo-delete-modal');
        let deleteUrl = null, targetCard = null;

        function openModal(card, url) { deleteUrl = url; targetCard = card; modal.classList.remove('hidden'); modal.classList.add('flex'); }
        function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); deleteUrl = null; targetCard = null; }

        document.getElementById('photo-delete-cancel').addEventListener('click', closeModal);

        document.getElementById('photo-delete-confirm').addEventListener('click', function () {
            if (!deleteUrl) { return; }
            fetch(deleteUrl, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            })
            .then(function (r) {
                if (r.ok) { targetCard && targetCard.remove(); closeModal(); }
                else { return Promise.reject(); }
            })
            .catch(function () { alert('Delete failed.'); });
        });

        document.querySelectorAll('.photo-delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.closest('[data-photo-id]'), btn.dataset.photoDeleteUrl);
            });
        });

        document.querySelectorAll('.photo-primary-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                fetch(btn.dataset.photoPrimaryUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                })
                .then(function (r) {
                    if (!r.ok) { return Promise.reject(); }
                    // Update UI: remove ring + Cover badge from all, add to this one
                    document.querySelectorAll('[data-photo-id] img').forEach(function (img) {
                        img.classList.remove('ring-2', 'ring-blue-500');
                    });
                    document.querySelectorAll('[data-photo-id] span').forEach(function (s) { s.remove(); });
                    document.querySelectorAll('.photo-primary-btn').forEach(function (b) { b.classList.remove('hidden'); b.classList.add('hidden'); });

                    const card = btn.closest('[data-photo-id]');
                    card.querySelector('img').classList.add('ring-2', 'ring-blue-500');
                    btn.remove();
                    const badge = document.createElement('span');
                    badge.className = 'absolute bottom-1 left-1 bg-blue-500 text-white text-[10px] px-1.5 py-0.5 rounded font-medium';
                    badge.textContent = 'Cover';
                    card.appendChild(badge);
                })
                .catch(function () { alert('Failed to set cover photo.'); });
            });
        });
    }());
    </script>
    @endpush
@endsection
