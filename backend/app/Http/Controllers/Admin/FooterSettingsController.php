<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FooterSettingsController extends Controller
{
    private const GROUPS = ['social', 'bottom_nav', 'app_store', 'payment_method'];

    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.content'), 403);

        $links = FooterLink::query()->ordered()->get()->groupBy('group');

        return view('admin.footer-settings.index', [
            'links' => $links,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.footer_settings')],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.content'), 403);

        $validated = $this->validateLink($request);
        $validated['is_active'] = $request->boolean('is_active');

        $maxSort = FooterLink::where('group', $validated['group'])->max('sort_order');
        $validated['sort_order'] = ((int) $maxSort) + 1;

        FooterLink::create($validated);

        return redirect()->route('admin.footer-settings.index')->with('success', __('admin.footer_settings.saved_success'));
    }

    public function update(Request $request, FooterLink $footerLink): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.content'), 403);

        $validated = $this->validateLink($request);
        $validated['is_active'] = $request->boolean('is_active');

        $footerLink->update($validated);

        return redirect()->route('admin.footer-settings.index')->with('success', __('admin.footer_settings.saved_success'));
    }

    public function destroy(FooterLink $footerLink): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.content'), 403);

        $footerLink->delete();

        return redirect()->route('admin.footer-settings.index')->with('success', __('admin.footer_settings.deleted_success'));
    }

    public function toggleActive(FooterLink $footerLink): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.content'), 403);

        $footerLink->update(['is_active' => !$footerLink->is_active]);

        return response()->json(['success' => true, 'is_active' => (bool) $footerLink->fresh()->is_active]);
    }

    private function validateLink(Request $request): array
    {
        return $request->validate([
            'group' => 'required|in:' . implode(',', self::GROUPS),
            'platform' => 'nullable|string|max:50',
            'label_en' => 'nullable|string|max:255',
            'label_ar' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:2048',
            'icon_path' => 'nullable|string|max:2048',
            'sort_order' => 'nullable|integer',
        ]);
    }
}
