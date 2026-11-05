<?php

namespace App\Services\Marketer;

use App\Enums\CommissionVertical;
use App\Exceptions\CollaborationContractRequiredException;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractDisplayDestination;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerSellerContract;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\ActivityLoggerService;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractFileService;
use App\Support\Marketer\CampaignSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seller <-> marketer collaboration contracts (kind seller_marketer, scope `marketer`).
 *
 * This is its own business workflow: it shares template storage / versioning with the seller-platform contracts but has
 * its own resolution, signing record (marketer_seller_contracts) and enforcement point (a seller creating or approving a
 * marketer campaign). Resolution:
 *
 *  1. the templates in force (published, active, effective) of the `marketer` scope;
 *  2. that apply to the marketer: a template linked to specific marketers applies to them only; otherwise it applies
 *     to every marketer who holds a marketer job named by one of its Display On destinations;
 *  3. in the seller's country: the country's own template of a contract name wins; the country-agnostic (legacy,
 *     NULL country) one of the same name serves countries without their own; another country's is never used;
 *  4. optionally narrowed to a classified category (and its sub-categories) of the promoted listing;
 *  5. newest version per contract name.
 *
 * When nothing applies the collaboration goes ahead unchanged: a country or marketer type without a configured
 * contract is not blocked (the admin templates page lists such marketer types).
 */
class MarketerCollaborationContractService
{
    public function __construct(
        private readonly CategoryContractService $contracts,
        private readonly ContractFileService $files,
        private readonly ActivityLoggerService $activity,
    ) {}

    // ── Resolution ────────────────────────────────────────────────────────────

