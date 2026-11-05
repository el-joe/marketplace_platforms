<?php

namespace App\Services\Customer;

use App\Exceptions\CustomerContractRequiredException;
use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedInquiry;
use App\Models\ClassifiedListing;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\Vendor;
use App\Services\ActivityLoggerService;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractFileService;
use App\Services\Vendor\ContractResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer purchase contracts (kind customer_purchase): the contract a customer reviews and accepts before buying or
 * renting a classified item such as a property or a car.
 *
 * It is its own business workflow. It shares template storage and versioning with the seller contracts but has its own
 * resolution ({@see ContractResolver::resolveForCustomerPurchase()}: the advertisement's category, with parent
 * inheritance, and the advertisement's country, never another country's contract), its own signing record
 * (customer_contracts) and its own enforcement point: the inquiry / purchase action of a classified advertisement.
 *
 * When no customer template applies, nothing changes for the customer. When one does, the action is refused with
 * `customer_contract_required` until the customer has accepted the CURRENT version for that advertisement. A newer
 * published version needs a new acceptance; earlier acceptances stay on record untouched.
 *
 * The acceptance is a recorded fact (typed name, time, IP address, user agent, frozen text and hash). This service
 * makes no claim about its legal value as an electronic signature.
 */
class CustomerContractService
{
    public function __construct(
        private readonly ContractResolver $resolver,
        private readonly CategoryContractService $contracts,
        private readonly ContractFileService $files,
        private readonly ActivityLoggerService $activity,
    ) {}

    // ── Resolution ────────────────────────────────────────────────────────────

    /**
     * The customer contract the advertisement needs, or null when none is required (including the customer's own
     * advertisement, which he cannot buy).
     */
    public function templateFor(ClassifiedListing $listing, ?Customer $customer = null): ?ClassifiedContractTemplate
    {
        if ($customer && $listing->seller_type === Customer::class && $listing->seller_id === $customer->id) {
            return null;
        }

        $listing->loadMissing(['classifiedCategory', 'country']);

        if (! $listing->classifiedCategory) {
            return null;
        }

        return $this->resolver->resolveForCustomerPurchase($listing->classifiedCategory, $listing->country);
    }

    /**
     * The customer's acceptance of the CURRENT version of the template for this advertisement, if any.
     */
    public function currentAcceptance(Customer $customer, ClassifiedListing $listing, ClassifiedContractTemplate $template): ?CustomerContract
    {
        return CustomerContract::query()
            ->active()
            ->where('customer_id', $customer->id)
            ->where('classified_listing_id', $listing->id)
            ->where('contract_template_id', $template->id)
            ->where('template_version', $template->version)
            ->first();
    }

    /**
     * What the customer still has to do for an advertisement.
     *
     * @return array{required: bool, template: ?ClassifiedContractTemplate, acceptance: ?CustomerContract}
     */
    public function statusFor(Customer $customer, ClassifiedListing $listing): array
    {
        $template = $this->templateFor($listing, $customer);

        return [
            'required' => $template !== null,
            'template' => $template,
            'acceptance' => $template ? $this->currentAcceptance($customer, $listing, $template) : null,
        ];
    }

    /**
     * The gate of the inquiry / purchase action. Returns the acceptance to link to the inquiry (null when no
     * contract applies) and refuses the action while the current version is not accepted.
     *
     * @throws CustomerContractRequiredException
     */
    public function assertAccepted(ClassifiedListing $listing, Customer $customer): ?CustomerContract
    {
        $status = $this->statusFor($customer, $listing);

        if (! $status['required']) {
            return null;
        }

        if ($status['acceptance']) {
            return $status['acceptance'];
        }

        throw CustomerContractRequiredException::for($this->present($status['template'], $listing, $customer));
    }

    /**
     * Links the first inquiry made under an acceptance back to it.
     */
    public function linkInquiry(?CustomerContract $acceptance, ClassifiedInquiry $inquiry): void
    {
        if ($acceptance && $acceptance->classified_inquiry_id === null) {
            $acceptance->forceFill(['classified_inquiry_id' => $inquiry->id])->saveQuietly();
        }
    }

    // ── Presentation ──────────────────────────────────────────────────────────

