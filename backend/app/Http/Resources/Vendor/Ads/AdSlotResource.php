<?php

namespace App\Http\Resources\Vendor\Ads;

use App\Enums\PaidAdSlotTargetType;
use App\Services\Ads\AdSlotAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $spec = $this->creativeSpec();

        return [
            'id' => $this->id,
            'code' => $this->slot_code,
            'name' => [
                'en' => $this->name,
                'ar' => $this->name_ar,
            ],
            'surface' => $this->target_type === PaidAdSlotTargetType::PageBlock ? 'page_block' : 'placement',
            'target_type' => $this->target_type?->value,
            'placement_code' => $this->when(
                $this->target_type === PaidAdSlotTargetType::Placement,
                fn () => $this->placementDefinition?->code
            ),
            'page' => $this->when(
                $this->target_type === PaidAdSlotTargetType::PageBlock,
                fn () => $this->pageBlock?->page ? [
                    'id' => $this->pageBlock->page->id,
                    'name' => $this->pageBlock->page->name,
                ] : null
            ),
            'block_type' => $this->when(
                $this->target_type === PaidAdSlotTargetType::PageBlock,
                fn () => $this->pageBlock?->block_type
            ),
            'item_position' => $this->item_position,
            'pricing_model' => $this->pricing_model?->value,
            'base_rate' => $this->base_rate,
            'min_budget' => $this->min_budget,
            'currency' => $this->country?->currency_code,
            'min_units' => $this->min_booking_days,
            'max_units' => $this->max_booking_days,
            'unit_label' => match ($this->pricing_model?->value) {
                'fixed_daily' => 'day',
                'fixed_weekly' => 'week',
                'fixed_monthly' => 'month',
                'cpm' => 'per 1,000 impressions',
                'cpc' => 'per click',
                default => null,
            },
            'creative_spec' => [
                'desktop' => $spec['desktop'],
                'mobile' => $spec['mobile'],
                'max_kb' => $spec['max_kb'],
                'formats' => $spec['formats'],
            ],
            'lead_time_days' => $this->lead_time_days,
            'notes' => [
                'en' => $this->notes_for_vendors,
                'ar' => $this->notes_for_vendors_ar,
            ],
            'preview_image_url' => $this->previewImageUrl(),
            'next_available_date' => $this->when(
                $request->boolean('with_availability', true),
                fn () => app(AdSlotAvailabilityService::class)->nextAvailableDate($this->resource)
            ),
        ];
    }

    private function previewImageUrl(): string
    {
        $code = $this->target_type === PaidAdSlotTargetType::Placement
            ? $this->placementDefinition?->code
            : $this->pageBlock?->block_type;

        $file = $code ? "/images/ad-slots/{$code}.png" : null;

        if ($file && file_exists(public_path(ltrim($file, '/')))) {
            return asset($file);
        }

        return asset('images/ad-slots/generic.png');
    }
}
