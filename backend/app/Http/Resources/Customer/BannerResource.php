<?php

namespace App\Http\Resources\Customer;

use App\Services\BannerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var BannerService $bannerService */
        $bannerService = app(BannerService::class);

        $desktopFile = $bannerService->getDesktopImage($this->resource);
        $mobileFile = $bannerService->getMobileImage($this->resource);

        return [
            'id'              => $this->id,
            'title_en'        => $this->title_en,
            'title_ar'        => $this->title_ar,
            'subtitle_en'     => $this->subtitle_en,
            'subtitle_ar'     => $this->subtitle_ar,
            'cta_label_en'    => $this->cta_label_en,
            'cta_label_ar'    => $this->cta_label_ar,
            'cta_url'         => $this->cta_url,
            'link_type'       => $this->link_type?->value,
            'link_reference_id' => $this->link_reference_id,
            'desktop_image_url' => $desktopFile?->full_path,
            'mobile_image_url'  => $mobileFile?->full_path,
        ];
    }
}
