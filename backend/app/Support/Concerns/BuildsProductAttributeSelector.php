<?php

namespace App\Support\Concerns;

use Illuminate\Support\Collection;

/**
 * Groups a product's variants by attribute (e.g. Color, Size) into a selector shape:
 * for each value, whether it's part of the currently-selected variant's combo, whether
 * disabled (no variant/listing exists for that value combined with the other selected
 * attributes), and the variant/listing you'd land on if you picked it.
 */
trait BuildsProductAttributeSelector
{
    /**
     * @param Collection $variants Product variants, each with variantAttributes.attribute/attributeValue loaded.
     * @param mixed $selectedVariant The currently-viewed variant.
     * @param array<string, array{listing_id: mixed, listing_ref: mixed}> $listingsByVariant Best listing per variant id.
     */
    private function productAttributesShape(Collection $variants, $selectedVariant, array $listingsByVariant): array
    {
        $comboFor = fn($variant) => $variant->variantAttributes
            ->filter(fn($va) => $va->attribute_value_id)
            ->pluck('attribute_value_id', 'attribute_id')
            ->all();

        $variantCombos = $variants->mapWithKeys(fn($variant) => [$variant->id => $comboFor($variant)]);
        $selectedCombo = $comboFor($selectedVariant);

        return $variants
            ->flatMap(fn($variant) => $variant->variantAttributes)
            ->filter(fn($va) => $va->attribute !== null)
            ->groupBy('attribute_id')
            ->map(function ($group, $attributeId) use ($selectedCombo, $variantCombos, $listingsByVariant) {
                $attribute = $group->first()->attribute;

                return [
                    'attribute_id' => $attribute->id,
                    'name' => [
                        'ar' => $attribute->name_ar,
                        'en' => $attribute->name_en,
                    ],
                    'values' => $group
                        ->unique(fn($va) => $va->attribute_value_id ?? $va->value_text_en)
                        ->map(function ($va) use ($attributeId, $selectedCombo, $variantCombos, $listingsByVariant) {

                            $candidateCombo = $selectedCombo;
                            $candidateCombo[$attributeId] = $va->attribute_value_id;

                            $matchedVariantId = $variantCombos->search(fn($combo) => $combo == $candidateCombo);
                            $matchedVariantId = $matchedVariantId === false ? null : $matchedVariantId;
                            $listing = $matchedVariantId ? ($listingsByVariant[$matchedVariantId] ?? null) : null;

                            if($matchedVariantId && $listing) {
                                $url = route('customer.listing.show', [request()->attributes->get('country')->site_code, $matchedVariantId .'--' . $listing['listing_id']]);
                                $url_param = $matchedVariantId .'--' . $listing['listing_id'];
                            } else {
                                $url = null;
                                $url_param = null;
                            }
                            return [
                                'attribute_value_id' => $va->attributeValue?->id,
                                'slug' => $va->attributeValue?->slug,
                                'value' => [
                                    'ar' => $va->attributeValue?->value_ar ?? $va->value_text_ar,
                                    'en' => $va->attributeValue?->value_en ?? $va->value_text_en,
                                ],
                                'url' => $url,
                                'url_param' => $url_param,
                                'color_hex' => $va->attributeValue?->color_hex,
                                'selected' => ($selectedCombo[$attributeId] ?? null) === $va->attribute_value_id,
                                'disabled' => $listing === null,
                                'variant_id' => $matchedVariantId,
                                'listing_id' => $listing['listing_id'] ?? null,
                                'listing_ref' => $listing['listing_ref'] ?? null,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy(fn($group) => $group['name']['en'])
            ->values()
            ->all();
    }
}
