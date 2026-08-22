<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PortalContentType;
use App\Http\Controllers\Controller;
use App\Models\PortalContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortalContentController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Index — list distinct pages with row counts
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('portal_content.view'), 403);

        $pages = PortalContent::selectRaw('page_key, COUNT(*) as fields_count, MAX(updated_at) as last_updated_at')
            ->groupBy('page_key')
            ->orderBy('page_key')
            ->get();

        return view('admin.portal-content.index', [
            'pages' => $pages,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Portal Content'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Page — all fields for one page, grouped by block
    // ─────────────────────────────────────────────────────────────────────────

    public function page(string $pageKey): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('portal_content.view'), 403);

        $rows = PortalContent::where('page_key', $pageKey)
            ->orderBy('block_key')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('block_key');

        return view('admin.portal-content.edit', [
            'pageKey' => $pageKey,
            'blocks' => $rows,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Portal Content', 'url' => route('admin.portal-content.index')],
                ['label' => $pageKey],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Save — bulk-update all fields for a page
    // ─────────────────────────────────────────────────────────────────────────

    public function save(Request $request, string $pageKey): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('portal_content.edit'), 403);

        $request->validate([
            'fields.*.value_file' => ['nullable', 'image', 'max:5120'],
        ]);

        $fields = $request->input('fields', []);
        $count = 0;

        foreach ($fields as $id => $data) {
            $row = PortalContent::where('page_key', $pageKey)->find($id);

            if (!$row) {
                continue;
            }

            $update = [
                'value_en' => $data['value_en'] ?? null,
                'value_ar' => $data['value_ar'] ?? null,
                'is_active' => isset($data['is_active']),
                'updated_by_admin_id' => $admin->id,
            ];

            if ($row->type === PortalContentType::Link) {
                $update['value_url'] = $data['value_url'] ?? null;
            }

            if ($row->type === PortalContentType::Image) {
                $uploaded = $request->file("fields.{$id}.value_file");

                if ($uploaded) {
                    $update['value_url'] = $uploaded->store('portal-content/' . $pageKey, 'public');
                }
            }

            $row->update($update);
            $count++;
        }

        PortalContent::flush($pageKey);

        return redirect()->route('admin.portal-content.page', $pageKey)
            ->with('success', "Portal content saved ({$count} fields updated).");
    }
}
