<?php

namespace App\Models;

use Database\Factories\SizeGuideFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A vendor-owned "size map" page: a bilingual measurement table (one row per
 * size, one column per measurement) plus intro text, how-to-measure steps
 * and an optional diagram. Reusable across the vendor's listings.
 *
 * @property array<int, array{key: string, label_en: string, label_ar: string, is_measurement: bool}> $columns
 */
class SizeGuide extends Model
{
    /** @use HasFactory<SizeGuideFactory> */
    use HasFactory;

    use HasUuids;

    public const UNITS = ['cm', 'in'];

    protected $fillable = [
        'vendor_id',
        'name',
        'title_en',
        'title_ar',
        'intro_en',
        'intro_ar',
        'how_to_measure_en',
        'how_to_measure_ar',
        'unit',
        'columns',
        'image_path',
    ];

    protected $casts = [
        'columns' => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SizeGuideRow::class)->orderBy('sort_order');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(VendorListing::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * Replaces every row with the given list, preserving submitted order.
     *
     * @param  array<int, array{size_label: string, values: array<string, string|null>}>  $rows
     */
    public function syncRows(array $rows): void
    {
        $this->rows()->delete();
        foreach (array_values($rows) as $position => $row) {
            $this->rows()->create([
                'size_label' => $row['size_label'],
                'values' => $row['values'] ?? [],
                'sort_order' => $position,
            ]);
        }
    }

    /**
     * Customer-facing payload shared by the PDP and the standalone size guide page.
     *
     * @return array<string, mixed>
     */
    public function toCustomerArray(): array
    {
        return [
            'id' => $this->id,
            'title' => ['en' => $this->title_en ?: $this->name, 'ar' => $this->title_ar ?: $this->title_en ?: $this->name],
            'intro' => ['en' => $this->intro_en, 'ar' => $this->intro_ar ?: $this->intro_en],
            'how_to_measure' => ['en' => $this->how_to_measure_en, 'ar' => $this->how_to_measure_ar ?: $this->how_to_measure_en],
            'unit' => $this->unit,
            'image_url' => $this->imageUrl(),
            'columns' => collect($this->columns)->map(fn (array $column) => [
                'key' => $column['key'],
                'label' => ['en' => $column['label_en'], 'ar' => $column['label_ar'] ?: $column['label_en']],
                'is_measurement' => (bool) ($column['is_measurement'] ?? true),
            ])->values()->all(),
            'rows' => $this->rows->map(fn (SizeGuideRow $row) => [
                'size' => $row->size_label,
                'values' => (object) ($row->values ?? []),
            ])->values()->all(),
        ];
    }

    /**
     * Starter template for abayas (sizes are the abaya length in inches, the
     * Gulf convention). Values are editable defaults, not a standard.
     *
     * @return array{title_en: string, title_ar: string, intro_en: string, intro_ar: string, how_to_measure_en: string, how_to_measure_ar: string, unit: string, columns: array<int, array<string, mixed>>, rows: array<int, array{size_label: string, values: array<string, string>}>}
     */
    public static function abayaTemplate(): array
    {
        $rows = [
            ['50', '127', '145-150', '96', '56'],
            ['52', '132', '150-155', '100', '57'],
            ['54', '137', '155-160', '104', '58'],
            ['56', '142', '160-165', '108', '59'],
            ['58', '147', '165-170', '112', '60'],
            ['60', '152', '170-175', '116', '61'],
            ['62', '157', '175-180', '120', '62'],
        ];

        return [
            'title_en' => 'Abaya size guide',
            'title_ar' => 'دليل مقاسات العبايات',
            'intro_en' => 'Abaya sizes refer to the total length in inches, measured from the top of the shoulder to the hem. Pick the size that matches your height, or enter your own measurements when ordering.',
            'intro_ar' => 'مقاس العباية هو الطول الكامل بالإنش من أعلى الكتف حتى نهاية العباية. اختاري المقاس المناسب لطولك، أو أدخلي مقاساتك الخاصة عند الطلب.',
            'how_to_measure_en' => "Length: from the highest point of the shoulder straight down to where you want the hem, while wearing your usual shoes.\nBust: around the fullest part of the chest, keeping the tape level.\nSleeve: from the shoulder seam down to the wrist with the arm relaxed.",
            'how_to_measure_ar' => "الطول: من أعلى نقطة في الكتف مباشرة حتى المكان المطلوب لنهاية العباية مع ارتداء الحذاء المعتاد.\nالصدر: حول أعرض جزء من الصدر مع إبقاء الشريط مستويًا.\nالكم: من خط الكتف حتى الرسغ مع إرخاء الذراع.",
            'unit' => 'cm',
            'columns' => [
                ['key' => 'length', 'label_en' => 'Abaya length', 'label_ar' => 'طول العباية', 'is_measurement' => true],
                ['key' => 'height', 'label_en' => 'Your height', 'label_ar' => 'طولك', 'is_measurement' => true],
                ['key' => 'bust', 'label_en' => 'Bust', 'label_ar' => 'الصدر', 'is_measurement' => true],
                ['key' => 'sleeve', 'label_en' => 'Sleeve', 'label_ar' => 'الكم', 'is_measurement' => true],
            ],
            'rows' => array_map(fn (array $r) => [
                'size_label' => $r[0],
                'values' => ['length' => $r[1], 'height' => $r[2], 'bust' => $r[3], 'sleeve' => $r[4]],
            ], $rows),
        ];
    }
}
