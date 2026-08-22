<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppBottomNavItem;
use App\Models\AppContext;
use App\Models\AppContextCountry;
use App\Models\Country;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppContextController extends Controller
{
    public function index(): View
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermissionTo('app_contexts.view'), 403);

        $contexts = AppContext::orderBy('sort_order')->get();

        return view('admin.app-contexts.index', compact('contexts'));
    }

    public function show(Request $request, AppContext $context): View
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermissionTo('app_contexts.view'), 403);

        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'flag_emoji']);

        $assignments = AppContextCountry::where('app_context_id', $context->id)
            ->with('homePage:id,name')
            ->get()
            ->keyBy('country_id');

        $pages = Page::orderBy('name')->get(['id', 'name', 'page_type', 'country_id']);

        $navItems = AppBottomNavItem::where('app_context_id', $context->id)
            ->orderBy('country_id')
            ->orderBy('position')
            ->get()
            ->groupBy(fn ($item) => $item->country_id ?? 'default')
            ->collect();

        $overrideCountryId = $request->query('override_country');
        if ($overrideCountryId && $countries->contains('id', $overrideCountryId) && !$navItems->has($overrideCountryId)) {
            $navItems->put($overrideCountryId, collect());
        }

        return view('admin.app-contexts.show', compact('context', 'countries', 'assignments', 'pages', 'navItems'));
    }

    public function update(Request $request, AppContext $context): JsonResponse
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermissionTo('app_contexts.manage'), 403);

        $data = $request->validate([
            'name_en' => 'required|string|max:100',
            'name_ar' => 'required|string|max:100',
            'color_hex' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'icon' => 'nullable|image|max:2048',
        ]);

        unset($data['icon']);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('icon')) {
            if ($context->icon_path) {
                Storage::disk('public')->delete($context->icon_path);
            }
            $data['icon_path'] = $request->file('icon')->store('app-contexts', 'public');
        }

        $context->update($data);

        return response()->json(['success' => true, 'message' => __('admin.app_contexts.context_updated')]);
    }

    public function saveCountryAssignment(Request $request, AppContext $context): JsonResponse
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermissionTo('app_contexts.manage'), 403);

        $data = $request->validate([
            'country_id' => 'required|uuid|exists:countries,id',
            'home_page_id' => 'nullable|uuid|exists:pages,id',
            'is_active' => 'nullable|boolean',
        ]);

        $assignment = AppContextCountry::updateOrCreate(
            ['app_context_id' => $context->id, 'country_id' => $data['country_id']],
            [
                'home_page_id' => $data['home_page_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]
        );

        return response()->json(['success' => true, 'data' => $assignment, 'message' => __('admin.app_contexts.assignment_saved')]);
    }

    public function saveNavItem(Request $request, AppContext $context): JsonResponse
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermissionTo('app_contexts.manage'), 403);

        $data = $request->validate([
            'country_id' => 'nullable|uuid|exists:countries,id',
            'position' => 'required|integer|min:1|max:5',
            'nav_type' => 'required|in:home,categories,featured,account,cart,custom',
            'label_en' => 'required|string|max:50',
            'label_ar' => 'required|string|max:50',
            'icon_name' => 'required|string|max:50',
            'deep_link' => 'nullable|string|max:200',
            'is_center_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['is_center_featured'] = $request->boolean('is_center_featured');
        $data['sort_order'] = $data['sort_order'] ?? $data['position'];
        $data['country_id'] = $data['country_id'] ?? null;

        $item = AppBottomNavItem::updateOrCreate(
            [
                'app_context_id' => $context->id,
                'country_id' => $data['country_id'],
                'position' => $data['position'],
            ],
            collect($data)->except(['country_id', 'position'])->toArray() + ['is_active' => true]
        );

        return response()->json(['success' => true, 'data' => $item, 'message' => __('admin.app_contexts.nav_item_saved')]);
    }

    public function updateNavItem(Request $request, AppContext $context, AppBottomNavItem $item): JsonResponse
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermissionTo('app_contexts.manage'), 403);

        abort_unless($item->app_context_id === $context->id, 404);

        $data = $request->validate([
            'nav_type' => 'sometimes|in:home,categories,featured,account,cart,custom',
            'label_en' => 'sometimes|string|max:50',
            'label_ar' => 'sometimes|string|max:50',
            'icon_name' => 'sometimes|string|max:50',
            'deep_link' => 'nullable|string|max:200',
            'is_center_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->has('is_center_featured')) {
            $data['is_center_featured'] = $request->boolean('is_center_featured');
        }
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $item->update($data);

        return response()->json(['success' => true, 'data' => $item, 'message' => __('admin.app_contexts.nav_item_updated')]);
    }

    private function admin()
    {
        return auth('admin')->user();
    }
}
