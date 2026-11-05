<?php

namespace App\Services\Vendor;

use App\Exceptions\ContractNotConfiguredException;
use App\Exceptions\ContractRequiredException;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedListing;
use App\Models\ContractCountryAssignment;
use App\Models\Country;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorCategoryEnrollment;
use App\Models\VendorContract;
use App\Models\VendorListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Individual contracts for classified categories, product categories and single products.
 *
 * Which contract applies is decided by {@see ContractResolver}: the seller's country, the requirement switches
 * (product override, category switch inherited from parent categories) and the country's assignments. A seller
 * signs a published template once per subject (category or product) and template version. The signed text is
 * frozen in vendor_contracts. A newer published version of the template makes existing signatures stale
 * (enrollment → re_sign_required), and signatures are never deleted or edited.
 */
class CategoryContractService
{
    public const SCOPE_CLASSIFIED = 'classified';

    public const SCOPE_PRODUCT = 'product';

    public const SCOPE_PLATFORM = 'platform';

    public function __construct(
        private readonly ContractFileService $files,
        private readonly ContractResolver $resolver,
    ) {}

    public function resolver(): ContractResolver
    {
        return $this->resolver;
    }

    public function categoryFor(string $scope, string $categoryId): ClassifiedCategory|Category|null
    {
        return $scope === self::SCOPE_PRODUCT
            ? Category::find($categoryId)
            : ClassifiedCategory::find($categoryId);
    }

    /**
     * The individual template that applies to this category for a country, or null when the category does not
     * require one (or, for a legacy link, when its template is no longer enforceable).
     * With no country only country-agnostic templates apply.
     */
    public function templateFor(ClassifiedCategory|Category $category, ?Country $country = null): ?ClassifiedContractTemplate
    {
        $scope = $category instanceof Category ? self::SCOPE_PRODUCT : self::SCOPE_CLASSIFIED;

        try {
            $resolved = $this->resolver->resolveForCategory($scope, $category, $country);
        } catch (ContractNotConfiguredException) {
            return null;
        }

        return $resolved->isIndividual() ? $resolved->template : null;
    }

    /**
     * The template the vendor still has to sign for this category before creating a listing in it, or null.
     * The category may be covered by a parent category's contract; one signature covers all sub-categories.
     *
     * @throws ContractNotConfiguredException when the category requires a contract that is not configured for the vendor's country
     */
    public function unsignedTemplateFor(Vendor $vendor, string $scope, string $categoryId): ?ClassifiedContractTemplate
    {
        $category = $this->categoryFor($scope, $categoryId);

        if (! $category) {
            return null;
        }

        return $this->unsignedFor($vendor, $this->resolver->resolveForVendorCategory($vendor, $scope, $category));
    }

    /**
     * The individual contract a vendor still has to sign for this resolved requirement, or null.
     */
    public function unsignedFor(Vendor $vendor, ResolvedContract $resolved): ?ClassifiedContractTemplate
    {
        if (! $resolved->isIndividual() || $this->hasCurrentSignature($vendor, $resolved->scope, $resolved->subject, $resolved->template)) {
            return null;
        }

        return $resolved->template;
    }

    /**
     * The unsigned individual contract for a vendor listing this product, resolved through the product override,
     * the category switch (with parent inheritance) and the vendor's country. Null when the global agreement
     * alone covers it or the contract is already signed.
     *
     * @return array{template: ClassifiedContractTemplate, scope: string, subject: Model, resolved: ResolvedContract}|null
     *
     * @throws ContractNotConfiguredException
     */
    public function unsignedForProduct(Vendor $vendor, Product $product): ?array
    {
        $resolved = $this->resolver->resolveForVendorProduct($vendor, $product);
        $template = $this->unsignedFor($vendor, $resolved);

        return $template ? ['template' => $template, 'scope' => $resolved->scope, 'subject' => $resolved->subject, 'resolved' => $resolved] : null;
    }

