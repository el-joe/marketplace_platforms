<?php

namespace App\Services\Catalog;

use App\Enums\AdminStatus;
use App\Enums\ProductRequestStatus;
use App\Models\Admin;
use App\Models\MarketerAdmin;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRequest;
use App\Models\VendorAdmin;
use App\Notifications\Admin\NewProductRequestSubmitted;
use App\Notifications\ProductRequestApproved;
use App\Notifications\ProductRequestRejected;
use App\Services\ActivityLoggerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lifecycle of vendor/marketer requests for new catalog products.
 *
 * Approval is not a standalone action: the admin completes the full product form
 * and Admin\ProductController::store() calls markApproved() inside its transaction.
 */
class ProductRequestService
{
    /** Permission an admin needs to be notified about / act on new requests. */
    public const REVIEW_PERMISSION = 'product_requests.review';

    /** Admin sidebar badge cache key (see NavigationService). */
    public const NAV_BADGE_CACHE_KEY = 'nav.badge.pending_product_requests';

    public function __construct(private readonly ActivityLoggerService $activityLogger) {}

    /**
     * Create a pending request for the submitting team member's vendor or marketer.
     *
     * @param  array{name_en: string, name_ar: string, category_id: string, brand_id?: ?string, brand_name?: ?string, gtin?: ?string, model_number?: ?string, description: string, variants_note?: ?string, reference_url?: ?string, notes?: ?string, country_id?: ?string}  $data
     * @param  array<int, UploadedFile>  $uploadedImages
     */
    public function submit(array $data, VendorAdmin|MarketerAdmin $user, array $uploadedImages): ProductRequest
    {
        $productRequest = new ProductRequest;
        $productRequest->id = (string) Str::uuid();

        $storedPaths = [];
        foreach ($uploadedImages as $image) {
            $storedPaths[] = $image->store(ProductRequest::IMAGE_DIRECTORY.'/'.$productRequest->id, 'public');
        }

        try {
            DB::transaction(function () use ($productRequest, $data, $user, $storedPaths): void {
                $isVendor = $user instanceof VendorAdmin;

                $productRequest->fill([
                    'requester_type' => $isVendor ? ProductRequest::REQUESTER_VENDOR : ProductRequest::REQUESTER_MARKETER,
                    'vendor_id' => $isVendor ? $user->vendor_id : null,
                    'marketer_id' => $isVendor ? null : $user->marketer_id,
                    'country_id' => $data['country_id'] ?? null,
                    'name_en' => trim($data['name_en']),
                    'name_ar' => trim($data['name_ar']),
                    'category_id' => $data['category_id'],
                    'brand_id' => $data['brand_id'] ?? null,
                    'brand_name' => empty($data['brand_id']) ? ($data['brand_name'] ?? null) : null,
                    'gtin' => ($data['gtin'] ?? null) ?: null,
                    'model_number' => $data['model_number'] ?? null,
                    'description' => $data['description'],
                    'variants_note' => $data['variants_note'] ?? null,
                    'reference_url' => $data['reference_url'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'images' => $storedPaths,
                    'status' => ProductRequestStatus::Pending,
                ]);
                $productRequest->requestedBy()->associate($user);
                $productRequest->save();

                DB::afterCommit(fn () => $this->notifyReviewers($productRequest));
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($storedPaths);

            throw $e;
        }

        $this->forgetNavBadge();

        $this->activityLogger->log(
            description: "Product request submitted: {$productRequest->request_number}",
            subject: $productRequest,
            causer: $user,
            properties: ['request_number' => $productRequest->request_number, 'name_en' => $productRequest->name_en],
            logName: 'product_requests',
            event: 'product_request_submitted',
        );

        return $productRequest;
    }

    /**
     * Withdraw a pending request. Only a member of the owning vendor/marketer team may cancel.
     *
     * @throws ValidationException when the request is not pending or not owned by the user
     */
    public function cancel(ProductRequest $productRequest, VendorAdmin|MarketerAdmin $user): ProductRequest
    {
        $ownsRequest = $user instanceof VendorAdmin
            ? $productRequest->vendor_id !== null && $productRequest->vendor_id === $user->vendor_id
            : $productRequest->marketer_id !== null && $productRequest->marketer_id === $user->marketer_id;

        if (! $ownsRequest) {
            throw ValidationException::withMessages(['product_request' => __('product_requests.messages.cannot_cancel')]);
        }

        DB::transaction(function () use ($productRequest): void {
            $locked = $this->lockPending($productRequest, __('product_requests.messages.cannot_cancel'));
            $locked->update(['status' => ProductRequestStatus::Cancelled]);
            $productRequest->setRawAttributes($locked->getAttributes(), true);
        });

        $this->forgetNavBadge();
        $this->discardUnattachedImportedImages($productRequest);

        return $productRequest;
    }

    /**
     * Reject a pending request and tell the requester why (database + broadcast).
     *
     * @throws ValidationException when the request is no longer pending
     */
    public function reject(ProductRequest $productRequest, Admin $admin, string $reason): ProductRequest
    {
        DB::transaction(function () use ($productRequest, $admin, $reason): void {
            $locked = $this->lockPending($productRequest);
            $locked->update([
                'status' => ProductRequestStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
            $productRequest->setRawAttributes($locked->getAttributes(), true);

            DB::afterCommit(fn () => $this->notifyRequester(
                $productRequest,
                fn (object $notifiable) => ProductRequestRejected::for($notifiable, $productRequest, $reason),
            ));
        });

        $this->forgetNavBadge();
        $this->discardUnattachedImportedImages($productRequest);

        $this->activityLogger->log(
            description: "Product request rejected: {$productRequest->request_number}",
            subject: $productRequest,
            causer: $admin,
            properties: ['request_number' => $productRequest->request_number, 'reason' => $reason],
            logName: 'product_requests',
            event: 'product_request_rejected',
        );

        return $productRequest;
    }

    /**
     * Link a freshly created catalog product to the request and mark it approved.
     *
     * Must be called inside the product-creation transaction: the request row is locked,
     * and the requester is notified only once that transaction commits.
     *
     * @throws ValidationException when the request is no longer pending
     */
    public function markApproved(ProductRequest $productRequest, Product $product, Admin $admin): ProductRequest
    {
        $locked = $this->lockPending($productRequest);
        $locked->update([
            'status' => ProductRequestStatus::Approved,
            'product_id' => $product->id,
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $productRequest->setRawAttributes($locked->getAttributes(), true);

        DB::afterCommit(function () use ($productRequest, $product, $admin): void {
            $this->forgetNavBadge();
            $this->discardUnattachedImportedImages($productRequest);

            $this->notifyRequester(
                $productRequest,
                fn (object $notifiable) => ProductRequestApproved::for($notifiable, $productRequest, $product),
            );

            $this->activityLogger->log(
                description: "Product request approved: {$productRequest->request_number}",
                subject: $productRequest,
                causer: $admin,
                properties: ['request_number' => $productRequest->request_number, 'product_id' => $product->id],
                logName: 'product_requests',
                event: 'product_request_approved',
            );
        });

        return $productRequest;
    }

    /**
     * Copy the request images into the product image area as unattached ProductImage rows
     * (product_id = null, exactly like Admin\ProductController::uploadImage()), so the admin
     * product form can pre-attach them. The original request images are left untouched.
     *
     * Idempotent: each source image maps to a deterministic copy under
     * products/images/request-{id}/, and a still-unattached copy from an earlier visit is
     * reused, so reloading or reopening the approve form never duplicates rows or files.
     * Copies the admin did not keep are removed by discardUnattachedImportedImages().
     *
     * @return array<int, array{id: string, url: string, filename: string, name: string, size: ?int, mime_type: ?string}>
     */
    public function importImagesForProductForm(ProductRequest $productRequest): array
    {
        $disk = Storage::disk('public');
        $imported = [];

        foreach ($productRequest->images ?? [] as $sourcePath) {
            if (! is_string($sourcePath) || ! $disk->exists($sourcePath)) {
                continue;
            }

            $targetPath = self::importedImagePath($productRequest, $sourcePath);

            $existingCopy = ProductImage::query()
                ->where('path', $targetPath)
                ->first(['id', 'path', 'product_id', 'product_variant_id', 'mime_type', 'size_bytes']);

            if ($existingCopy && ($existingCopy->product_id !== null || $existingCopy->product_variant_id !== null)) {
                // Already attached to a product: never hand the same file out twice.
                continue;
            }

            if ($existingCopy && $disk->exists($targetPath)) {
                $imported[] = $this->productFormImage($existingCopy->id, $targetPath, $existingCopy->size_bytes, $existingCopy->mime_type);

                continue;
            }

            // A stale row without its file: drop it and copy again.
            $existingCopy?->delete();

            if (! $disk->copy($sourcePath, $targetPath)) {
                Log::warning('Product request image copy failed', ['product_request_id' => $productRequest->id, 'path' => $sourcePath]);

                continue;
            }

            $imageId = (string) Str::uuid();
            $mimeType = $disk->mimeType($targetPath) ?: null;
            $size = $disk->size($targetPath);

            ProductImage::query()->insert([
                'id' => $imageId,
                'product_id' => null,
                'product_variant_id' => null,
                'path' => $targetPath,
                'disk' => 'public',
                'mime_type' => $mimeType,
                'size_bytes' => $size,
                'position' => 0,
                'is_primary' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $imported[] = $this->productFormImage($imageId, $targetPath, $size, $mimeType);
        }

        return $imported;
    }

    /**
     * Delete the product-form copies of the request images that never got attached to a
     * product (rows + files). Called once the request is rejected, cancelled or approved.
     */
    public function discardUnattachedImportedImages(ProductRequest $productRequest): void
    {
        $leftovers = ProductImage::query()
            ->where('path', 'like', self::importedImageDirectory($productRequest).'/%')
            ->whereNull('product_id')
            ->whereNull('product_variant_id')
            ->get(['id', 'path', 'disk']);

        foreach ($leftovers as $image) {
            Storage::disk($image->disk ?: 'public')->delete($image->path);
            $image->delete();
        }
    }

    /**
     * Folder holding the product-form copies of one request's images.
     */
    public static function importedImageDirectory(ProductRequest $productRequest): string
    {
        return 'products/images/request-'.$productRequest->id;
    }

    private static function importedImagePath(ProductRequest $productRequest, string $sourcePath): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');

        return self::importedImageDirectory($productRequest).'/'.sha1($sourcePath).'.'.$extension;
    }

    /**
     * @return array{id: string, url: string, filename: string, name: string, size: ?int, mime_type: ?string}
     */
    private function productFormImage(string $imageId, string $path, ?int $size, ?string $mimeType): array
    {
        return [
            'id' => $imageId,
            'url' => Storage::url($path),
            'filename' => $path,
            'name' => basename($path),
            'size' => $size,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Active admins allowed to review product requests (super admins included via Gate::before).
     *
     * @return Collection<int, Admin>
     */
    public function reviewers(): Collection
    {
        return Admin::query()
            ->where('status', AdminStatus::Active->value)
            ->get()
            ->filter(fn (Admin $admin): bool => $admin->can(self::REVIEW_PERMISSION))
            ->values();
    }

    /**
     * Requester-side recipients: the team member who submitted the request, or every
     * active member of the vendor/marketer team when that account is gone or inactive.
     *
     * @return Collection<int, VendorAdmin|MarketerAdmin>
     */
    public function requesterRecipients(ProductRequest $productRequest): Collection
    {
        $submitter = $productRequest->requestedBy;

        if ($submitter && ($submitter->is_active ?? true)) {
            return collect([$submitter]);
        }

        if ($productRequest->isFromVendor()) {
            return $productRequest->vendor?->vendorAdmins()->where('is_active', true)->get() ?? collect();
        }

        return $productRequest->marketer?->marketerAdmins()->where('is_active', true)->get() ?? collect();
    }

    private function notifyReviewers(ProductRequest $productRequest): void
    {
        foreach ($this->reviewers() as $admin) {
            $admin->notify(new NewProductRequestSubmitted($productRequest, $admin->id));
        }
    }

    /**
     * @param  \Closure(object): Notification  $makeNotification
     */
    private function notifyRequester(ProductRequest $productRequest, \Closure $makeNotification): void
    {
        foreach ($this->requesterRecipients($productRequest) as $recipient) {
            $recipient->notify($makeNotification($recipient));
        }
    }

    /**
     * Re-read the request with a row lock and make sure it is still pending.
     *
     * @throws ValidationException
     */
    private function lockPending(ProductRequest $productRequest, ?string $message = null): ProductRequest
    {
        $locked = ProductRequest::query()->lockForUpdate()->findOrFail($productRequest->id);

        if (! $locked->isPending()) {
            throw ValidationException::withMessages([
                'product_request_id' => $message ?? __('product_requests.messages.not_pending'),
            ]);
        }

        return $locked;
    }

    private function forgetNavBadge(): void
    {
        Cache::forget(self::NAV_BADGE_CACHE_KEY);
    }
}
