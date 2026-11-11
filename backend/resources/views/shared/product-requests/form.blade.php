{{--
    "Request a new product" form, shared by the partner and marketer panels.
    Expects: $panel ('partner'|'marketer'), $categories (id => label), $brands, $countries,
             $defaultCountryId (?string), $searchedText (string, from ?q= on the listing search CTA).
    Plain multipart POST; server validation (StoreProductRequestSubmission) is authoritative.
--}}
@php
    $routePrefix = $panel . '.product-requests.';
    $maxImages = 6;
    $minDescription = 30;
    $inputClass = 'w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40';
    $errorInputClass = 'border-red-300 bg-red-50/30';
    $imageErrors = collect($errors->get('images'))
        ->merge(collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'images.'))->flatten())
        ->unique()
        ->values();
@endphp

<div class="max-w-4xl"
    x-data="productRequestForm({
        maxImages: {{ $maxImages }},
        maxBytes: {{ 5 * 1024 * 1024 }},
        minDescription: {{ $minDescription }},
        description: @js(old('description', '')),
        messages: {
            maxReached: @js(__('product_requests.panel.images_max_reached', ['max' => $maxImages])),
            invalidType: @js(__('product_requests.panel.images_invalid_type')),
            tooLarge: @js(__('product_requests.panel.images_too_large')),
            charsNeeded: @js(__('product_requests.panel.chars_needed', ['count' => '__COUNT__'])),
            counter: @js(__('product_requests.panel.images_counter', ['count' => '__COUNT__', 'max' => $maxImages])),
        },
    })">

    <div class="mb-4">
        <a href="{{ route($routePrefix . 'index') }}" class="text-sm text-gray-500 hover:text-gray-700 inline-flex items-center gap-1">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ __('product_requests.panel.back_to_list') }}
        </a>
    </div>

    <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4 mb-5 flex items-start gap-3">
        <svg class="w-5 h-5 text-yellow-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="text-sm text-yellow-900">
            <p>{{ __('product_requests.panel.create_intro') }}</p>
            <p class="text-xs text-yellow-700 mt-1">{{ __('product_requests.panel.required_note') }}</p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc ps-5 space-y-0.5">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route($routePrefix . 'store') }}" enctype="multipart/form-data" class="space-y-5"
        @submit="submitting = true">
        @csrf

        {{-- Basic information --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800">{{ __('product_requests.panel.sections.basic') }}</h3>
            <p class="text-xs text-gray-500 mt-1 mb-4">{{ __('product_requests.panel.sections.basic_hint') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="pr-name-en" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.name_en') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="pr-name-en" name="name_en" dir="ltr" required maxlength="255"
                        value="{{ old('name_en', $searchedText) }}"
                        @class([$inputClass, $errorInputClass => $errors->has('name_en')])>
                    @error('name_en') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pr-name-ar" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.name_ar') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="pr-name-ar" name="name_ar" dir="rtl" required maxlength="255"
                        value="{{ old('name_ar') }}"
                        @class([$inputClass, $errorInputClass => $errors->has('name_ar')])>
                    @error('name_ar') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Classification --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('product_requests.panel.sections.classification') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="pr-category" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.category') }} <span class="text-red-500">*</span>
                    </label>
                    <select id="pr-category" name="category_id" data-select2-init
                        @class([$inputClass, $errorInputClass => $errors->has('category_id')])>
                        <option value="">{{ __('product_requests.panel.select_category') }}</option>
                        @foreach($categories as $categoryId => $categoryLabel)
                            <option value="{{ $categoryId }}" @selected(old('category_id') === $categoryId)>{{ $categoryLabel }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pr-brand" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.brand') }} <span class="text-xs text-gray-400 font-normal">({{ __('product_requests.panel.optional') }})</span>
                    </label>
                    <select id="pr-brand" name="brand_id" data-select2-init
                        @class([$inputClass, $errorInputClass => $errors->has('brand_id')])>
                        <option value="">{{ __('product_requests.panel.select_brand') }}</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(old('brand_id') === $brand->id)>
                                {{ app()->getLocale() === 'ar' ? ($brand->name_ar ?: $brand->name_en) : ($brand->name_en ?: $brand->name_ar) }}
                            </option>
                        @endforeach
                    </select>
                    @error('brand_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pr-brand-name" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.brand_name') }}
                    </label>
                    <input type="text" id="pr-brand-name" name="brand_name" maxlength="120" value="{{ old('brand_name') }}"
                        @class([$inputClass, $errorInputClass => $errors->has('brand_name')])>
                    <p class="text-xs text-gray-400 mt-1">{{ __('product_requests.hints.brand_name') }}</p>
                    @error('brand_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Identification --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800">{{ __('product_requests.panel.sections.identification') }}</h3>
            <p class="text-xs text-gray-500 mt-1 mb-4">{{ __('product_requests.panel.sections.identification_hint') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="pr-gtin" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.gtin') }} <span class="text-xs text-gray-400 font-normal">({{ __('product_requests.panel.optional') }})</span>
                    </label>
                    <input type="text" id="pr-gtin" name="gtin" dir="ltr" inputmode="numeric" maxlength="14" pattern="\d{8}|\d{12}|\d{13}|\d{14}"
                        value="{{ old('gtin') }}"
                        @class([$inputClass . ' font-mono', $errorInputClass => $errors->has('gtin')])>
                    <p class="text-xs text-gray-400 mt-1">{{ __('product_requests.hints.gtin') }}</p>
                    @error('gtin') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pr-model" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.model_number') }} <span class="text-xs text-gray-400 font-normal">({{ __('product_requests.panel.optional') }})</span>
                    </label>
                    <input type="text" id="pr-model" name="model_number" dir="ltr" maxlength="100" value="{{ old('model_number') }}"
                        @class([$inputClass, $errorInputClass => $errors->has('model_number')])>
                    @error('model_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Details --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">{{ __('product_requests.panel.sections.details') }}</h3>
            <div>
                <label for="pr-description" class="block text-sm font-medium text-gray-700 mb-1.5">
                    {{ __('product_requests.fields.description') }} <span class="text-red-500">*</span>
                </label>
                <textarea id="pr-description" name="description" rows="5" required minlength="{{ $minDescription }}" maxlength="5000"
                    x-model="description"
                    @class([$inputClass . ' resize-y', $errorInputClass => $errors->has('description')])>{{ old('description') }}</textarea>
                <div class="flex flex-wrap items-start justify-between gap-2 mt-1">
                    <p class="text-xs text-gray-400">{{ __('product_requests.hints.description') }}</p>
                    <p class="text-xs whitespace-nowrap" :class="descriptionLength >= minDescription ? 'text-green-600' : 'text-gray-400'">
                        <span x-text="descriptionLength >= minDescription ? @js(__('product_requests.panel.chars_ok')) : messages.charsNeeded.replace('__COUNT__', minDescription - descriptionLength)"></span>
                        · <span dir="ltr" x-text="descriptionLength + ' / 5000'"></span>
                    </p>
                </div>
                @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pr-variants" class="block text-sm font-medium text-gray-700 mb-1.5">
                    {{ __('product_requests.fields.variants_note') }} <span class="text-xs text-gray-400 font-normal">({{ __('product_requests.panel.optional') }})</span>
                </label>
                <textarea id="pr-variants" name="variants_note" rows="2" maxlength="1000"
                    @class([$inputClass . ' resize-y', $errorInputClass => $errors->has('variants_note')])>{{ old('variants_note') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">{{ __('product_requests.hints.variants_note') }}</p>
                @error('variants_note') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="pr-reference" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.reference_url') }} <span class="text-xs text-gray-400 font-normal">({{ __('product_requests.panel.optional') }})</span>
                    </label>
                    <input type="url" id="pr-reference" name="reference_url" dir="ltr" maxlength="500" placeholder="https://"
                        value="{{ old('reference_url') }}"
                        @class([$inputClass, $errorInputClass => $errors->has('reference_url')])>
                    <p class="text-xs text-gray-400 mt-1">{{ __('product_requests.hints.reference_url') }}</p>
                    @error('reference_url') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pr-notes" class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('product_requests.fields.notes') }} <span class="text-xs text-gray-400 font-normal">({{ __('product_requests.panel.optional') }})</span>
                    </label>
                    <textarea id="pr-notes" name="notes" rows="2" maxlength="1000"
                        @class([$inputClass . ' resize-y', $errorInputClass => $errors->has('notes')])>{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Target market --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('product_requests.panel.sections.market') }}</h3>
            <div class="md:w-1/2">
                <label for="pr-country" class="block text-sm font-medium text-gray-700 mb-1.5">
                    {{ __('product_requests.fields.country') }} <span class="text-xs text-gray-400 font-normal">({{ __('product_requests.panel.optional') }})</span>
                </label>
                @php $selectedCountryId = old('country_id', $countries->contains('id', $defaultCountryId) ? $defaultCountryId : null); @endphp
                <select id="pr-country" name="country_id"
                    @class([$inputClass, $errorInputClass => $errors->has('country_id')])>
                    <option value="">{{ __('product_requests.panel.any_country') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" @selected($selectedCountryId === $country->id)>
                            {{ app()->getLocale() === 'ar' ? ($country->name_ar ?: $country->name_en) : ($country->name_en ?: $country->name_ar) }}
                        </option>
                    @endforeach
                </select>
                @error('country_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Images --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-1">
                <h3 class="font-semibold text-gray-800">
                    {{ __('product_requests.fields.images') }} <span class="text-red-500">*</span>
                </h3>
                <span class="text-xs text-gray-500" x-text="messages.counter.replace('__COUNT__', images.length)"></span>
            </div>
            <p class="text-xs text-gray-500 mb-4">{{ __('product_requests.hints.images') }}</p>

            @if($errors->any())
                <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800" x-show="images.length === 0">
                    {{ __('product_requests.panel.images_reselect') }}
                </div>
            @endif

            {{-- The named input carries the files on submit; it is kept in sync with the preview list. --}}
            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="hidden" x-ref="imagesInput">
            <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="hidden" x-ref="picker" @change="addFiles($event)">

            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
                <template x-for="(image, index) in images" :key="image.key">
                    <div class="relative aspect-square rounded-xl border border-gray-200 bg-gray-50 overflow-hidden group">
                        <img :src="image.url" alt="" class="w-full h-full object-cover">
                        <span x-show="index === 0"
                            class="absolute bottom-1 start-1 bg-gray-900/70 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded">{{ __('product_requests.panel.main_image') }}</span>
                        <button type="button" @click="removeImage(index)"
                            class="absolute top-1 end-1 w-6 h-6 rounded-full bg-white/90 text-gray-600 hover:text-red-600 shadow flex items-center justify-center"
                            title="{{ __('product_requests.panel.remove_image') }}" aria-label="{{ __('product_requests.panel.remove_image') }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </template>

                <button type="button" x-show="images.length < maxImages" @click="$refs.picker.click()"
                    @class([
                        'aspect-square rounded-xl border-2 border-dashed flex flex-col items-center justify-center gap-1 text-xs transition-colors',
                        'border-red-300 text-red-500 hover:border-red-400' => $imageErrors->isNotEmpty(),
                        'border-gray-300 text-gray-500 hover:border-yellow-400 hover:text-yellow-600' => $imageErrors->isEmpty(),
                    ])>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('product_requests.panel.add_images') }}
                </button>
            </div>

            <p x-show="imageError" x-cloak x-text="imageError" class="text-xs text-red-600 mt-2"></p>
            @foreach($imageErrors as $message)
                <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
            @endforeach
        </section>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route($routePrefix . 'index') }}"
                class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors">
                {{ __('product_requests.panel.cancel') }}
            </a>
            <button type="submit" :disabled="submitting"
                class="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-300 disabled:opacity-60 disabled:cursor-wait text-gray-900 text-sm font-semibold px-6 py-2.5 rounded-xl transition-colors">
                <span x-show="!submitting">{{ __('product_requests.panel.submit') }}</span>
                <span x-show="submitting" x-cloak>{{ __('product_requests.panel.submitting') }}</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
    <script>
        function productRequestForm(config) {
            return {
                ...config,
                images: [],
                imageError: '',
                submitting: false,
                nextKey: 0,
                get descriptionLength() {
                    return (this.description || '').trim().length;
                },
                addFiles(event) {
                    this.imageError = '';
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    for (const file of Array.from(event.target.files || [])) {
                        if (this.images.length >= this.maxImages) {
                            this.imageError = this.messages.maxReached;
                            break;
                        }
                        if (!allowedTypes.includes(file.type)) {
                            this.imageError = this.messages.invalidType;
                            continue;
                        }
                        if (file.size > this.maxBytes) {
                            this.imageError = this.messages.tooLarge;
                            continue;
                        }
                        this.images.push({ key: this.nextKey++, file, url: URL.createObjectURL(file) });
                    }
                    event.target.value = '';
                    this.syncInput();
                },
                removeImage(index) {
                    const [removed] = this.images.splice(index, 1);
                    if (removed) {
                        URL.revokeObjectURL(removed.url);
                    }
                    this.imageError = '';
                    this.syncInput();
                },
                syncInput() {
                    const transfer = new DataTransfer();
                    this.images.forEach((image) => transfer.items.add(image.file));
                    this.$refs.imagesInput.files = transfer.files;
                },
            };
        }
    </script>
@endpush