    /**
     * Blocks creating or activating a listing of this product while a required individual contract is unsigned or
     * not configured for the vendor's country. The global Seller Agreement never counts as the individual contract.
     *
     * @throws ContractRequiredException
     * @throws ContractNotConfiguredException
     */
    public function assertCoveredForProduct(Vendor $vendor, Product $product, string $fileRoute = 'vendor.legal.template-file', string $acceptRoute = 'vendor.contracts.accept'): void
    {
        $pending = $this->unsignedForProduct($vendor, $product);

        if (! $pending) {
            return;
        }

        throw ContractRequiredException::for(
            $pending['template'],
            $this->payloadFor($vendor, $pending['template'], $pending['scope'], $pending['subject'], $fileRoute, $acceptRoute) + ['product_id' => $product->id],
            __('contracts.api.contract_required'),
        );
    }

    /**
     * Presented contract plus where it belongs, for API and wizard payloads.
     *
     * @return array<string, mixed>
     */
    public function payloadFor(Vendor $vendor, ClassifiedContractTemplate $template, string $scope, Model $subject, string $fileRoute = 'partner.legal.template-file', ?string $acceptRoute = null): array
    {
        $payload = $this->present($template, $vendor, $subject, $fileRoute) + [
            'category_scope' => $scope,
            'category_id' => $subject instanceof Product ? null : $subject->getKey(),
            'product_id' => $subject instanceof Product ? $subject->getKey() : null,
            'category_name' => $subject->name_en,
            'country' => $this->countryPayload($this->resolver->countryOf($vendor)),
        ];

        if ($acceptRoute !== null) {
            $payload['accept_url'] = route($acceptRoute, $template->id);
        }

        return $payload;
    }

    /**
     * @return array{id: string, code: string, name: string}|null
     */
    public function countryPayload(?Country $country): ?array
    {
        return $country ? ['id' => $country->id, 'code' => $country->iso_code_2, 'name' => $country->name_en] : null;
    }

    /**
     * The individual template a classified listing of this owner needs, or null. Vendors are resolved by their
     * own country; customers (who list for themselves) by the listing's country.
     *
     * @throws ContractNotConfiguredException
     */
    public function classifiedTemplateForOwner(?Model $owner, ClassifiedCategory $category, ?string $listingCountryId = null): ?ClassifiedContractTemplate
    {
        $country = $owner instanceof Vendor
            ? $this->resolver->countryOf($owner)
            : ($listingCountryId ? Country::find($listingCountryId) : null);

        $resolved = $this->resolver->resolveForCategory(self::SCOPE_CLASSIFIED, $category, $country);

        return $resolved->isIndividual() ? $resolved->template : null;
    }

    /**
     * @throws ContractNotConfiguredException
     */
    public function classifiedTemplateForListing(ClassifiedListing $listing): ?ClassifiedContractTemplate
    {
        $category = $listing->classifiedCategory;
        $owner = $listing->seller_type === Vendor::class ? $listing->seller : null;

        return $category ? $this->classifiedTemplateForOwner($owner, $category, $listing->country_id) : null;
    }

    public function hasCurrentSignature(Vendor $vendor, string $scope, Model $subject, ClassifiedContractTemplate $template): bool
    {
        return $this->currentSignatureQuery($vendor, $scope, $subject)
            ->where('contract_template_id', $template->id)
            ->where('template_version', $template->version)
            ->exists();
    }

    /**
     * @return Builder<VendorContract>
     */
    private function currentSignatureQuery(Vendor $vendor, string $scope, Model $subject)
    {
        $query = VendorContract::where('vendor_id', $vendor->id)
            ->where('category_scope', $scope)
            ->where('status', 'active');

        return $subject instanceof Product
            ? $query->where('product_id', $subject->getKey())
            : $query->whereNull('product_id')->where($this->categoryColumn($scope), $subject->getKey());
    }

