<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\AppConfigResource;
use App\Http\Responses\ApiResponse;
use App\Models\AppContextCountry;
use App\Models\Page;
use App\Services\Customer\PageRendererService;
use App\Support\SafeCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AppConfigController extends Controller
{
    public function __construct(
        private readonly PageRendererService $renderer,
    ) {
    }

    public function config(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'string'],
        ]);

        $countryId = $validated['country_id'];

        $data = SafeCache::remember("app_config_{$countryId}", 300, function () use ($countryId) {
            $contextCountries = AppContextCountry::query()
                ->where('country_id', $countryId)
                ->where('is_active', true)
                ->whereHas('appContext', fn ($q) => $q->where('is_active', true))
                ->with(['appContext'])
                ->get()
                ->sortBy(fn ($cc) => $cc->appContext->sort_order)
                ->values();

            $contexts = $contextCountries->map(function (AppContextCountry $cc) use ($countryId) {
                $context = $cc->appContext;

                $navItems = $context->navItems()
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->where('country_id', $countryId)->orWhereNull('country_id'))
                    ->get()
                    ->groupBy('position')
                    ->map(fn ($group) => $group->sortBy(fn ($item) => $item->country_id === null ? 1 : 0)->first())
                    ->sortBy('position')
                    ->values();

                return [
                    'key' => $context->key,
                    'name_en' => $context->name_en,
                    'name_ar' => $context->name_ar,
                    'icon_url' => $context->icon_path ? Storage::url($context->icon_path) : null,
                    'color_hex' => $context->color_hex,
                    'sort_order' => $context->sort_order,
                    'is_active' => $context->is_active,
                    'home_page_id' => $cc->home_page_id,
                    'bottom_nav' => $navItems->map(fn ($item) => [
                        'position' => $item->position,
                        'nav_type' => $item->nav_type,
                        'label_en' => $item->label_en,
                        'label_ar' => $item->label_ar,
                        'icon_name' => $item->icon_name,
                        'deep_link' => $item->deep_link,
                        'is_center_featured' => $item->is_center_featured,
                    ])->all(),
                ];
            })->all();

            return [
                'contexts' => $contexts,
                'default_context' => 'main',
            ];
        });

        return ApiResponse::success(new AppConfigResource($data));
    }

    public function homePage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'string'],
            'context_key' => ['required', 'string'],
        ]);

        $country = \App\Models\Country::findOrFail($validated['country_id']);

        $contextCountry = AppContextCountry::query()
            ->where('country_id', $country->id)
            ->where('is_active', true)
            ->whereHas('appContext', fn ($q) => $q->where('is_active', true)->where('key', $validated['context_key']))
            ->first();

        if (!$contextCountry || !$contextCountry->home_page_id) {
            return response()->json(['success' => false, 'message' => __('common.exceptions.app_config.home_page_not_found')], 404);
        }

        $page = Page::where('id', $contextCountry->home_page_id)
            ->where('status', 'published')
            ->first();

        if (!$page) {
            return response()->json(['success' => false, 'message' => __('common.exceptions.app_config.home_page_not_found')], 404);
        }

        $customer = $request->user('customer');
        $sessionId = $request->header('X-Session-Id') ?? $request->cookie('session_id') ?? session()->getId();

        $result = $this->renderer->renderPage($page, $country, $customer, (string) $sessionId);

        if (empty($result)) {
            return response()->json(['success' => false, 'message' => __('common.exceptions.app_config.home_page_not_found')], 404);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}
