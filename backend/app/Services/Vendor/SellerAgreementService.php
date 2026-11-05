<?php

namespace App\Services\Vendor;

use App\Mail\SellerAgreementSignedMail;
use App\Models\ClassifiedContractTemplate;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The platform-wide Seller Agreement: one document per country for every vendor, signed once per version.
 *
 * Every country has its own lineage of versions (country_id on the template). Templates without a country are
 * the legacy country-agnostic lineage: they serve every country that has no agreement of its own, and they keep
 * working exactly as before until an admin publishes a country-specific agreement. Lineages never touch each
 * other: publishing, retiring, notices and implicit acceptances are all scoped to one country.
 *
 * A new version reaches vendors in one of two ways: click_wrap (vendors must accept before they continue) or
 * notice (it takes effect after the notice period and continued use is recorded as an implicit acceptance).
 *
 * Methods taking `$countryId` resolve which agreement serves that country (own versions, else the legacy ones);
 * passing null means "legacy country-agnostic lineage only".
 */
class SellerAgreementService
{
    public function __construct(
        private readonly CategoryContractService $contracts,
        private readonly ContractResolver $resolver,
    ) {}

    /**
     * The newest published version serving a country, in force or scheduled. This is what a vendor who never
     * signed must sign.
     */
    public function latestPublished(?string $countryId = null): ?ClassifiedContractTemplate
    {
        return $this->resolver->platformQuery($countryId)->orderByDesc('version')->first();
    }

    /**
     * The newest published version serving a country that is already in force.
     */
    public function inForce(?string $countryId = null): ?ClassifiedContractTemplate
    {
        return $this->resolver->platformQuery($countryId)
            ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
            ->orderByDesc('version')
            ->first();
    }

    /**
     * A published version serving a country that is still inside its notice period.
     */
    public function upcoming(?string $countryId = null): ?ClassifiedContractTemplate
    {
        return $this->resolver->platformQuery($countryId)
            ->where('effective_at', '>', now())
            ->orderByDesc('version')
            ->first();
    }

    public function activeSignature(Vendor $vendor): ?VendorContract
    {
        return VendorContract::where('vendor_id', $vendor->id)
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('status', 'active')
            ->latest('signed_at')
            ->first();
    }

    /**
     * The version this vendor must accept now, or null. Vendors who never signed must accept the newest published
     * version of their country. Vendors on an older version of the same lineage only have to accept click-wrap
     * versions; a notice version that came into force is recorded as accepted by continued use. A vendor who signed
     * a different lineage (for example the legacy agreement, while their country now has its own) must accept the
     * agreement of their country explicitly.
     */
    public function pendingFor(Vendor $vendor): ?ClassifiedContractTemplate
    {
        $countryId = $vendor->country_id;
        $latest = $this->latestPublished($countryId);

        if (! $latest) {
            return null;
        }

        $signature = $this->activeSignature($vendor);

        if (! $signature) {
            return $latest;
        }

        $inForce = $this->inForce($countryId);

        if (! $inForce) {
            return null;
        }

        $signedTemplate = $signature->contractTemplate;

        if ($signedTemplate && $signedTemplate->country_id !== $inForce->country_id) {
            return $inForce;
        }

        if ($signature->contract_template_id === $inForce->id || $signature->template_version >= $inForce->version) {
            return null;
        }

        if ($inForce->acceptance_mode === ClassifiedContractTemplate::ACCEPTANCE_NOTICE) {
            $this->recordImplicitAcceptance($vendor, $inForce);

            return null;
        }

        return $inForce;
    }

