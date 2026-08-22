<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementBar;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AnnouncementBarController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('announcement_bars.view'), 403);

        $bars = AnnouncementBar::with('country')
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get();

        $countries = Country::orderBy('name_en')->where('is_launched', true)->get(['id', 'name_en', 'flag_emoji']);

        return view('admin.announcement-bars.index', [
            'bars' => $bars,
            'countries' => $countries,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.announcement_bars')],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('announcement_bars.create'), 403);

        $data = $request->validate([
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:150'],
            'image_url' => ['required', 'string', 'max:1000'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $bar = AnnouncementBar::create([
            'country_id' => $data['country_id'] ?? null,
            'name' => $data['name'],
            'image_url' => $data['image_url'],
            'cta_url' => $data['cta_url'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'created_by_admin_id' => $admin->id,
        ]);

        $this->bustCache($bar->country_id);

        return response()->json([
            'success' => true,
            'message' => __('admin.announcement_bars.created_flash'),
            'bar' => $bar,
        ]);
    }

    public function update(Request $request, AnnouncementBar $announcementBar): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('announcement_bars.edit'), 403);

        $data = $request->validate([
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:150'],
            'image_url' => ['required', 'string', 'max:1000'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $previousCountryId = $announcementBar->country_id;

        $announcementBar->update([
            'country_id' => $data['country_id'] ?? null,
            'name' => $data['name'],
            'image_url' => $data['image_url'],
            'cta_url' => $data['cta_url'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'priority' => (int) ($data['priority'] ?? $announcementBar->priority),
            'is_active' => $request->boolean('is_active'),
            'updated_by_admin_id' => $admin->id,
        ]);

        $this->bustCache($previousCountryId);
        $this->bustCache($announcementBar->country_id);

        return response()->json([
            'success' => true,
            'message' => __('admin.announcement_bars.updated_flash'),
            'bar' => $announcementBar->fresh(),
        ]);
    }

    public function destroy(AnnouncementBar $announcementBar): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('announcement_bars.delete'), 403);

        $countryId = $announcementBar->country_id;
        $announcementBar->delete();

        $this->bustCache($countryId);

        return response()->json(['success' => true, 'message' => __('admin.announcement_bars.deleted_flash')]);
    }

    public function toggle(AnnouncementBar $announcementBar): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('announcement_bars.edit'), 403);

        $announcementBar->update([
            'is_active' => !$announcementBar->is_active,
            'updated_by_admin_id' => $admin->id,
        ]);

        $this->bustCache($announcementBar->country_id);

        return response()->json(['success' => true, 'is_active' => $announcementBar->is_active]);
    }

    /**
     * Bust the customer app_config cache for the affected country. When country_id is
     * null (targets all countries), every country's cached config is invalidated since
     * a global bar affects all of them.
     */
    private function bustCache(?string $countryId): void
    {
        if ($countryId !== null) {
            Cache::forget("app_config_{$countryId}");
            Cache::forget("announcement_bar_active_{$countryId}");

            return;
        }

        Country::pluck('id')->each(function ($id) {
            Cache::forget("app_config_{$id}");
            Cache::forget("announcement_bar_active_{$id}");
        });
    }
}