    /**
     * Individual contracts the vendor still has to sign for what they list: product categories (with parent
     * inheritance), classified categories and products with their own contract. Subjects whose contract is not
     * configured for the vendor's country are skipped here (they are blocked at creation with a clear error).
     *
     * @return Collection<int, array{scope: string, category: Model, template: ClassifiedContractTemplate}>
     */
    public function pendingForVendor(Vendor $vendor): Collection
    {
        $country = $this->resolver->countryOf($vendor);

        $classifiedIds = ClassifiedListing::forVendors()
            ->where('seller_id', $vendor->id)
            ->whereNotIn('status', ['draft', 'sold', 'expired', 'rejected'])
            ->pluck('classified_category_id')
            ->unique();

        $products = VendorListing::where('vendor_id', $vendor->id)
            ->with('productVariant.product')
            ->get()
            ->map(fn (VendorListing $listing) => $listing->productVariant?->product)
            ->filter()
            ->unique('id');

        $entries = collect();

        foreach (ClassifiedCategory::whereIn('id', $classifiedIds)->get() as $category) {
            $entries->push($this->pendingEntry($vendor, fn () => $this->resolver->resolveForCategory(self::SCOPE_CLASSIFIED, $category, $country)));
        }

        foreach ($products as $product) {
            $entries->push($this->pendingEntry($vendor, fn () => $this->resolver->resolveForProduct($product, $country)));
        }

        return $entries->filter()->unique(fn (array $entry) => $entry['scope'].':'.$entry['category']->getKey())->values();
    }

    /**
     * @return array{scope: string, category: Model, template: ClassifiedContractTemplate}|null
     */
    private function pendingEntry(Vendor $vendor, \Closure $resolve): ?array
    {
        try {
            $resolved = $resolve();
        } catch (ContractNotConfiguredException $e) {
            Log::info('Individual contract not configured for seller country', ['vendor_id' => $vendor->id, 'country_id' => $e->country?->id, 'scope' => $e->scope]);

            return null;
        }

        $template = $this->unsignedFor($vendor, $resolved);

        return $template ? ['scope' => $resolved->scope, 'category' => $resolved->subject, 'template' => $template] : null;
    }