    /**
     * What a client needs to show the contract: the text rendered for this customer and advertisement in editor
     * mode, links to the documents in file mode.
     *
     * @return array<string, mixed>
     */
    public function present(ClassifiedContractTemplate $template, ClassifiedListing $listing, Customer $customer): array
    {
        $payload = [
            'template_id' => $template->id,
            'name' => $template->name,
            'version' => $template->version,
            'content_mode' => $template->content_mode,
            'content' => '',
            'content_en' => '',
            'content_ar' => '',
            'files' => [],
            'listing' => ['number' => $listing->listing_number, 'title' => app()->getLocale() === 'ar' ? ($listing->title_ar ?: $listing->title_en) : $listing->title_en],
            'country' => $this->contracts->countryPayload($listing->country),
        ];

        if ($template->isFileMode()) {
            $payload['files'] = $this->fileLinks($template, $listing);
            $payload['content_en'] = $this->contracts->fileHtml($payload['files'], 'en');
            $payload['content_ar'] = $this->contracts->fileHtml($payload['files'], 'ar');
        } else {
            $payload['content_en'] = $this->contracts->renderContent($template->content_en, $this->variablesFor($listing, $customer, $template, 'en'));
            $payload['content_ar'] = $this->contracts->renderContent($template->content_ar, $this->variablesFor($listing, $customer, $template, 'ar'));
        }

        $payload['content'] = app()->getLocale() === 'ar' ? $payload['content_ar'] : $payload['content_en'];

        return $payload;
    }

    /**
     * @return array<string, array{name: string, mime: string, size: int, url: string, inline: bool}>
     */
    public function fileLinks(ClassifiedContractTemplate $template, ClassifiedListing $listing): array
    {
        $listing->loadMissing('country');
        $links = [];
        $webStorefront = request()->routeIs('classifieds.*');

        foreach (['ar', 'en'] as $language) {
            $file = ($template->files ?? [])[$language] ?? null;

            if ($file) {
                $links[$language] = [
                    'name' => $file['name'],
                    'mime' => $file['mime'],
                    'size' => $file['size'],
                    'url' => $webStorefront
                        ? route('classifieds.customer-contract.file', ['country' => request()->route('country'), 'listing' => $listing->id, 'language' => $language])
                        : route('customer.listings.classified.customer-contract.file', [
                            'country' => $listing->country?->site_code,
                            'slug' => $listing->slug,
                            'language' => $language,
                        ]),
                    'inline' => $file['pdf_path'] !== null,
                ];
            }
        }

        return $links;
    }

    /**
     * @return array<string, string> variable values keyed without braces
     */
    public function variablesFor(ClassifiedListing $listing, Customer $customer, ClassifiedContractTemplate $template, string $language, string $signerName = ''): array
    {
        $language = $language === 'ar' ? 'ar' : 'en';
        $listing->loadMissing(['classifiedCategory', 'country', 'seller']);

        $seller = $listing->seller;
        $sellerName = $seller instanceof Vendor ? ($seller->store_name ?? $seller->business_name) : ($seller->name ?? '');
        $title = $language === 'ar' ? ($listing->title_ar ?: $listing->title_en) : $listing->title_en;
        $country = $listing->country;

        return [
            'customer.name' => (string) $customer->name,
            'customer.email' => (string) $customer->email,
            'customer.signer_name' => $signerName,
            'listing.number' => (string) $listing->listing_number,
            'listing.title' => (string) $title,
            'listing.price' => (string) $listing->price_formatted,
            'listing.purpose' => __('contracts.customer.purpose_'.CustomerContract::purposeFor($listing->listing_purpose), [], $language),
            'listing.country' => (string) ($language === 'ar' ? ($country?->name_ar ?: $country?->name_en) : $country?->name_en),
            'seller.name' => (string) $sellerName,
            'platform.name_en' => (string) config('app.name'),
            'platform.name_ar' => (string) config('app.name'),
            'category.name_en' => (string) ($listing->classifiedCategory?->name_en ?? ''),
            'category.name_ar' => (string) ($listing->classifiedCategory?->name_ar ?? ''),
            'contract.version' => (string) $template->version,
            'contract.date' => now()->format('d/m/Y'),
        ];
    }

    // ── Signing ───────────────────────────────────────────────────────────────

