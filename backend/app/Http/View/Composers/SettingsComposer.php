<?php

namespace App\Http\View\Composers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingsComposer
{
    public function compose(View $view): void
    {
        $settings = Cache::remember('content_settings', 300, function () {
            return Setting::where('is_public', true)
                ->get()
                ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->getRawValue()])
                ->toArray();
        });

        $view->with('siteSettings', $settings);
    }
}