    /**
     * Records the signature: freezes rendered text, variables and hash, supersedes older active signatures
     * for the same subject, and marks the enrollment signed. The template is the one the resolver picks for
     * this vendor's country, never one the client names. Idempotent and race-safe: the vendor row is locked and
     * a signature that already covers the subject at this version is returned instead of a duplicate.
     *
     * @param  string  $categoryId  the category, or the product's category when $productId is given
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
        ?string $productId = null,
    ): VendorContract {
        if ($productId) {
            $product = Product::find($productId) ?? throw ValidationException::withMessages(['product' => 'Unknown product.']);
            $resolved = $this->resolver->resolveForVendorProduct($vendor, $product);
        } else {
            $category = $this->categoryFor($scope, $categoryId)
                ?? throw ValidationException::withMessages(['category' => 'Unknown category.']);
            $resolved = $this->resolver->resolveForVendorCategory($vendor, $scope, $category);
        }

        if (! $resolved->isIndividual()) {
            throw ValidationException::withMessages(['contract' => __('contracts.api.no_individual_contract')]);
        }

        $template = $resolved->template;
        $subject = $resolved->subject;

        if ($existing = $this->currentSignatureQuery($vendor, $scope, $subject)->where('contract_template_id', $template->id)->first()) {
            return $existing;
        }

        $frozen = $this->freeze($vendor, $template, $subject, $language, $signerName);

        return DB::transaction(function () use ($vendor, $signer, $scope, $subject, $template, $frozen, $signerName, $ipAddress, $userAgent, $signaturePath, $resolved) {
            Vendor::whereKey($vendor->id)->lockForUpdate()->first();

            if ($existing = $this->currentSignatureQuery($vendor, $scope, $subject)->where('contract_template_id', $template->id)->first()) {
                return $existing;
            }

            $isProduct = $subject instanceof Product;
            $enrollment = $isProduct ? null : $this->enrollmentFor($vendor, $scope, $subject);

            if ($enrollment) {
                $enrollment->contracts()->where('status', 'active')->update(['status' => 'superseded']);
            } else {
                $this->currentSignatureQuery($vendor, $scope, $subject)->update(['status' => 'superseded']);
            }

            $contract = VendorContract::create($frozen + [
                'vendor_id' => $vendor->id,
                'vendor_category_enrollment_id' => $enrollment?->id,
                'classified_category_id' => $scope === self::SCOPE_CLASSIFIED ? $subject->getKey() : null,
                'category_scope' => $scope,
                'product_category_id' => $scope === self::SCOPE_PRODUCT && ! $isProduct ? $subject->getKey() : null,
                'product_id' => $isProduct ? $subject->getKey() : null,
                'country_id' => $resolved->country?->id,
                'contract_template_id' => $template->id,
                'template_version' => $template->version,
                'signature_path' => $signaturePath,
                'signed_by_vendor_admin_id' => $signer?->id,
                'signer_name' => $signerName,
                'signed_ip' => $ipAddress,
                'signed_user_agent' => $userAgent,
                'signed_at' => now(),
                'status' => 'active',
                'acceptance_method' => 'click_wrap',
            ]);

            $enrollment?->update([
                'status' => VendorCategoryEnrollment::STATUS_SIGNED,
                'active_contract_id' => $contract->id,
                'signed_at' => now(),
            ]);

            return $contract;
        });
    }

    /**
     * The immutable part of a signature: the text rendered for this vendor, or a private copy of the uploaded
     * document, plus the hash that proves what was signed.
     *
     * @return array<string, mixed>
     */
    public function freeze(Vendor $vendor, ClassifiedContractTemplate $template, ?Model $category, string $language, string $signerName): array
    {
        $language = $language === 'ar' ? 'ar' : 'en';
        $variables = $this->variablesFor($vendor, $category, $template, $signerName);

        if ($template->isFileMode()) {
            $file = $template->fileFor($language)
                ?? throw ValidationException::withMessages(['contract' => 'This contract has no uploaded document.']);
            $copy = $this->files->copyForSignature($file, $vendor->id);

            return [
                'language_signed' => $file['language'],
                'content_mode' => ClassifiedContractTemplate::MODE_FILE,
                'rendered_content' => '',
                'rendered_content_hash' => $file['sha256'],
                'variables' => $variables,
                'signed_file_path' => $copy['path'],
                'signed_file_name' => $copy['name'],
                'signed_file_mime' => $copy['mime'],
            ];
        }

        $content = $this->renderContent($language === 'ar' ? $template->content_ar : $template->content_en, $variables);

        return [
            'language_signed' => $language,
            'content_mode' => ClassifiedContractTemplate::MODE_EDITOR,
            'rendered_content' => $content,
            'rendered_content_hash' => hash('sha256', $content.json_encode($variables)),
            'variables' => $variables,
        ];
    }

    /**
     * What a client needs to show a template: rendered text in editor mode, document links in file mode.
     *
     * @return array{template_id: string, name: string, version: int, content_mode: string, content_en: string, content_ar: string, files: array<string, array{name: string, mime: string, size: int, url: string, inline: bool}>}
     */
    public function present(ClassifiedContractTemplate $template, Vendor $vendor, ?Model $category, string $fileRoute = 'partner.legal.template-file'): array
    {
        $payload = [
            'template_id' => $template->id,
            'name' => $template->name,
            'version' => $template->version,
            'content_mode' => $template->content_mode,
            'content_en' => '',
            'content_ar' => '',
            'files' => [],
        ];

        if ($template->isFileMode()) {
            $payload['files'] = $this->fileLinks($template, $fileRoute);
            $payload['content_en'] = $this->fileHtml($payload['files'], 'en');
            $payload['content_ar'] = $this->fileHtml($payload['files'], 'ar');

            return $payload;
        }

        $variables = $this->variablesFor($vendor, $category, $template);
        $payload['content_en'] = $this->renderContent($template->content_en, $variables);
        $payload['content_ar'] = $this->renderContent($template->content_ar, $variables);

        return $payload;
    }

