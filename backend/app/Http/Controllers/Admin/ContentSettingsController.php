<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ContentSettingsController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Index — all settings, grouped by group then section
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.content'), 403);

        $settings = ContentSetting::orderBy('group')->orderBy('sort_order')->get();
        $groups = $settings->groupBy('group')->map(fn($group) => $group->groupBy('section'));

        return view('admin.content-settings.index', [
            'groups' => $groups,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.content_settings')],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show group — settings for a single group
    // ─────────────────────────────────────────────────────────────────────────

    public function showGroup(string $group): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.content'), 403);

        $settings = ContentSetting::where('group', $group)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section');

        return view('admin.content-settings.group', [
            'group' => $group,
            'sections' => $settings,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.content_settings'), 'url' => route('admin.content-settings.index')],
                ['label' => ucfirst(str_replace('_', ' ', $group))],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update — bulk-save settings (with optional file uploads)
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.content'), 403);

        $input = $request->input('settings', []);
        $keys = array_keys($input);
        $settings = ContentSetting::whereIn('key', $keys)->get()->keyBy('key');

        $rules = [];
        foreach ($settings as $key => $setting) {
            $rules["settings.{$key}"] = $this->validationRulesFor($setting);
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $settings, $input, $admin) {
            foreach ($settings as $key => $setting) {
                $value = $input[$key]['value'] ?? null;

                if ($setting->type === 'file') {
                    $file = $request->file("settings.{$key}.value");

                    if ($file) {
                        if ($setting->value) {
                            Storage::disk('public')->delete($setting->value);
                        }

                        $path = "settings/{$setting->group}/{$key}." . $file->getClientOriginalExtension();
                        Storage::disk('public')->putFileAs(
                            dirname($path),
                            $file,
                            basename($path)
                        );

                        $setting->value = $path;
                    }
                } elseif ($setting->type === 'boolean') {
                    $setting->value = $value ? '1' : '0';
                } else {
                    $setting->value = $value;
                }

                $setting->updated_by_admin_id = $admin->id;
                $setting->save();
            }
        });

        ContentSetting::flush();
        Cache::forget('content_settings');

        return redirect()->back()->with('success', __('admin.content_settings.saved_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function validationRulesFor(ContentSetting $setting): array
    {
        $rules = ['nullable'];

        $rules[] = match ($setting->type) {
            'file' => 'file',
            'boolean' => 'in:0,1',
            'email' => 'email',
            'url' => 'url',
            'color' => 'string',
            'select' => 'string',
            default => 'string',
        };

        if ($setting->type === 'file' && $setting->allowed_extensions) {
            $rules[] = 'mimes:' . $setting->allowed_extensions;
        }

        if ($setting->type === 'file' && $setting->max_size_kb) {
            $rules[] = 'max:' . $setting->max_size_kb;
        }

        return $rules;
    }
}
