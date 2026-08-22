<div id="nav-item-modal" class="modal fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
        <h3 class="font-semibold text-gray-900 mb-4">{{ __('admin.app_contexts.edit_nav_item') }}</h3>
        <form id="nav-item-form" class="space-y-4">
            <input type="hidden" name="item_id">
            <input type="hidden" name="country_id">
            <input type="hidden" name="position">

            <div>
                <label class="label">{{ __('admin.app_contexts.nav_type') }}</label>
                <select name="nav_type" class="input" required>
                    <option value="home">home</option>
                    <option value="categories">categories</option>
                    <option value="featured">featured</option>
                    <option value="account">account</option>
                    <option value="cart">cart</option>
                    <option value="custom">custom</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">{{ __('admin.app_contexts.name_english') }}</label>
                    <input type="text" name="label_en" class="input" dir="ltr" maxlength="50" required>
                </div>
                <div>
                    <label class="label">{{ __('admin.app_contexts.name_arabic') }}</label>
                    <input type="text" name="label_ar" class="input font-arabic" dir="rtl" maxlength="50" required>
                </div>
            </div>

            <div>
                <label class="label">{{ __('admin.app_contexts.icon_name') }}</label>
                <input type="text" name="icon_name" class="input" maxlength="50" required>
            </div>

            <div class="js-deep-link-wrap hidden">
                <label class="label">{{ __('admin.app_contexts.deep_link') }}</label>
                <input type="text" name="deep_link" class="input" placeholder="nawy://travel" maxlength="200">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_center_featured" id="is_center_featured" value="1" class="rounded border-gray-300 text-primary-600">
                <label for="is_center_featured" class="text-sm text-gray-700">{{ __('admin.app_contexts.center_featured') }}</label>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn btn-primary">{{ __('admin.app_contexts.save') }}</button>
                <button type="button" class="btn btn-secondary js-close-modal" data-modal="nav-item-modal">{{ __('admin.app_contexts.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