    /**
     * The same payload for readers who are not vendors yet (registration, public terms page). Vendor-specific
     * variables stay as written because there is no vendor to fill them from.
     *
     * @return array{template_id: string, name: string, version: int, content_mode: string, content_en: string, content_ar: string, files: array<string, array{name: string, mime: string, size: int, url: string, inline: bool}>}
     */
    public function presentPublic(ClassifiedContractTemplate $template): array
    {
        $payload = [
            'template_id' => $template->id,
            'name' => $template->name,
            'version' => $template->version,
            'content_mode' => $template->content_mode,
            'content_en' => '',
            'content_ar' => '',
            'files' => [],
        ];

        if ($template->isFileMode()) {
            $payload['files'] = $this->fileLinks($template, 'portal.legal.seller-agreement.file');
            $payload['content_en'] = $this->fileHtml($payload['files'], 'en');
            $payload['content_ar'] = $this->fileHtml($payload['files'], 'ar');

            return $payload;
        }

        $variables = [
            'platform.name_en' => (string) config('app.name'),
            'platform.name_ar' => (string) config('app.name'),
            'contract.version' => (string) $template->version,
            'contract.date' => ($template->effective_at ?? now())->format('d/m/Y'),
        ];
        $payload['content_en'] = $this->renderContent($template->content_en, $variables);
        $payload['content_ar'] = $this->renderContent($template->content_ar, $variables);

        return $payload;
    }

    /**
     * HTML stand-in for the contract text of an uploaded document, so clients that only render
     * content_en/content_ar still show the document (inline when a PDF preview exists, plus a link).
     *
     * @param  array<string, array{name: string, mime: string, size: int, url: string, inline: bool}>  $files
     */
    public function fileHtml(array $files, string $language): string
    {
        $file = $files[$language] ?? $files['ar'] ?? $files['en'] ?? null;

        if (! $file) {
            return '';
        }

        $name = e($file['name']);
        $url = e($file['url']);
        $html = $file['inline'] ? '<iframe src="'.$url.'" title="'.$name.'" style="width:100%;height:28rem;border:1px solid #e5e7eb;border-radius:8px"></iframe>' : '';

        return $html.'<p><a href="'.$url.(str_contains($file['url'], '?') ? '&' : '?').'download=1" target="_blank" rel="noopener">'.$name.'</a></p>';
    }

    /**
     * @return array<string, array{name: string, mime: string, size: int, url: string, inline: bool}>
     */
    public function fileLinks(ClassifiedContractTemplate $template, string $fileRoute = 'partner.legal.template-file'): array
    {
        $links = [];

        foreach (['ar', 'en'] as $language) {
            $file = ($template->files ?? [])[$language] ?? null;

            if ($file) {
                $links[$language] = [
                    'name' => $file['name'],
                    'mime' => $file['mime'],
                    'size' => $file['size'],
                    'url' => route($fileRoute, ['template' => $template->id, 'language' => $language]),
                    'inline' => $file['pdf_path'] !== null,
                ];
            }
        }

        return $links;
    }

    /**
     * Makes this version the one vendors sign, within its own country lineage (same name, scope and country).
     * Categories, products and country assignments on the older versions move to it, signed enrollments in
     * those categories must re-sign, and the older version is retired. Other countries are never touched.
     * Publishing a country default replaces the previous default of that country and scope.
     *
     * @return Collection<int, Vendor> vendors that must re-sign, for notification
     */
    public function publish(ClassifiedContractTemplate $template): Collection
    {
        $lineage = $this->lineageIds($template);

        return DB::transaction(function () use ($template, $lineage) {
            ClassifiedContractTemplate::whereIn('id', $lineage)
                ->where('id', '!=', $template->id)
                ->update(['is_published' => false, 'is_active' => false]);

            if ($template->is_country_default && $template->country_id) {
                ClassifiedContractTemplate::where('country_id', $template->country_id)
                    ->where('category_scope', $template->category_scope)
                    ->where('is_country_default', true)
                    ->where('id', '!=', $template->id)
                    ->whereNotIn('id', $lineage)
                    ->update(['is_country_default' => false]);
            }

            $template->update(['is_published' => true, 'is_active' => true]);

            $isProduct = $template->category_scope === self::SCOPE_PRODUCT;
            $model = $isProduct ? Category::class : ClassifiedCategory::class;
            $column = $isProduct ? 'product_category_id' : 'classified_category_id';

            $linked = $model::whereIn('contract_template_id', $lineage)->pluck('id');
            $model::whereIn('id', $linked)->update(['contract_template_id' => $template->id]);

            $assigned = ContractCountryAssignment::whereIn('contract_template_id', $lineage)->get();
            ContractCountryAssignment::whereIn('id', $assigned->pluck('id'))->update(['contract_template_id' => $template->id]);

            if ($isProduct) {
                Product::whereIn('contract_template_id', $lineage)->update(['contract_template_id' => $template->id]);
            }

            $categoryIds = $linked->merge($assigned->pluck($column)->filter())->unique();

            $enrollments = VendorCategoryEnrollment::where('category_scope', $template->category_scope)
                ->whereIn($this->categoryColumn($template->category_scope), $categoryIds)
                ->where('status', VendorCategoryEnrollment::STATUS_SIGNED);

            $vendorIds = (clone $enrollments)->pluck('vendor_id')
                ->merge($this->productSignatureVendorIds($lineage, $template));
            $enrollments->update(['status' => VendorCategoryEnrollment::STATUS_RE_SIGN]);

            return Vendor::with('vendorAdmins')->whereIn('id', $vendorIds->unique())->get();
        });
    }

