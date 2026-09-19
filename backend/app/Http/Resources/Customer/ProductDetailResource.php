<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public bool $isWishlisted = false;

    /**
     * Whether this product is currently part of an active, visible
     * mega_deals Page Builder block — computed by the controller via
     * PageBuilderService::isProductInActiveMegaDeal() (see
     * docs/plans/mega-deal-page-builder-correction.md Task F). Defaults to
     * false so callers that never set it (e.g. tests instantiating this
     * resource directly) still get a boolean, not null.
     */
    public bool $isMegaDeal = false;

    /**
     * Whether this product currently has a `live` FlashSaleSubmission —
     * computed by the controller via
     * FlashSaleService::activeFlashSaleEndsAtForProduct(). Takes precedence
     * over isMegaDeal: a product never reports both true (see
     * docs/plans/flash-sale-badge-and-countdown.md Task H).
     */
    public bool $isFlashSale = false;

    /** ISO 8601 end timestamp of the product's live flash sale, or null. */
    public ?string $flashSaleEndsAt = null;

    /** @var array<string, mixed>|null Pre-shaped banner/ad array from PlacementAdService::resolve(). */
    public ?array $banner = null;

    /** @var array<string, mixed>|null Pre-shaped sponsored listing from SponsoredProductService::forProductPage(). */
    public ?array $crossSellAd = null;

    public ?array $topBanner = null;

    public ?array $inlineBanner1 = null;

    public ?array $inlineBanner2 = null;

    /** @var array<string, mixed> */
    public array $enrichment = [];

    /** @var array<int, array<string, mixed>> */
    public array $productAttributes = [];

    /** @var array<int, array{stars: int, count: int, percentage: int}> */
    public array $ratingBreakdown = [];

    public function toArray(Request $request): array
    {
        $countrySetting = $this->whenLoaded('countrySettings', function () {
            return $this->countrySettings->first();
        });

        $name = [
            'ar' => $countrySetting?->name_override_ar ?? $this->name_ar,
            'en' => $countrySetting?->name_override_en ?? $this->name_en,
        ];

        $extras = [];
        if (! empty($this->enrichment['best_seller_badge'] ?? null)) {
            $extras['best_seller_badge'] = $this->enrichment['best_seller_badge'];
        }
        if (! empty($this->enrichment['delivery_options'] ?? null)) {
            $extras['delivery_options'] = $this->enrichment['delivery_options'];
        }
        if (! empty($this->enrichment['coupons'] ?? null)) {
            $extras['coupons'] = $this->enrichment['coupons'];
        }
        if (! empty($this->enrichment['payment_options'] ?? null)) {
            $extras['payment_options'] = $this->enrichment['payment_options'];
        }

        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'name'             => $name,
            'description'      => Bilingual::pair($this->resource, 'description'),
            'short_description' => Bilingual::pairFromKeys($this->resource, 'short_desc_ar', 'short_desc_en'),
            'model_number'     => $this->model_number,
            'gtin'             => $this->gtin,
            'is_age_restricted' => $this->is_age_restricted,
            'min_age'          => $this->min_age,
            'is_hazardous'     => $this->is_hazardous,
            'has_variants'     => $this->has_variants,
            'is_mega_deal'     => $this->isMegaDeal,
            'is_flash_sale'    => $this->isFlashSale,
            'flash_sale_ends_at' => $this->flashSaleEndsAt,
            'promo_badges'     => $this->whenLoaded('promoBadges', fn() =>
                $this->promoBadges->map(fn($b) => [
                    'id'             => $b->id,
                    'label'          => [
                        'ar' => $b->label_ar,
                        'en' => $b->label_en,
                    ],
                    'icon_key'       => $b->icon_key,
                    'color_hex'      => $b->color_hex,
                    'text_color_hex' => $b->text_color_hex,
                    'sort_order'     => $b->sort_order,
                ])
            ),
            'rating_avg'       => (float) ($this->relationLoaded('activeListings') ? ($this->activeListings->first()->rating_avg ?? 0) : 0),
            'rating_count'     => (int) ($this->relationLoaded('activeListings') ? ($this->activeListings->first()->rating_count ?? 0) : 0),
            'rating_breakdown' => $this->ratingBreakdown,
            'total_sold'       => (int) $this->total_sold,
            'brand'            => $this->whenLoaded('brand', fn() => [
                'id'       => $this->brand->id,
                'name'     => Bilingual::pair($this->brand, 'name'),
                'slug'     => $this->brand->slug,
                'logo_url' => $this->brand->logo_url,
            ]),
            'category'         => $this->whenLoaded('category', fn() => [
                'id'   => $this->category->id,
                'name' => Bilingual::pair($this->category, 'name'),
                'slug' => $this->category->slug,
            ]),
            'breadcrumbs'      => $this->whenLoaded('category', fn() => $this->buildBreadcrumbs()),
            'highlights'       => $this->whenLoaded('highlights', fn() =>
                $this->highlights->map(fn($h) => [
                    'id'       => $h->id,
                    'text'     => Bilingual::pair($h, 'text'),
                    'position' => $h->position,
                ])
            ),
            'specifications'   => $this->whenLoaded('specifications', function () {
                $locale = app()->getLocale();

                return $this->specifications->map(fn($s) => [
                    'id'       => $s->id,
                    'label'    => Bilingual::pair($s, 'key'),
                    'value'    => Bilingual::pair($s, 'value'),
                    'position' => $s->position,
                ]);
            }),
            'images'           => $this->whenLoaded('images', fn() =>
                $this->images->map(fn($img) => [
                    'id'             => $img->id,
                    'url'            => \Storage::disk($img->disk)->url($img->path),
                    'alt'            => Bilingual::pairFromKeys($img, 'alt_text_ar', 'alt_text_en'),
                    'is_primary'     => $img->is_primary,
                    'position'       => $img->position,
                    'variant_id'     => $img->product_variant_id,
                ])
            ),
            'product_attributes' => $this->productAttributes,
            'has_custom_attributes' => (bool) $this->has_custom_attributes,
            'size_guide_image' => $this->has_custom_attributes && $this->size_guide_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->size_guide_image) : null,
            'custom_attributes' => $this->has_custom_attributes
                ? $this->whenLoaded('customAttributes', fn () => $this->customAttributes->map(fn ($a) => [
                    'id'          => $a->id,
                    'label'       => $a->label,
                    'unit'        => $a->unit,
                    'type'        => $a->type ?: 'text',
                    'options'     => $a->options ?? [],
                    'size_guide_image' => $this->size_guide_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->size_guide_image) : null,
                    'is_required' => (bool) $a->is_required,
                    'sort_order'  => $a->sort_order,
                ]), [])
                : [],
            'variants'         => $this->whenLoaded('variants', fn() =>
                $this->variants->filter(fn($v) => $v->is_active)->map(fn($v) => [
                    'id'           => $v->id,
                    'sku'          => $v->sku,
                    'variant_name' => trim(collect([$name['en'] ?? null, $v->variant_name ?: null])->filter()->implode(' ')),
                    'variant_name_ar' => trim(collect([$name['ar'] ?? null, $v->variant_name_ar ?: $v->variant_name ?: null])->filter()->implode(' ')),
                    'is_default'   => $v->is_default,
                    'position'     => $v->position,
                    'images'       => $v->relationLoaded('images')
                        ? $v->images->map(fn ($img) => [
                            'id'         => $img->id,
                            'url'        => $img->url,
                            'alt'        => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                            'is_primary' => (bool) $img->is_primary,
                            'position'   => (int) $img->position,
                        ])->values()->all()
                        : [],
                    'attributes'   => $v->variantAttributes->map(fn($va) => [
                        'attribute_id'   => $va->attribute_id,
                        'attribute_code' => $va->attribute?->code,
                        'attribute_name' => $va->attribute ? Bilingual::pair($va->attribute, 'name') : ['ar' => null, 'en' => null],
                        'value_id'       => $va->attribute_value_id,
                        'value'          => [
                            'ar' => $va->attributeValue?->value_ar ?? $va->value_text_ar ?? $va->value_number,
                            'en' => $va->attributeValue?->value_en ?? $va->value_text_en ?? $va->value_number,
                        ],
                        'color_hex'      => $va->attributeValue?->code_hex,
                        'swatch_image_url' => $va->attributeValue?->swatch_image_url,
                    ]),
                ])->values()
            ),
            'sellers'          => $this->whenLoaded('activeListings', fn() =>
                SellerListingResource::collection($this->activeListings)->resolve()
            ),
            'offers_by_variant' => $this->whenLoaded('activeListings', fn() =>
                $this->activeListings
                    ->groupBy('product_variant_id')
                    ->map(fn($listings) => [
                        'offers_count' => $listings->count(),
                        'offers'       => SellerListingResource::collection($listings->values())->resolve(),
                    ])
            ),
            'reviews'          => $this->whenLoaded('topReviews', fn() =>
                ReviewResource::collection($this->topReviews)->resolve()
            ),
            'related'          => $this->whenLoaded('related', fn() => $this->related),
            'is_wishlisted'    => $this->isWishlisted,
            'banner'           => $this->banner,
            'cross_sell_ad'    => $this->crossSellAd,
            'top_banner'       => $this->topBanner,
            'inline_banner_1'  => $this->inlineBanner1,
            'inline_banner_2'  => $this->inlineBanner2,
            'seo'              => [
                'title'       => [
                    'ar' => $countrySetting?->seo_title ?? $this->seo_title_ar,
                    'en' => $countrySetting?->seo_title ?? $this->seo_title_en,
                ],
                'description' => Bilingual::pairFromKeys($this->resource, 'seo_description_ar', 'seo_description_en'),
            ],
        ] + $extras;
    }

    private function buildBreadcrumbs(): array
    {
        $crumbs = [];

        foreach ($this->category->ancestors()->get() as $ancestor) {
            $crumbs[] = [
                'id'   => $ancestor->id,
                'name' => Bilingual::pair($ancestor, 'name'),
                'slug' => $ancestor->slug,
            ];
        }

        $crumbs[] = [
            'id'   => $this->category->id,
            'name' => Bilingual::pair($this->category, 'name'),
            'slug' => $this->category->slug,
        ];

        return $crumbs;
    }
}