    /**
     * Signs the agreement. Idempotent: a double submit (or a retry after a timeout) returns the signature that
     * already exists for this template instead of creating a duplicate. The vendor row is locked while signing so
     * two concurrent requests cannot both write one, and a database key backs this up.
     *
     * @param  string  $method  registration|click_wrap|implicit
     */
    public function sign(
        Vendor $vendor,
        ?VendorAdmin $signer,
        ClassifiedContractTemplate $template,
        string $language,
        string $signerName,
        ?string $ipAddress,
        ?string $userAgent,
        string $method = 'click_wrap',
    ): VendorContract {
        if ($existing = $this->existingSignature($vendor, $template)) {
            return $existing;
        }

        $frozen = $this->contracts->freeze($vendor, $template, null, $language, $signerName);

        return DB::transaction(function () use ($vendor, $signer, $template, $frozen, $signerName, $ipAddress, $userAgent, $method) {
            Vendor::whereKey($vendor->id)->lockForUpdate()->first();

            if ($existing = $this->existingSignature($vendor, $template)) {
                return $existing;
            }

            VendorContract::where('vendor_id', $vendor->id)
                ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
                ->where('status', 'active')
                ->update(['status' => 'superseded']);

            return VendorContract::create($frozen + [
                'vendor_id' => $vendor->id,
                'vendor_category_enrollment_id' => null,
                'category_scope' => ClassifiedContractTemplate::SCOPE_PLATFORM,
                'contract_template_id' => $template->id,
                'country_id' => $vendor->country_id,
                'template_version' => $template->version,
                'signed_by_vendor_admin_id' => $signer?->id,
                'signer_name' => $signerName,
                'signed_ip' => $ipAddress,
                'signed_user_agent' => $userAgent,
                'signed_at' => now(),
                'status' => 'active',
                'acceptance_method' => $method,
            ]);
        });
    }