    /**
     * @return Builder<ClassifiedContractTemplate>
     */
    public function inForceQuery(): Builder
    {
        return ClassifiedContractTemplate::query()
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_MARKETER)
            ->inForce();
    }

    /**
     * Contracts that apply to marketers holding these job types (and/or this specific marketer) in a country.
     *
     * @param  list<string>  $jobKeys  marketer_jobs.key values
     * @param  list<string>|null  $categoryChainIds  classified category plus ancestors of the promoted listing; null = ignore
     *                                               the category (profile display), [] = a non-classified subject
     * @return Collection<int, ClassifiedContractTemplate>
     */
    public function resolve(array $jobKeys, ?string $marketerId, ?string $countryId, ?array $categoryChainIds = null): Collection
    {
        $candidates = $this->inForceQuery()
            ->where(function (Builder $applies) use ($jobKeys, $marketerId) {
                $applies->where(function (Builder $byType) use ($jobKeys) {
                    $byType->whereDoesntHave('marketers')
                        ->whereHas('displayDestinations', fn (Builder $d) => $d->where('is_active', true)
                            ->where('target_type', ContractDisplayDestination::TARGET_MARKETER_JOB)
                            ->whereIn('marketer_job_key', $jobKeys ?: ['']));
                });

                if ($marketerId) {
                    $applies->orWhereHas('marketers', fn (Builder $m) => $m->whereKey($marketerId));
                }
            })
            ->when($countryId, fn (Builder $q) => $q->where(fn (Builder $c) => $c->whereNull('country_id')->orWhere('country_id', $countryId)), fn (Builder $q) => $q->whereNull('country_id'))
            ->when($categoryChainIds !== null, fn (Builder $q) => $q->where(fn (Builder $c) => $c->whereNull('classified_category_id')->orWhereIn('classified_category_id', $categoryChainIds)))
            ->with(['displayDestinations', 'country:id,name_en,name_ar,iso_code_2', 'category:id,name_en,name_ar'])
            ->get();

        return $this->latestPerContract($candidates, $countryId);
    }

    /**
     * Contracts that apply to a marketer's profile (all of his job types, his own country).
     *
     * @return Collection<int, ClassifiedContractTemplate>
     */
    public function applicableTo(Marketer $marketer): Collection
    {
        $marketer->loadMissing('marketerJobs');

        return $this->resolve($marketer->marketerJobs->pluck('key')->all(), $marketer->id, $marketer->country_id);
    }

    /**
     * Contracts shown in the general contracts section for sellers of a country.
     *
     * @return Collection<int, ClassifiedContractTemplate>
     */
    public function forDestination(string $targetType, ?string $countryId): Collection
    {
        $candidates = $this->inForceQuery()
            ->whereHas('displayDestinations', fn (Builder $d) => $d->where('is_active', true)->where('target_type', $targetType))
            ->when($countryId, fn (Builder $q) => $q->where(fn (Builder $c) => $c->whereNull('country_id')->orWhere('country_id', $countryId)), fn (Builder $q) => $q->whereNull('country_id'))
            ->with(['displayDestinations', 'country:id,name_en,name_ar,iso_code_2', 'category:id,name_en,name_ar'])
            ->get();

        return $this->latestPerContract($candidates, $countryId);
    }

    /**
     * One template per contract name: the country's own lineage when the country has one, else the legacy lineage,
     * newest version first.
     *
     * @param  Collection<int, ClassifiedContractTemplate>  $candidates
     * @return Collection<int, ClassifiedContractTemplate>
     */
    private function latestPerContract(Collection $candidates, ?string $countryId): Collection
    {
        return $candidates->groupBy('name')->map(function (Collection $versions) use ($countryId) {
            $own = $countryId ? $versions->where('country_id', $countryId) : collect();
            $pool = $own->isNotEmpty() ? $own : $versions->whereNull('country_id');

            return $pool->sortByDesc('version')->first();
        })->filter()->sortBy('name')->values();
    }

    /**
     * Marketer jobs (destinations) that are active but have no contract in force at all: sellers working with them are
     * not asked to sign anything. Shown to the admin so a missing contract is visible, never silently ignored.
     *
     * @return Collection<int, ContractDisplayDestination>
     */
    public function uncoveredMarketerDestinations(): Collection
    {
        return ContractDisplayDestination::active()
            ->where('target_type', ContractDisplayDestination::TARGET_MARKETER_JOB)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ContractDisplayDestination $destination) => ! $this->inForceQuery()
                ->whereHas('displayDestinations', fn (Builder $d) => $d->whereKey($destination->id))
                ->exists())
            ->values();
    }

    // ── Requirements (what a seller must sign for a collaboration) ────────────

    /**
     * @param  list<string>  $marketerIds  marketers the seller picked
     * @param  list<string>  $openJobKeys  job types of an open call (affiliate / broker) the campaign is open to
     * @return Collection<string, array{template: ClassifiedContractTemplate, marketers: Collection<int, Marketer>, open_job_keys: list<string>}> keyed by template id
     */
    public function requirementsFor(Vendor $vendor, array $marketerIds, array $openJobKeys = [], ?string $classifiedCategoryId = null, bool $classifiedSubject = false): Collection
    {
        $countryId = $vendor->country_id;
        $chain = null;

        if ($classifiedSubject) {
            $category = $classifiedCategoryId ? ClassifiedCategory::find($classifiedCategoryId) : null;
            $chain = $category ? collect($this->contracts->resolver()->chain($category))->pluck('id')->all() : [];
        } else {
            $chain = [];
        }

        $required = collect();

        $marketers = $marketerIds === [] ? collect() : Marketer::whereIn('id', $marketerIds)->with('marketerJobs')->get();

        foreach ($marketers as $marketer) {
            foreach ($this->resolve($marketer->marketerJobs->pluck('key')->all(), $marketer->id, $countryId, $chain) as $template) {
                $entry = $required->get($template->id, ['template' => $template, 'marketers' => collect(), 'open_job_keys' => []]);
                $entry['marketers']->push($marketer);
                $required->put($template->id, $entry);
            }
        }

        foreach ($openJobKeys as $jobKey) {
            foreach ($this->resolve([$jobKey], null, $countryId, $chain) as $template) {
                $entry = $required->get($template->id, ['template' => $template, 'marketers' => collect(), 'open_job_keys' => []]);
                $entry['open_job_keys'][] = $jobKey;
                $required->put($template->id, $entry);
            }
        }

        return $required;
    }

    /**
     * Requirements of a wizard submission: the picked marketers plus, for a campaign open to affiliates, the
     * affiliate and broker types.
     *
     * @param  list<string>  $marketerIds
     * @return Collection<string, array{template: ClassifiedContractTemplate, marketers: Collection<int, Marketer>, open_job_keys: list<string>}>
     */
    public function requirementsForSubject(Vendor $vendor, array $marketerIds, bool $openToAffiliates, CampaignSubject $subject): Collection
    {
        $classified = $subject->vertical === CommissionVertical::Classified;

        return $this->requirementsFor($vendor, $marketerIds, $openToAffiliates ? ['affiliate', 'broker'] : [], $classified ? $subject->categoryId : null, $classified);
    }

    /**
     * Requirements for the marketer who asked to promote a seller's listing (the seller approves and signs).
     *
     * @return Collection<string, array{template: ClassifiedContractTemplate, marketers: Collection<int, Marketer>, open_job_keys: list<string>}>
     */
    public function requirementsForRequest(Vendor $vendor, MarketerCampaign $campaign): Collection
    {
        if (! $campaign->requested_by_marketer_id) {
            return collect();
        }

        $classified = $campaign->campaign_category === 'classified';
        $categoryId = $classified ? $campaign->classifiedListing?->classified_category_id : null;

        return $this->requirementsFor($vendor, [$campaign->requested_by_marketer_id], [], $categoryId, $classified);
    }

    /**
     * Throws unless the seller accepted every required contract. Only ids the server itself resolved count: a
     * client-supplied id of any other template is ignored.
     *
     * @param  Collection<string, array{template: ClassifiedContractTemplate}>  $requirements
     * @param  list<string>  $acceptedIds
     *
     * @throws CollaborationContractRequiredException
     * @throws ValidationException
     */
    public function assertAccepted(Collection $requirements, array $acceptedIds, ?string $signerName): void
    {
        if ($requirements->isEmpty()) {
            return;
        }

        $missing = $requirements->reject(fn (array $entry, string $id) => in_array($id, $acceptedIds, true));

        if ($missing->isNotEmpty()) {
            throw CollaborationContractRequiredException::missing(
                $missing->keys()->all(),
                $missing->map(fn (array $entry) => $entry['template']->name.' (v'.$entry['template']->version.')')->values()->all(),
            );
        }

        if (trim((string) $signerName) === '') {
            throw ValidationException::withMessages(['collab_signer_name' => __('contracts.marketer.signer_required')]);
        }
    }

    /**
     * What the wizard shows for each required contract.
     *
     * @param  Collection<string, array{template: ClassifiedContractTemplate, marketers: Collection<int, Marketer>, open_job_keys: list<string>}>  $requirements
     * @return list<array<string, mixed>>
     */
    public function present(Collection $requirements, Vendor $vendor): array
    {
        return $requirements->map(function (array $entry) use ($vendor) {
            $template = $entry['template'];
            $doc = $this->contracts->present($template, $vendor, null);

            return [
                'template_id' => $template->id,
                'name' => $template->name,
                'version' => $template->version,
                'content_mode' => $template->content_mode,
                'content' => app()->getLocale() === 'ar' ? $doc['content_ar'] : $doc['content_en'],
                'marketers' => $entry['marketers']->pluck('name')->values()->all(),
                'open_types' => $entry['open_job_keys'],
            ];
        })->values()->all();
    }

    // ── Signing ───────────────────────────────────────────────────────────────

    /**
     * Records the seller's signature on every required contract for a campaign. Idempotent and race-safe: the vendor row
     * is locked, an already signed (seller, campaign, marketer, template) is left as it is, and the unique active key of
     * the table rejects a concurrent duplicate. Run inside the caller's transaction so the campaign and its signatures
     * are saved together.
     *
     * @param  Collection<string, array{template: ClassifiedContractTemplate, marketers: Collection<int, Marketer>, open_job_keys: list<string>}>  $requirements
     * @return Collection<int, MarketerSellerContract> rows created by this call
     */
    public function sign(
        Vendor $vendor,
        ?VendorAdmin $signer,
        MarketerCampaign $campaign,
        Collection $requirements,
        string $signerName,
        string $language,
        ?string $ipAddress,
        ?string $userAgent,
    ): Collection {
        $language = $language === 'ar' ? 'ar' : 'en';
        $created = collect();

        if ($requirements->isEmpty()) {
            return $created;
        }

        DB::transaction(function () use ($vendor, $signer, $campaign, $requirements, $signerName, $language, $ipAddress, $userAgent, &$created) {
            Vendor::whereKey($vendor->id)->lockForUpdate()->first();

            foreach ($requirements as $entry) {
                /** @var ClassifiedContractTemplate $template */
                $template = $entry['template'];
                $fileCopy = null;

                $targets = $entry['marketers']->map(fn (Marketer $m) => [$m, null])->all();

                foreach (array_unique($entry['open_job_keys']) as $jobKey) {
                    $targets[] = [null, $jobKey];
                }

                if ($targets === []) {
                    continue;
                }

                // Only the first open-call row of a template needs the type; one row covers every open type.
                $openTargets = array_values(array_filter($targets, fn (array $t) => $t[0] === null));
                $namedTargets = array_values(array_filter($targets, fn (array $t) => $t[0] !== null));

                $rows = $namedTargets;
                if ($openTargets !== []) {
                    $rows[] = [null, implode(',', array_column($openTargets, 1))];
                }

                foreach ($rows as [$marketer, $jobKey]) {
                    if ($this->existingActive($vendor->id, $campaign->id, $marketer?->id, $template->id)) {
                        continue;
                    }

                    $frozen = $this->freeze($vendor, $template, $campaign, $marketer, $language, $signerName, $fileCopy);

                    try {
                        $row = DB::transaction(fn () => MarketerSellerContract::create($frozen + [
                            'contract_template_id' => $template->id,
                            'template_version' => $template->version,
                            'vendor_id' => $vendor->id,
                            'marketer_id' => $marketer?->id,
                            'marketer_campaign_id' => $campaign->id,
                            'country_id' => $vendor->country_id,
                            'marketer_job_key' => $jobKey,
                            'signed_by_vendor_admin_id' => $signer?->id,
                            'signer_name' => $signerName,
                            'signed_ip' => $ipAddress,
                            'signed_user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                            'signed_at' => now(),
                            'status' => MarketerSellerContract::STATUS_ACTIVE,
                        ]));
                    } catch (UniqueConstraintViolationException) {
                        continue;
                    }

                    $created->push($row);
                    $this->auditSigned($row, $template);
                }
            }
        });

        return $created;
    }

    /**
     * An invitation was dispatched for a campaign: a marketer who joined an open call is covered by the seller's open-call
     * signature, so the contract shows up in his profile. Does nothing when the seller signed no open call.
     */
    public function coverInvitation(MarketerCampaign $campaign, MarketerCampaignInvitation $invitation): void
    {
        if (! $campaign->vendor_id) {
            return;
        }

        $openRows = MarketerSellerContract::query()
            ->where('marketer_campaign_id', $campaign->id)
            ->whereNull('marketer_id')
            ->active()
            ->get();

        if ($openRows->isEmpty()) {
            return;
        }

        $marketer = Marketer::with('marketerJobs')->find($invitation->marketer_id);

        if (! $marketer) {
            return;
        }

        $jobKeys = $marketer->marketerJobs->pluck('key')->all();

        foreach ($openRows as $open) {
            $types = array_filter(explode(',', (string) $open->marketer_job_key));

            if (array_intersect($types, $jobKeys) === [] || $this->existingActive($open->vendor_id, $campaign->id, $marketer->id, $open->contract_template_id)) {
                continue;
            }

            try {
                $copy = MarketerSellerContract::create([
                    'parent_id' => $open->id,
                    'marketer_id' => $marketer->id,
                    'marketer_job_key' => implode(',', array_intersect($types, $jobKeys)),
                    'status' => MarketerSellerContract::STATUS_ACTIVE,
                ] + $open->only([
                    'contract_template_id', 'template_version', 'vendor_id', 'marketer_campaign_id', 'country_id', 'language_signed',
                    'content_mode', 'rendered_content', 'rendered_content_hash', 'variables', 'signed_file_path', 'signed_file_name',
                    'signed_file_mime', 'signed_by_vendor_admin_id', 'signer_name', 'signed_ip', 'signed_user_agent', 'signed_at',
                ]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $this->auditSigned($copy, $open->template, ['covered_by_open_call' => $open->id]);
        }
    }

    private function existingActive(string $vendorId, string $campaignId, ?string $marketerId, string $templateId): bool
    {
        return MarketerSellerContract::query()
            ->where('vendor_id', $vendorId)
            ->where('marketer_campaign_id', $campaignId)
            ->where('contract_template_id', $templateId)
            ->when($marketerId, fn (Builder $q) => $q->where('marketer_id', $marketerId), fn (Builder $q) => $q->whereNull('marketer_id'))
            ->active()
            ->exists();
    }

    /**
     * The immutable part of a signature (text rendered for this seller and marketer, or a private copy of the uploaded
     * document, plus the hash that proves what was signed).
     *
     * @param  array{path: string, name: string, mime: string}|null  $fileCopy  shared by every row of one template
     * @return array<string, mixed>
     */
    private function freeze(Vendor $vendor, ClassifiedContractTemplate $template, MarketerCampaign $campaign, ?Marketer $marketer, string $language, string $signerName, ?array &$fileCopy): array
    {
        $variables = $this->contracts->variablesFor($vendor, null, $template, $signerName)
            + ['campaign.title' => (string) ($campaign->title ?? '')];

        if ($marketer) {
            $marketer->loadMissing('marketerJobs');
            $variables += [
                'marketer.name' => (string) $marketer->name,
                'marketer.type' => (string) ($marketer->typeLabel() ?: ''),
            ];
        }

        if ($template->isFileMode()) {
            $file = $template->fileFor($language)
                ?? throw ValidationException::withMessages(['collaboration_contracts' => 'This contract has no uploaded document.']);
            $fileCopy ??= $this->files->copyForSignature($file, $vendor->id);

            return [
                'language_signed' => $file['language'],
                'content_mode' => ClassifiedContractTemplate::MODE_FILE,
                'rendered_content' => '',
                'rendered_content_hash' => $file['sha256'],
                'variables' => $variables,
                'signed_file_path' => $fileCopy['path'],
                'signed_file_name' => $fileCopy['name'],
                'signed_file_mime' => $fileCopy['mime'],
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
    private function auditSigned(MarketerSellerContract $contract, ?ClassifiedContractTemplate $template, array $extra = []): void
    {
        $this->activity->log(
            description: 'marketer_contract.signed: '.($template?->name ?? $contract->contract_template_id).' v'.$contract->template_version,
            subject: $contract,
            causer: $contract->signedBy ?? $contract->vendor,
            properties: $extra + [
                'template_id' => $contract->contract_template_id,
                'version' => $contract->template_version,
                'vendor_id' => $contract->vendor_id,
                'marketer_id' => $contract->marketer_id,
                'campaign_id' => $contract->marketer_campaign_id,
                'country_id' => $contract->country_id,
                'hash' => $contract->rendered_content_hash,
            ],
            logName: 'contracts',
            event: 'marketer_contract.signed',
        );
    }

    // ── Publishing ────────────────────────────────────────────────────────────

    /**
     * Makes this version the one in force for its contract name and country, retiring the previous version of the same
     * lineage (never another country's). Signatures collected on the old version are untouched.
     */
    public function publish(ClassifiedContractTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            ClassifiedContractTemplate::where('name', $template->name)
                ->where('category_scope', ClassifiedContractTemplate::SCOPE_MARKETER)
                ->when($template->country_id, fn (Builder $q) => $q->where('country_id', $template->country_id), fn (Builder $q) => $q->whereNull('country_id'))
                ->where('id', '!=', $template->id)
                ->where('is_published', true)
                ->update(['is_published' => false, 'is_active' => false]);

            $template->update([
                'is_published' => true,
                'is_active' => true,
                'is_country_default' => false,
                'published_at' => now(),
                'effective_at' => now(),
            ]);
        });
    }
}