    /**
     * Ids of every version of the same contract in the same country: same name, scope and country.
     *
     * @return Collection<int, string>
     */
    public function lineageIds(ClassifiedContractTemplate $template): Collection
    {
        return ClassifiedContractTemplate::where('name', $template->name)
            ->where('category_scope', $template->category_scope)
            ->when($template->country_id, fn (Builder $query) => $query->where('country_id', $template->country_id), fn (Builder $query) => $query->whereNull('country_id'))
            ->pluck('id');
    }

    /**
     * @param  Collection<int, string>  $lineage
     * @return Collection<int, string>
     */
    private function productSignatureVendorIds(Collection $lineage, ClassifiedContractTemplate $template): Collection
    {
        if ($template->category_scope !== self::SCOPE_PRODUCT) {
            return collect();
        }

        return VendorContract::whereIn('contract_template_id', $lineage)
            ->where('id', '!=', $template->id)
            ->whereNotNull('product_id')
            ->where('status', 'active')
            ->pluck('vendor_id');
    }

    /**
     * @return array<string, string> variable values keyed without braces
     */
    public function variablesFor(Vendor $vendor, ?Model $category, ClassifiedContractTemplate $template, string $signerName = ''): array
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
            'category.name_en' => (string) ($category?->name_en ?? ''),
            'category.name_ar' => (string) ($category?->name_ar ?? ''),
            'contract.version' => (string) $template->version,
            'contract.date' => now()->format('d/m/Y'),
        ];
    }

    /**
     * Contract content as HTML. Editor-authored content is HTML; older plain-text content is converted.
     * Vendor-supplied values are escaped, so the signed text can be shown with {!! !!} safely.
     *
     * @param  array<string, string>  $variables
     */
    public function renderContent(string $content, array $variables): string
    {
        if ($content === strip_tags($content)) {
            return nl2br(e($this->render($content, $variables)));
        }

        return $this->render($content, array_map(fn (string $value) => e($value), $variables));
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
        $isProduct = $template->category_scope === self::SCOPE_PRODUCT;
        $linked = ($isProduct ? Category::class : ClassifiedCategory::class)::where('contract_template_id', $template->id)->pluck('id');
        $assigned = ContractCountryAssignment::where('contract_template_id', $template->id)
            ->pluck($isProduct ? 'product_category_id' : 'classified_category_id')->filter();

        $vendorIds = VendorCategoryEnrollment::where('category_scope', $template->category_scope)
            ->whereIn($this->categoryColumn($template->category_scope), $linked->merge($assigned)->unique())
            ->where('status', VendorCategoryEnrollment::STATUS_RE_SIGN)
            ->pluck('vendor_id')
            ->merge($this->productSignatureVendorIds($this->lineageIds($template), $template))
            ->unique();

        return Vendor::with('vendorAdmins')->whereIn('id', $vendorIds)->get();
    }

    /**
     * Ensures an enrollment row exists for the vendor and category, without changing its status.
     */
    public function enrollmentFor(Vendor $vendor, string $scope, Model $category): VendorCategoryEnrollment
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