    private function existingSignature(Vendor $vendor, ClassifiedContractTemplate $template): ?VendorContract
    {
        return VendorContract::where('vendor_id', $vendor->id)
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('contract_template_id', $template->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Emails the vendor a copy of what they signed, to the email they registered with. Never blocks signing.
     */
    public function sendSignedCopy(VendorContract $contract): void
    {
        try {
            $vendor = $contract->vendor;

            Mail::to($vendor->email)->queue(new SellerAgreementSignedMail($contract));
            $contract->update(['emailed_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Seller agreement copy email failed: '.$e->getMessage(), ['contract_id' => $contract->id]);
        }
    }

    /**
     * Continued use after the notice period counts as acceptance. Keeps the language the vendor signed before.
     * Only applies inside one lineage: moving to another country's agreement is never implicit.
     */
    public function recordImplicitAcceptance(Vendor $vendor, ClassifiedContractTemplate $template): ?VendorContract
    {
        $previous = $this->activeSignature($vendor);

        if (! $previous || $previous->template_version >= $template->version) {
            return null;
        }

        if ($previous->contractTemplate && $previous->contractTemplate->country_id !== $template->country_id) {
            return null;
        }

        return $this->sign(
            $vendor,
            null,
            $template,
            $previous->language_signed,
            $previous->signer_name,
            null,
            null,
            'implicit',
        );
    }

    /**
     * Publishes this version for its own country only. Click-wrap versions replace the old version of that
     * country immediately; notice versions are scheduled and the old version stays in force until the notice
     * period ends. Other countries' agreements are never touched.
     */
    public function publish(ClassifiedContractTemplate $template): void
    {
        $isNotice = $template->acceptance_mode === ClassifiedContractTemplate::ACCEPTANCE_NOTICE
            && $this->lineage($template->country_id)->exists();

        DB::transaction(function () use ($template, $isNotice) {
            if (! $isNotice) {
                $this->retireOthers($template);
            }

            $template->update([
                'is_published' => true,
                'is_active' => true,
                'acceptance_mode' => $isNotice ? ClassifiedContractTemplate::ACCEPTANCE_NOTICE : ClassifiedContractTemplate::ACCEPTANCE_CLICK_WRAP,
                'published_at' => now(),
                'effective_at' => $isNotice ? now()->addDays(max(1, (int) $template->notice_days)) : now(),
            ]);
        });
    }

    /**
     * Retires versions replaced by one that is now in force and records the implicit acceptance for vendors on
     * an older version, separately for every country lineage. Safe to run repeatedly.
     *
     * @return int implicit acceptances recorded
     */
    public function applyEffective(): int
    {
        $recorded = 0;

        $countryIds = ClassifiedContractTemplate::query()
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('is_published', true)->where('is_active', true)
            ->distinct()->pluck('country_id');

        foreach ($countryIds as $countryId) {
            $inForce = $this->lineage($countryId)
                ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
                ->orderByDesc('version')
                ->first();

            if (! $inForce) {
                continue;
            }

            $this->retireOthers($inForce, olderThanVersion: true);

            if ($inForce->acceptance_mode !== ClassifiedContractTemplate::ACCEPTANCE_NOTICE) {
                continue;
            }

            $this->vendorsInLineage($countryId)
                ->whereIn('global_status', ['active', 'under_review'])
                ->whereHas('contracts', fn ($query) => $query
                    ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
                    ->where('status', 'active')
                    ->where('template_version', '<', $inForce->version))
                ->chunkById(100, function ($vendors) use ($inForce, &$recorded) {
                    foreach ($vendors as $vendor) {
                        if ($this->recordImplicitAcceptance($vendor, $inForce)) {
                            $recorded++;
                        }
                    }
                });
        }

        return $recorded;
    }

    /**
     * Every vendor served by this template's country lineage that has to act on this version: all of them
     * without a signature on it.
     *
     * @return Builder<Vendor>
     */
    public function vendorsToNotify(ClassifiedContractTemplate $template)
    {
        return $this->vendorsInLineage($template->country_id)
            ->whereIn('global_status', ['active', 'under_review'])
            ->whereDoesntHave('contracts', fn ($query) => $query
                ->where('contract_template_id', $template->id)
                ->where('status', 'active'));
    }

    /**
     * Vendors this template's agreement lineage serves (its country's vendors, or for the legacy lineage every
     * vendor of a country without an agreement of its own).
     *
     * @return Builder<Vendor>
     */
    public function vendorsServedBy(ClassifiedContractTemplate $template): Builder
    {
        return $this->vendorsInLineage($template->country_id);
    }

    /**
     * Vendors an agreement lineage serves: a country's own lineage serves vendors of that country; the legacy
     * (null) lineage serves vendors of every country that has no published agreement of its own, and vendors
     * without a country.
     *
     * @return Builder<Vendor>
     */
    private function vendorsInLineage(?string $countryId): Builder
    {
        if ($countryId) {
            return Vendor::query()->where('country_id', $countryId);
        }

        $ownLineages = ClassifiedContractTemplate::query()
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('is_published', true)->where('is_active', true)
            ->whereNotNull('country_id')
            ->select('country_id');

        return Vendor::query()->where(fn (Builder $query) => $query->whereNull('country_id')->orWhereNotIn('country_id', $ownLineages));
    }

    /**
     * Published, active platform versions of exactly one country lineage (null = legacy), without the fallback.
     *
     * @return Builder<ClassifiedContractTemplate>
     */
    private function lineage(?string $countryId)
    {
        return ClassifiedContractTemplate::query()
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('is_published', true)
            ->where('is_active', true)
            ->when($countryId, fn (Builder $query) => $query->where('country_id', $countryId), fn (Builder $query) => $query->whereNull('country_id'));
    }

    /**
     * Retires the other versions of this template's own country lineage. Other countries are never affected.
     */
    private function retireOthers(ClassifiedContractTemplate $template, bool $olderThanVersion = false): void
    {
        ClassifiedContractTemplate::where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->when($template->country_id, fn (Builder $query) => $query->where('country_id', $template->country_id), fn (Builder $query) => $query->whereNull('country_id'))
            ->where('id', '!=', $template->id)
            ->when($olderThanVersion, fn ($query) => $query->where('version', '<', $template->version))
            ->update(['is_published' => false, 'is_active' => false]);
    }
}
