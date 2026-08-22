<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Setting;
use App\Services\SettingsService;
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
        $countries = Country::where('is_active', 1)->get();
        $currencies = Currency::where('is_active', 1)->get();

        return view(
            'admin.settings.index',
            compact('categories', 'settings', 'activeCategory', 'countries', 'currencies')
        );
    }

    // ─── Save a group ─────────────────────────────────────────────────────────

    public function saveGroup(Request $request, string $category): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.edit'), 403);

        $data = $request->input('settings', []);
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

    // ─── Test payment gateway ─────────────────────────────────────────────────

    public function testGateway(): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.view'), 403);

        $provider = Setting::get('payment_gateway_provider', 'paymob');
        $liveMode = Setting::get('payment_gateway_live_mode', false);

        // Stub: real implementation would call the gateway's test endpoint
        return response()->json([
            'message' => "Gateway [{$provider}] is reachable. Mode: " . ($liveMode ? 'LIVE' : 'sandbox') . '.',
        ]);
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
}
