<?php

namespace App\Services\Vendor;

use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedListing;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorCategoryEnrollment;
use App\Models\VendorContract;
use App\Models\VendorListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Category contracts for classified and product categories.
 *
 * A vendor signs a published template once per category. The signed text is frozen in vendor_contracts.
 * A newer published version of the template makes existing signatures stale (enrollment → re_sign_required).
 */
class CategoryContractService
{
    public const SCOPE_CLASSIFIED = 'classified';

    public const SCOPE_PRODUCT = 'product';

    public function categoryFor(string $scope, string $categoryId): ClassifiedCategory|Category|null
    {
        return $scope === self::SCOPE_PRODUCT
            ? Category::find($categoryId)
            : ClassifiedCategory::find($categoryId);
    }

    /**
     * The template a vendor must sign for this category, or null when the category has none enforceable.
     */
    public function templateFor(ClassifiedCategory|Category $category): ?ClassifiedContractTemplate
    {
        $template = $category->contractTemplate;

        return $template && $template->is_published && $template->is_active ? $template : null;
    }

    /**
     * The template the vendor still has to sign for this category before creating a listing in it, or null.
     */
    public function unsignedTemplateFor(Vendor $vendor, string $scope, string $categoryId): ?ClassifiedContractTemplate
    {
        $category = $this->categoryFor($scope, $categoryId);

        if (! $category) {
            return null;
        }

        $template = $this->templateFor($category);

        if (! $template || $this->hasCurrentSignature($vendor, $scope, $category, $template)) {
            return null;
        }

        return $template;
    }

