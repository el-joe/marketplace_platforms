<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Support\PortalPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.view'), 403);

        $categories = Setting::select('category')->distinct()->orderBy('category')->pluck('category')
            ->map(fn($category) => $category instanceof \App\Enums\SettingCategory ? $category->value : $category);
        $activeCategory = request('tab', 'general');
        $settings = Setting::where('category', $activeCategory)->orderBy('key')->get();
        $currencies = Currency::where('is_active', 1)->get();

        return view(
            'admin.settings.index',
            compact('categories', 'settings', 'activeCategory', 'currencies')
        );
    }

    // ─── Save a group ─────────────────────────────────────────────────────────

    public function saveGroup(Request $request, string $category): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.edit'), 403);

        $data = $request->input('settings', []);

        // The /join page option toggles are portal configuration: changing them needs
        // portal_content.manage on top of settings.edit (unchanged values never trigger the check).
        if ($this->changesJoinOptions($data) && ! $admin->can(PortalPermissions::MANAGE)) {
            return response()->json(['message' => __('admin.portal_permissions.join_options_forbidden')], 403);
        }

        $errors = $this->settingsService->validateGroup($category, $data);

        if (!empty($errors)) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $errors], 422);
        }

        $count = $this->settingsService->saveGroup($category, $data, $admin);

        return response()->json([
            'message' => ucfirst($category) . " settings saved ({$count} updated).",
            'count' => $count,
        ]);
    }

    // ─── Get group (AJAX) ─────────────────────────────────────────────────────

    public function getGroup(string $category): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.view'), 403);

        $settings = Setting::where('category', $category)
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn($s) => [$s->key => $s->getDisplayValue()]);

        return response()->json(['settings' => $settings]);
    }

    // ─── Reset a key to seeder default ───────────────────────────────────────

    public function reset(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.edit'), 403);
        abort_unless($admin->hasRole('super_admin'), 403);

        $key = $request->input('key');
        if (!$key) {
            return response()->json(['message' => 'Key is required.'], 422);
        }

        $setting = Setting::where('key', $key)->firstOrFail();

        // Re-run just this key from the seeder defaults (not possible without duplicating defaults).
        // Instead, we clear the cache so the next read gets fresh DB value.
        Cache::forget('setting:' . $key);
        Cache::forget('settings:' . $setting->category->value);

        return response()->json(['message' => "Setting [{$key}] cache cleared."]);
    }

    // ─── Clear application cache ──────────────────────────────────────────────

    public function clearCache(): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.edit'), 403);

        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return response()->json(['message' => 'Application cache cleared successfully.']);
    }

    /**
     * Whether the submitted settings flip any `join_option_*` visibility toggle.
     *
     * @param  array<string, mixed>  $data
     */
    private function changesJoinOptions(array $data): bool
    {
        $keys = array_filter(array_keys($data), fn ($key) => str_starts_with((string) $key, 'join_option_'));

        if ($keys === []) {
            return false;
        }

        foreach (Setting::whereIn('key', $keys)->get() as $setting) {
            $submitted = filter_var($data[$setting->key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($submitted !== null && $submitted !== (bool) $setting->value) {
                return true;
            }
        }

        return false;
    }
}
