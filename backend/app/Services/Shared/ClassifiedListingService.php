<?php

namespace App\Services\Shared;

use App\Enums\ClassifiedListingStatus;
use App\Jobs\NotifyAdminNewClassifiedListingJob;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassifiedListingService
{
    /**
     * @param  Customer|Vendor  $owner
     */
    public function create(Customer|Vendor $owner, array $data): ClassifiedListing
    {
        /** @var ClassifiedCategory $category */
        $category = ClassifiedCategory::findOrFail($data['classified_category_id']);

        $this->validateCategoryRequirements($category, $data, partial: true);

        $status = $category->contract_template_id
            ? ClassifiedListingStatus::PendingContract
            : ClassifiedListingStatus::PendingReview;

        /** @var ClassifiedListing $listing */
        $listing = $owner->classifiedListings()->create([
            'listing_number'         => 'CL-' . strtoupper(Str::random(8)),
            'classified_category_id' => $category->id,
            'country_id'             => $data['country_id'],
            'city_id'                => $data['city_id'] ?? null,
            'listing_purpose'        => $data['listing_purpose'],
            'title_en'               => $data['title_en'],
            'title_ar'               => $data['title_ar'],
            'description_en'         => $data['description_en'] ?? null,
            'description_ar'         => $data['description_ar'] ?? null,
            'price'            => $data['price'],
            'currency'               => $data['currency'],
            'price_negotiable'       => $data['price_negotiable'] ?? false,
            'attributes'               => $data['attributes'] ?? null,
            'latitude'                 => $data['latitude'] ?? null,
            'longitude'               => $data['longitude'] ?? null,
            'vendor_listing_reference'   => $data['vendor_listing_reference'] ?? null,
            'marketer_promotion_enabled' => $data['marketer_promotion_enabled'] ?? false,
            'status'                     => $status,
        ]);

        $this->storeImages($listing, $data['images'] ?? []);
        $this->storeSketch($listing, $data['sketch_file'] ?? null);
        $this->storeAttachments($listing, $data['attachments'] ?? []);

        if ($status === ClassifiedListingStatus::PendingReview) {
            NotifyAdminNewClassifiedListingJob::dispatch($listing);
        }

        return $listing->fresh(['images', 'attachments', 'classifiedCategory']);
    }

    public function update(ClassifiedListing $listing, array $data): ClassifiedListing
    {
        $category = isset($data['classified_category_id'])
            ? ClassifiedCategory::findOrFail($data['classified_category_id'])
            : $listing->classifiedCategory;

        $this->validateCategoryRequirements($category, $data, partial: true);

        $listing->update(array_filter([
            'classified_category_id' => $data['classified_category_id'] ?? $listing->classified_category_id,
            'country_id'             => $data['country_id'] ?? $listing->country_id,
            'city_id'                => array_key_exists('city_id', $data) ? $data['city_id'] : $listing->city_id,
            'listing_purpose'        => $data['listing_purpose'] ?? $listing->listing_purpose,
            'title_en'               => $data['title_en'] ?? $listing->title_en,
            'title_ar'               => $data['title_ar'] ?? $listing->title_ar,
            'description_en'         => $data['description_en'] ?? $listing->description_en,
            'description_ar'         => $data['description_ar'] ?? $listing->description_ar,
            'price'            => $data['price'] ?? $listing->price,
            'currency'               => $data['currency'] ?? $listing->currency,
            'price_negotiable'       => $data['price_negotiable'] ?? $listing->price_negotiable,
            'attributes'             => $data['attributes'] ?? $listing->attributes,
            'latitude'               => array_key_exists('latitude', $data) ? $data['latitude'] : $listing->latitude,
            'longitude'              => array_key_exists('longitude', $data) ? $data['longitude'] : $listing->longitude,
        ], fn ($v) => $v !== null));

        if (!empty($data['images'])) {
            $listing->images()->delete();
            $this->storeImages($listing, $data['images']);
        }

        if (!empty($data['sketch_file'])) {
            $this->storeSketch($listing, $data['sketch_file']);
        }

        if (!empty($data['attachments'])) {
            $this->storeAttachments($listing, $data['attachments']);
        }

        return $listing->fresh(['images', 'attachments', 'classifiedCategory']);
    }

    public function acceptContract(ClassifiedListing $listing, string $signatureData): ClassifiedListing
    {
        $template = $listing->classifiedCategory?->contractTemplate;

        if (! $template) {
            throw ValidationException::withMessages([
                'contract' => 'No contract template is configured for this category.',
            ]);
        }

        $listing->update([
            'contract_template_id'    => $template->id,
            'contract_accepted_at'    => now(),
            'contract_signature_data' => $signatureData,
            'status'                  => 'pending_review',
        ]);

        NotifyAdminNewClassifiedListingJob::dispatch($listing->fresh());

        return $listing->fresh();
    }

    public function canEdit(ClassifiedListing $listing): bool
    {
        return in_array($listing->status, [ClassifiedListingStatus::Draft, ClassifiedListingStatus::Rejected], true);
    }

    public function pause(ClassifiedListing $listing): ClassifiedListing
    {
        $listing->update(['status' => 'paused']);

        return $listing->fresh();
    }

    public function resume(ClassifiedListing $listing): ClassifiedListing
    {
        $listing->update(['status' => 'active']);

        return $listing->fresh();
    }

    public function markSold(ClassifiedListing $listing): ClassifiedListing
    {
        $listing->update(['status' => 'sold']);

        return $listing->fresh();
    }

    public function delete(ClassifiedListing $listing): void
    {
        $listing->delete();
    }

    private function validateCategoryRequirements(ClassifiedCategory $category, array $data, bool $partial = false): void
    {
        $errors = [];

        if ($category->requires_location_map && ! $partial) {
            if (empty($data['latitude']) || empty($data['longitude'])) {
                $errors['latitude']  = 'Location coordinates are required for this category.';
                $errors['longitude'] = 'Location coordinates are required for this category.';
            }
        }

        if ($category->requires_sketch_upload && ! $partial) {
            if (empty($data['sketch_file'])) {
                $errors['sketch_file'] = 'A sketch file is required for this category.';
            }
        }

        $requiredTypes = $category->required_attachment_types ?? [];
        if (! empty($requiredTypes) && ! $partial) {
            $provided = array_keys($data['attachments'] ?? []);
            $missing  = array_diff($requiredTypes, $provided);
            foreach ($missing as $type) {
                $errors["attachments.{$type}"] = "Attachment type '{$type}' is required for this category.";
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function storeImages(ClassifiedListing $listing, array $images): void
    {
        foreach ($images as $index => $image) {
            /** @var UploadedFile $image */
            $path = $image->store('classified-images', 'public');
            $listing->images()->create([
                'file_path'  => $path,
                'position'   => $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    private function storeSketch(ClassifiedListing $listing, ?UploadedFile $sketch): void
    {
        if (! $sketch) {
            return;
        }

        $path = $sketch->store('classified-sketches', 'public');
        $listing->update(['sketch_file_path' => $path]);
    }

    private function storeAttachments(ClassifiedListing $listing, array $attachments): void
    {
        foreach ($attachments as $type => $file) {
            /** @var UploadedFile $file */
            $path = $file->store('classified-attachments', 'public');
            $listing->attachments()->create([
                'attachment_type' => $type,
                'file_path'       => $path,
                'status'          => 'pending',
            ]);
        }
    }
}
