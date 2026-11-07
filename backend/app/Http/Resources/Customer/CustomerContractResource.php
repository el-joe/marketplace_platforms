<?php

namespace App\Http\Resources\Customer;

use App\Models\CustomerContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer's accepted purchase contract. The list shape omits the frozen text; the detail shape (`detailed`) adds it.
 *
 * @mixin CustomerContract
 */
class CustomerContractResource extends JsonResource
{
    public bool $detailed = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $arabic = app()->getLocale() === 'ar';
        $country = $request->attributes->get('country');
        $category = $this->classifiedCategory;
        $contractCountry = $this->country;

        $data = [
            'id' => $this->id,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'name' => $this->template?->name,
            'template_version' => $this->template_version,
            'listing' => [
                'id' => $this->classified_listing_id,
                'number' => $this->listing_number,
                'title' => $this->listing_title,
                'slug' => $this->listing?->slug,
            ],
            'category' => $category ? ['id' => $category->id, 'name' => $arabic ? $category->name_ar : $category->name_en] : null,
            'country' => $contractCountry ? ['id' => $contractCountry->id, 'code' => $contractCountry->iso_code_2, 'name' => $arabic ? $contractCountry->name_ar : $contractCountry->name_en] : null,
            'signer_name' => $this->signer_name,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'language' => $this->language_signed,
            'content_mode' => $this->content_mode,
            'has_file' => $this->hasSignedFile(),
            'file_name' => $this->signed_file_name,
            'file_url' => $this->hasSignedFile() && $country
                ? route('customer.account.contracts.file', ['country' => $country->site_code, 'id' => $this->id])
                : null,
            'download_url' => $country
                ? route('customer.account.contracts.download', ['country' => $country->site_code, 'id' => $this->id])
                : null,
        ];

        if ($this->detailed) {
            $data += [
                'content' => $this->rendered_content,
                'content_hash' => $this->rendered_content_hash,
                'signed_ip' => $this->signed_ip,
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            ];
        }

        return $data;
    }
}
