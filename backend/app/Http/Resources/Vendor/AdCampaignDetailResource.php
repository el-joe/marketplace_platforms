<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdCampaignDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'type'                => $this->type?->value,
            'status'              => $this->status?->value,
            'targeting_type'      => $this->targeting_type?->value,
            'budget_total'        => (float) $this->budget_total,
            'budget_daily'        => $this->budget_daily !== null ? (float) $this->budget_daily : null,
            'budget_spent_total'  => (float) $this->budget_spent_total,
            'budget_spent_today'  => (float) $this->budget_spent_today,
            'bid'                 => (float) $this->bid,
            'quality_score'       => $this->quality_score !== null ? (float) $this->quality_score : null,
            'starts_at'           => $this->starts_at?->toISOString(),
            'ends_at'             => $this->ends_at?->toISOString(),
            'approved_at'         => $this->approved_at?->toISOString(),
            'rejection_reason'    => $this->rejection_reason,
            'country_id'          => $this->country_id,

            // Targeting data — only return relevant section
            'keywords' => $this->when(
                $this->relationLoaded('keywords') && in_array($this->targeting_type, ['keyword', 'mixed']),
                fn () => $this->keywords->where('is_active', true)->map(fn ($kw) => [
                    'id'           => $kw->id,
                    'keyword'      => $kw->keyword,
                    'match_type'   => $kw->match_type?->value,
                    'bid_override' => $kw->bid_override !== null ? (float) $kw->bid_override : null,
                    'is_negative'  => (bool) $kw->is_negative,
                ])->values()
            ),
            'category_targets' => $this->when(
                $this->relationLoaded('categoryTargets') && in_array($this->targeting_type, ['category', 'mixed']),
                fn () => $this->categoryTargets->where('is_active', true)->map(fn ($ct) => [
                    'id'           => $ct->id,
                    'category_id'  => $ct->category_id,
                    'category_name'=> $ct->category?->name_ar ?? $ct->category?->name_en,
                    'bid_override' => $ct->bid_override !== null ? (float) $ct->bid_override : null,
                ])->values()
            ),

            // Products in campaign
            'products' => $this->when(
                $this->relationLoaded('products'),
                fn () => $this->products->where('is_active', true)->map(fn ($p) => [
                    'id'                  => $p->id,
                    'vendor_listing_id'   => $p->vendor_listing_id,
                    'product_variant_id'  => $p->product_variant_id,
                    'listing_name'        => $p->vendorListing?->product?->name_ar
                                            ?? $p->vendorListing?->product?->name_en
                                            ?? null,
                    'listing_sku'         => $p->vendorListing?->sku,
                    'listing_price'       => $p->vendorListing?->price !== null
                                            ? (float) $p->vendorListing->price : null,
                    'listing_image'       => $p->vendorListing?->primaryImage?->url ?? null,
                ])->values()
            ),

            // Quality score breakdown
            'quality_score_detail' => $this->when(
                $this->relationLoaded('qualityScore') && $this->qualityScore !== null,
                fn () => [
                    'score'           => (float) $this->qualityScore->score,
                    'ctr_score'       => (float) $this->qualityScore->ctr_score,
                    'relevance_score' => (float) $this->qualityScore->relevance_score,
                    'landing_score'   => (float) $this->qualityScore->landing_score,
                    'vendor_score'    => (float) $this->qualityScore->vendor_score,
                    'calculated_at'   => $this->qualityScore->calculated_at?->toISOString(),
                ]
            ),
        ];
    }
}