    public function hasCurrentSignature(Vendor $vendor, string $scope, ClassifiedCategory|Category $category, ClassifiedContractTemplate $template): bool
    {
        return VendorContract::where('vendor_id', $vendor->id)
            ->where('category_scope', $scope)
            ->where($this->categoryColumn($scope), $category->id)
            ->where('contract_template_id', $template->id)
            ->where('template_version', $template->version)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Categories where the vendor currently lists and has not signed the published version.
     *
     * @return Collection<int, array{scope: string, category: ClassifiedCategory|Category, template: ClassifiedContractTemplate}>
     */
    public function pendingForVendor(Vendor $vendor): Collection
    {
        $classifiedIds = ClassifiedListing::forVendors()
            ->where('seller_id', $vendor->id)
            ->whereNotIn('status', ['draft', 'sold', 'expired', 'rejected'])
            ->pluck('classified_category_id')
            ->unique();

        $productIds = VendorListing::where('vendor_id', $vendor->id)
            ->with('productVariant.product:id,category_id')
            ->get()
            ->map(fn (VendorListing $listing) => $listing->productVariant?->product?->category_id)
            ->filter()
            ->unique();

        $classified = ClassifiedCategory::whereIn('id', $classifiedIds)->with('contractTemplate')->get();
        $product = Category::whereIn('id', $productIds)->with('contractTemplate')->get();

        return $this->pendingEntries($classified, self::SCOPE_CLASSIFIED, $vendor)
            ->concat($this->pendingEntries($product, self::SCOPE_PRODUCT, $vendor))
            ->values();
    }

    /**
     * Records the signature: freezes rendered text, variables and hash, supersedes older active signatures
     * for the same category, and marks the enrollment signed.
     */
    public function sign(
        Vendor $vendor,
        ?VendorAdmin $signer,
        string $scope,
        string $categoryId,
        string $language,
        string $signerName,
        string $ipAddress,
        string $userAgent,
        ?string $signaturePath = null,
    ): VendorContract {
        $category = $this->categoryFor($scope, $categoryId)
            ?? throw ValidationException::withMessages(['category' => 'Unknown category.']);

        $template = $this->templateFor($category)
            ?? throw ValidationException::withMessages(['contract' => 'No published contract for this category.']);

        $language = $language === 'ar' ? 'ar' : 'en';
        $variables = $this->variablesFor($vendor, $category, $template, $signerName);
        $content = $this->render($language === 'ar' ? $template->content_ar : $template->content_en, $variables);

        return DB::transaction(function () use ($vendor, $signer, $scope, $category, $template, $language, $variables, $content, $signerName, $ipAddress, $userAgent, $signaturePath) {
            $enrollment = $this->enrollmentFor($vendor, $scope, $category);

            $enrollment->contracts()->where('status', 'active')->update(['status' => 'superseded']);

            $contract = VendorContract::create([
                'vendor_id' => $vendor->id,
                'vendor_category_enrollment_id' => $enrollment->id,
                'classified_category_id' => $scope === self::SCOPE_CLASSIFIED ? $category->id : null,
                'category_scope' => $scope,
                'product_category_id' => $scope === self::SCOPE_PRODUCT ? $category->id : null,
                'contract_template_id' => $template->id,
                'template_version' => $template->version,
                'language_signed' => $language,
                'rendered_content' => $content,
                'rendered_content_hash' => hash('sha256', $content.json_encode($variables)),
                'variables' => $variables,
                'signature_path' => $signaturePath,
                'signed_by_vendor_admin_id' => $signer?->id,
                'signer_name' => $signerName,
                'signed_ip' => $ipAddress,
                'signed_user_agent' => $userAgent,
                'signed_at' => now(),
                'status' => 'active',
            ]);

            $enrollment->update([
                'status' => VendorCategoryEnrollment::STATUS_SIGNED,
                'active_contract_id' => $contract->id,
                'signed_at' => now(),
            ]);

            return $contract;
        });
    }

    /**
     * Makes this version the one vendors sign. Categories on the older versions move to it,
     * signed enrollments in those categories must re-sign, and the older version is retired.
     *
     * @return Collection<int, Vendor> vendors that must re-sign, for notification
     */
    public function publish(ClassifiedContractTemplate $template): Collection
    {
        $lineage = ClassifiedContractTemplate::where('name', $template->name)
            ->where('category_scope', $template->category_scope)
            ->pluck('id');

        return DB::transaction(function () use ($template, $lineage) {
            ClassifiedContractTemplate::whereIn('id', $lineage)
                ->where('id', '!=', $template->id)
                ->update(['is_published' => false, 'is_active' => false]);

            $template->update(['is_published' => true, 'is_active' => true]);

            if ($template->category_scope === self::SCOPE_PRODUCT) {
                $categoryIds = Category::whereIn('contract_template_id', $lineage)->pluck('id');
                Category::whereIn('id', $categoryIds)->get()
                    ->each(fn (Category $category) => $category->update(['contract_template_id' => $template->id]));
            } else {
                $categoryIds = ClassifiedCategory::whereIn('contract_template_id', $lineage)->pluck('id');
                ClassifiedCategory::whereIn('id', $categoryIds)->get()
                    ->each(fn (ClassifiedCategory $category) => $category->update(['contract_template_id' => $template->id]));
            }

            $enrollments = VendorCategoryEnrollment::where('category_scope', $template->category_scope)
                ->whereIn($this->categoryColumn($template->category_scope), $categoryIds)
                ->where('status', VendorCategoryEnrollment::STATUS_SIGNED);

            $vendorIds = (clone $enrollments)->pluck('vendor_id')->unique();
            $enrollments->update(['status' => VendorCategoryEnrollment::STATUS_RE_SIGN]);

            return Vendor::with('vendorAdmins')->whereIn('id', $vendorIds)->get();
        });
    }

    /**
     * @return array<string, string> variable values keyed without braces
     */
    public function variablesFor(Vendor $vendor, ClassifiedCategory|Category $category, ClassifiedContractTemplate $template, string $signerName = ''): array
    {
        $vendor->loadMissing(['country', 'businessAddress.city']);

        $businessType = $vendor->business_type;

        return [
            'vendor.store_name' => (string) $vendor->store_name,
            'vendor.business_name' => (string) ($vendor->business_name ?? $vendor->store_name),
            'vendor.business_type' => $businessType instanceof \BackedEnum ? (string) $businessType->value : (string) $businessType,
            'vendor.registration_number' => (string) $vendor->business_registration_number,
            'vendor.tax_id' => (string) $vendor->tax_id,
            'vendor.contact_email' => (string) ($vendor->contact_email ?? $vendor->email),
            'vendor.contact_phone' => (string) ($vendor->contact_phone ?? $vendor->phone),
            'vendor.address' => $this->formatAddress($vendor),
            'vendor.country' => (string) ($vendor->country?->name_en ?? ''),
            'vendor.signer_name' => $signerName,
            'platform.name_en' => (string) config('app.name'),
            'platform.name_ar' => (string) config('app.name'),
            'category.name_en' => (string) $category->name_en,
            'category.name_ar' => (string) $category->name_ar,
            'contract.version' => (string) $template->version,
            'contract.date' => now()->format('d/m/Y'),
        ];
    }

    /**
     * Replaces {{key}} tokens with values. Tokens without a value are left as written.
     *
     * @param  array<string, string>  $variables
     */
    public function render(string $content, array $variables): string
    {
        $tokens = [];

        foreach ($variables as $key => $value) {
            $tokens['{{'.$key.'}}'] = $value;
        }

        return strtr($content, $tokens);
    }

    /**
     * Vendors whose signatures went stale when this version was published.
     *
     * @return Collection<int, Vendor>
     */
    public function vendorsRequiredToResign(ClassifiedContractTemplate $template): Collection
    {
        $categoryIds = $template->category_scope === self::SCOPE_PRODUCT
            ? Category::where('contract_template_id', $template->id)->pluck('id')
            : ClassifiedCategory::where('contract_template_id', $template->id)->pluck('id');

        $vendorIds = VendorCategoryEnrollment::where('category_scope', $template->category_scope)
            ->whereIn($this->categoryColumn($template->category_scope), $categoryIds)
            ->where('status', VendorCategoryEnrollment::STATUS_RE_SIGN)
            ->pluck('vendor_id')
            ->unique();

        return Vendor::with('vendorAdmins')->whereIn('id', $vendorIds)->get();
    }

    /**
     * Ensures an enrollment row exists for the vendor and category, without changing its status.
     */
    public function enrollmentFor(Vendor $vendor, string $scope, ClassifiedCategory|Category $category): VendorCategoryEnrollment
    {
        return VendorCategoryEnrollment::firstOrCreate(
            [
                'vendor_id' => $vendor->id,
                $this->categoryColumn($scope) => $category->id,
            ],
            [
                'category_scope' => $scope,
                'status' => VendorCategoryEnrollment::STATUS_PENDING,
                'requested_at' => now(),
            ],
        );
    }

    private function pendingEntries(Collection $categories, string $scope, Vendor $vendor): Collection
    {
        return $categories
            ->map(fn (ClassifiedCategory|Category $category) => [
                'scope' => $scope,
                'category' => $category,
                'template' => $this->templateFor($category),
            ])
            ->filter(fn (array $entry) => $entry['template'] !== null
                && ! $this->hasCurrentSignature($vendor, $scope, $entry['category'], $entry['template']))
            ->values();
    }

    private function categoryColumn(string $scope): string
    {
        return $scope === self::SCOPE_PRODUCT ? 'product_category_id' : 'classified_category_id';
    }

    private function formatAddress(Vendor $vendor): string
    {
        $address = $vendor->businessAddress;

        if (! $address) {
            return '';
        }

        return collect([$address->street_address, $address->area, $address->building, $address->city?->name_en])
            ->filter()
            ->implode(', ');
    }
}
