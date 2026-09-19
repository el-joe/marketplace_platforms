@php $promoBadgeRows = collect($badges ?? [])->map(fn($b) => [
    'id' => $b->id, 'label_en' => $b->label_en, 'label_ar' => $b->label_ar,
    'icon_key' => $b->icon_key, 'color_hex' => $b->color_hex,
    'text_color_hex' => $b->text_color_hex, 'is_active' => (bool) $b->is_active,
])->values(); @endphp
@php
    $pbL = fn (string $k) => __('admin.promo_badges.' . $k);
    $pbLabels = array_merge([
        'title' => $pbL('title'), 'hint' => __('admin.promo_badges.hint', ['max' => config('promo_badges.max_per_owner', 10)]), 'add' => $pbL('add'),
        'label_en' => $pbL('label_en'), 'label_ar' => $pbL('label_ar'), 'icon' => $pbL('icon'),
        'active' => $pbL('active'), 'up' => $pbL('up'), 'remove' => $pbL('remove'), 'empty' => $pbL('empty'),
    ], $labels ?? []);
@endphp
<div
    x-data="{
        badges: @js($promoBadgeRows),
        add() { if (this.badges.length < {{ (int) config('promo_badges.max_per_owner', 10) }}) this.badges.push({ id: '', label_en: '', label_ar: '', icon_key: 'Truck', color_hex: '#1a1a2e', text_color_hex: '#FFFFFF', is_active: true }); },
    }"
    class="space-y-4"
>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">{{ $title ?? $pbLabels['title'] }}</h3>
                        <p class="text-xs text-gray-500">{{ $hint ?? $pbLabels['hint'] }}</p>
                    </div>
                    <button type="button" @click="add()" class="text-sm font-medium text-primary-600 hover:text-primary-700">{{ $pbLabels['add'] }}</button>
                </div>

                <datalist id="promo-badge-icons">
                    @foreach(config('promo_badges.icons') as $ic)
                    <option value="{{ $ic }}">
                    @endforeach
                </datalist>

                <p x-show="badges.length === 0" class="text-sm text-gray-400 italic">{{ $pbLabels['empty'] }}</p>

                <template x-for="(b, i) in badges" :key="i">
                    <div class="rounded-lg border border-gray-200 p-3 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                        <input type="hidden" :name="`promo_badges[${i}][id]`" :value="b.id">
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">{{ $pbLabels['label_en'] }}</label>
                            <input type="text" :name="`promo_badges[${i}][label_en]`" x-model="b.label_en" dir="ltr" maxlength="100" class="form-input text-sm py-1.5 w-full">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">{{ $pbLabels['label_ar'] }}</label>
                            <input type="text" :name="`promo_badges[${i}][label_ar]`" x-model="b.label_ar" dir="rtl" maxlength="100" class="form-input text-sm py-1.5 w-full">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ $pbLabels['icon'] }}</label>
                            <input type="text" list="promo-badge-icons" :name="`promo_badges[${i}][icon_key]`" x-model="b.icon_key" maxlength="50" class="form-input text-sm py-1.5 w-full">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="color" :name="`promo_badges[${i}][color_hex]`" x-model="b.color_hex" title="Icon colour" class="h-8 w-10 p-0 border rounded">
                            <input type="color" :name="`promo_badges[${i}][text_color_hex]`" x-model="b.text_color_hex" title="Text colour" class="h-8 w-10 p-0 border rounded">
                        </div>
                        <div class="md:col-span-6 flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="hidden" :name="`promo_badges[${i}][is_active]`" value="0">
                                <input type="checkbox" :name="`promo_badges[${i}][is_active]`" value="1" x-model="b.is_active"> {{ $pbLabels['active'] }}
                            </label>
                            <div class="flex gap-3 text-sm">
                                <button type="button" x-show="i > 0" @click="badges.splice(i-1, 2, badges[i], badges[i-1])" class="text-gray-500 hover:text-gray-800">↑ {{ $pbLabels['up'] }}</button>
                                <button type="button" @click="badges.splice(i, 1)" class="text-red-500 hover:text-red-700">{{ $pbLabels['remove'] }}</button>
                            </div>
                        </div>
                    </div>
                </template>
</div>
