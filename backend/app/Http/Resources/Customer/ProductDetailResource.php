<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public bool $isWishlisted = false;

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
            'rating_avg'       => (float) ($this->relationLoaded('activeListings') ? ($this->activeListings->first()->rating_avg ?? 0) : 0),
            'rating_count'     => (int) ($this->relationLoaded('activeListings') ? ($this->activeListings->first()->rating_count ?? 0) : 0),
            'rating_breakdown' => $this->ratingBreakdown,
            'total_sold'       => (int) $this->total_sold,
            'brand'            => $this->whenLoaded('brand', fn() => [
                'id'   => $this->brand->id,
                'name' => Bilingual::pair($this->brand, 'name'),
                'slug' => $this->brand->slug,
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
            'specifications'   => $this->whenLoaded('specifications', fn() =>
                $this->specifications->map(fn($s) => [
                    'id'       => $s->id,
                    'key'      => Bilingual::pair($s, 'key'),
                    'value'    => Bilingual::pair($s, 'value'),
                    'position' => $s->position,
                ])
            ),
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
            'variants'         => $this->whenLoaded('variants', fn() =>
                $this->variants->filter(fn($v) => $v->is_active)->map(fn($v) => [
                    'id'           => $v->id,
                    'sku'          => $v->sku,
                    'variant_name' => $v->variant_name,
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
            'related'          => $this->whenLoaded('related', fn() =>
                ProductListResource::collection($this->related)->resolve()
            ),
            'is_wishlisted'    => $this->isWishlisted,
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