    /**
     * Records the customer's acceptance of the contract that applies to this advertisement.
     *
     * Idempotent and race-safe: the customer row is locked, an acceptance of the same version is returned as it is,
     * and the unique active key of the table rejects a concurrent duplicate. Earlier acceptances of an older version
     * for the same advertisement become `superseded` and keep their text.
     *
     * @return array{contract: CustomerContract, created: bool}
     *
     * @throws ValidationException when no customer contract applies to the advertisement
     */
    public function accept(Customer $customer, ClassifiedListing $listing, string $signerName, string $language, ?string $ipAddress, ?string $userAgent): array
    {
        $template = $this->templateFor($listing, $customer)
            ?? throw ValidationException::withMessages(['contract' => __('contracts.customer.not_required')]);

        $language = $language === 'ar' ? 'ar' : 'en';
        $signerName = trim($signerName);

        return DB::transaction(function () use ($customer, $listing, $template, $signerName, $language, $ipAddress, $userAgent) {
            Customer::whereKey($customer->id)->lockForUpdate()->first();

            if ($existing = $this->currentAcceptance($customer, $listing, $template)) {
                return ['contract' => $existing, 'created' => false];
            }

            $frozen = $this->freeze($customer, $listing, $template, $language, $signerName);

            try {
                $contract = DB::transaction(fn () => CustomerContract::create($frozen + [
                    'customer_id' => $customer->id,
                    'contract_template_id' => $template->id,
                    'template_version' => $template->version,
                    'classified_listing_id' => $listing->id,
                    'classified_category_id' => $listing->classified_category_id,
                    'country_id' => $listing->country_id,
                    'purpose' => CustomerContract::purposeFor($listing->listing_purpose),
                    'customer_name' => (string) $customer->name,
                    'customer_email' => $customer->email,
                    'listing_number' => $listing->listing_number,
                    'listing_title' => $listing->title_en,
                    'signer_name' => $signerName,
                    'signed_ip' => $ipAddress,
                    'signed_user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                    'signed_at' => now(),
                    'status' => CustomerContract::STATUS_ACTIVE,
                ]));
            } catch (UniqueConstraintViolationException) {
                return ['contract' => $this->currentAcceptance($customer, $listing, $template), 'created' => false];
            }

            CustomerContract::query()
                ->active()
                ->where('customer_id', $customer->id)
                ->where('classified_listing_id', $listing->id)
                ->where('id', '!=', $contract->id)
                ->update(['status' => CustomerContract::STATUS_SUPERSEDED]);

            $this->audit('customer_contract.signed', $contract, $template);

            return ['contract' => $contract, 'created' => true];
        });
    }

    /**
     * The immutable part of an acceptance: the text rendered for this customer and advertisement, or a private copy of
     * the uploaded document, plus the hash that proves what was accepted.
     *
     * @return array<string, mixed>
     */
    private function freeze(Customer $customer, ClassifiedListing $listing, ClassifiedContractTemplate $template, string $language, string $signerName): array
    {
        $variables = $this->variablesFor($listing, $customer, $template, $language, $signerName);

        if ($template->isFileMode()) {
            $file = $template->fileFor($language)
                ?? throw ValidationException::withMessages(['contract' => __('contracts.customer.no_document')]);
            $copy = $this->files->copyForCustomerSignature($file, $customer->id);

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

        $content = $this->contracts->renderContent($language === 'ar' ? $template->content_ar : $template->content_en, $variables);

        return [
            'language_signed' => $language,
            'content_mode' => ClassifiedContractTemplate::MODE_EDITOR,
            'rendered_content' => $content,
            'rendered_content_hash' => hash('sha256', $content.json_encode($variables)),
            'variables' => $variables,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function audit(string $event, CustomerContract $contract, ?ClassifiedContractTemplate $template, array $extra = []): void
    {
        $this->activity->log(
            description: $event.': '.($template?->name ?? $contract->contract_template_id).' v'.$contract->template_version,
            subject: $contract,
            causer: $contract->customer,
            properties: $extra + [
                'template_id' => $contract->contract_template_id,
                'version' => $contract->template_version,
                'customer_id' => $contract->customer_id,
                'listing_id' => $contract->classified_listing_id,
                'listing_number' => $contract->listing_number,
                'category_id' => $contract->classified_category_id,
                'country_id' => $contract->country_id,
                'purpose' => $contract->purpose,
                'language' => $contract->language_signed,
                'hash' => $contract->rendered_content_hash,
            ],
            logName: 'contracts',
            event: $event,
        );
    }
}
