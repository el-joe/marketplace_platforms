<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\SizeGuide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Vendor-owned size guides ("size maps"). A guide is reusable across the
 * vendor's listings and is shown to customers on made-to-measure products
 * (custom attributes switched on) next to the add-to-cart flow.
 */
class SizeGuideController extends Controller
{
    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    private function ownedOrFail(SizeGuide $sizeGuide): SizeGuide
    {
        abort_unless($sizeGuide->vendor_id === $this->vendorId(), 404);

        return $sizeGuide;
    }

    public function index(): View
    {
        $guides = SizeGuide::query()
            ->where('vendor_id', $this->vendorId())
            ->withCount(['rows', 'listings'])
            ->latest()
            ->get();

        return view('partner.size-guides.index', compact('guides'));
    }

    public function create(Request $request): View
    {
        $template = $request->boolean('template') ? SizeGuide::abayaTemplate() : null;

        return view('partner.size-guides.form', [
            'guide' => null,
            'initial' => $template
                ? ['name' => $template['title_en'], ...$template]
                : ['unit' => 'cm', 'columns' => [], 'rows' => []],
            'template' => SizeGuide::abayaTemplate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $guide = DB::transaction(function () use ($data, $request) {
            $guide = SizeGuide::create([
                ...$data['attributes'],
                'vendor_id' => $this->vendorId(),
                'image_path' => $request->file('image')?->store('size-guides', 'public'),
            ]);
            $guide->syncRows($data['rows']);

            return $guide;
        });

        return redirect()->route('partner.size-guides.edit', $guide)->with('success', __('partner.size_guides.saved'));
    }

    public function edit(SizeGuide $sizeGuide): View
    {
        $guide = $this->ownedOrFail($sizeGuide)->load('rows');

        return view('partner.size-guides.form', [
            'guide' => $guide,
            'initial' => [
                ...$guide->only(['name', 'title_en', 'title_ar', 'intro_en', 'intro_ar', 'how_to_measure_en', 'how_to_measure_ar', 'unit', 'columns']),
                'rows' => $guide->rows->map(fn ($row) => ['size_label' => $row->size_label, 'values' => $row->values ?? []])->all(),
            ],
            'template' => SizeGuide::abayaTemplate(),
        ]);
    }

    public function update(Request $request, SizeGuide $sizeGuide): RedirectResponse
    {
        $guide = $this->ownedOrFail($sizeGuide);
        $data = $this->validated($request);

        DB::transaction(function () use ($guide, $data, $request) {
            $attributes = $data['attributes'];
            if ($request->hasFile('image') || $request->boolean('remove_image')) {
                if ($guide->image_path) {
                    Storage::disk('public')->delete($guide->image_path);
                }
                $attributes['image_path'] = $request->file('image')?->store('size-guides', 'public');
            }
            $guide->update($attributes);
            $guide->syncRows($data['rows']);
        });

        return redirect()->route('partner.size-guides.edit', $guide)->with('success', __('partner.size_guides.saved'));
    }

    public function destroy(SizeGuide $sizeGuide): RedirectResponse
    {
        $guide = $this->ownedOrFail($sizeGuide);
        if ($guide->image_path) {
            Storage::disk('public')->delete($guide->image_path);
        }
        $guide->delete();

        return redirect()->route('partner.size-guides.index')->with('success', __('partner.size_guides.deleted'));
    }

    /**
     * The table editor posts `columns` and `rows` as JSON strings; they are
     * decoded, then validated and normalised (unique column keys, values kept
     * only for known columns).
     *
     * @return array{attributes: array<string, mixed>, rows: array<int, array{size_label: string, values: array<string, string>}>}
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'columns' => json_decode((string) $request->input('columns', '[]'), true) ?? [],
            'rows' => json_decode((string) $request->input('rows', '[]'), true) ?? [],
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'intro_en' => ['nullable', 'string', 'max:3000'],
            'intro_ar' => ['nullable', 'string', 'max:3000'],
            'how_to_measure_en' => ['nullable', 'string', 'max:5000'],
            'how_to_measure_ar' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', 'in:'.implode(',', SizeGuide::UNITS)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'columns' => ['required', 'array', 'min:1', 'max:12'],
            'columns.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'columns.*.label_en' => ['required', 'string', 'max:60'],
            'columns.*.label_ar' => ['nullable', 'string', 'max:60'],
            'columns.*.is_measurement' => ['boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:60'],
            'rows.*.size_label' => ['required', 'string', 'max:50', 'distinct'],
            'rows.*.values' => ['nullable', 'array'],
            'rows.*.values.*' => ['nullable', 'string', 'max:40'],
        ], [
            'columns.required' => __('partner.size_guides.errors.columns_required'),
            'rows.required' => __('partner.size_guides.errors.rows_required'),
            'rows.*.size_label.distinct' => __('partner.size_guides.errors.duplicate_size'),
        ]);

        $columns = array_map(fn (array $column) => [
            'key' => $column['key'],
            'label_en' => trim($column['label_en']),
            'label_ar' => trim((string) ($column['label_ar'] ?? '')),
            'is_measurement' => (bool) ($column['is_measurement'] ?? true),
        ], $validated['columns']);
        $keys = array_column($columns, 'key');

        $rows = array_map(fn (array $row) => [
            'size_label' => trim($row['size_label']),
            'values' => collect($row['values'] ?? [])
                ->only($keys)
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->all(),
        ], $validated['rows']);

        if (collect($rows)->every(fn ($row) => $row['values'] === [])) {
            throw ValidationException::withMessages(['rows' => __('partner.size_guides.errors.rows_empty')]);
        }

        return [
            'attributes' => [
                ...collect($validated)->only(['name', 'title_en', 'title_ar', 'intro_en', 'intro_ar', 'how_to_measure_en', 'how_to_measure_ar', 'unit'])->all(),
                'columns' => $columns,
            ],
            'rows' => $rows,
        ];
    }
}
