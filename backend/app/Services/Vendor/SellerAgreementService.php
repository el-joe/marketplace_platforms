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
 * The platform-wide Seller Agreement: one document for every vendor, signed once per version.
 *
 * All platform templates are versions of the same agreement. A new version reaches vendors in one of two ways:
 * click_wrap (vendors must accept before they continue) or notice (it takes effect after the notice period and
 * continued use is recorded as an implicit acceptance).
 */
class SellerAgreementService
{
    public function __construct(private readonly CategoryContractService $contracts) {}

    /**
     * The newest published version, in force or scheduled. This is what a vendor who never signed must sign.
     */
    public function latestPublished(): ?ClassifiedContractTemplate
    {
        return $this->published()->orderByDesc('version')->first();
    }

    /**
     * The newest published version that is already in force.
     */
    public function inForce(): ?ClassifiedContractTemplate
    {
        return $this->published()
            ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
            ->orderByDesc('version')
            ->first();
    }

    /**
     * A published version still inside its notice period.
     */
    public function upcoming(): ?ClassifiedContractTemplate
    {
        return $this->published()
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
     * version. Vendors on an older version only have to accept click-wrap versions; a notice version that came into
     * force is recorded as accepted by continued use.
     */
    public function pendingFor(Vendor $vendor): ?ClassifiedContractTemplate
    {
        $latest = $this->latestPublished();

        if (! $latest) {
            return null;
        }

        $signature = $this->activeSignature($vendor);

        if (! $signature) {
            return $latest;
        }

        $inForce = $this->inForce();

        if (! $inForce || $signature->contract_template_id === $inForce->id || $signature->template_version >= $inForce->version) {
            return null;
        }

        if ($inForce->acceptance_mode === ClassifiedContractTemplate::ACCEPTANCE_NOTICE) {
            $this->recordImplicitAcceptance($vendor, $inForce);

            return null;
        }

        return $inForce;
    }

    /**
     * @param  string  $method  registration|click_wrap
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
        $frozen = $this->contracts->freeze($vendor, $template, null, $language, $signerName);

        return DB::transaction(function () use ($vendor, $signer, $template, $frozen, $signerName, $ipAddress, $userAgent, $method) {
            VendorContract::where('vendor_id', $vendor->id)
                ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
                ->where('status', 'active')
                ->update(['status' => 'superseded']);

            return VendorContract::create($frozen + [
                'vendor_id' => $vendor->id,
                'vendor_category_enrollment_id' => null,
                'category_scope' => ClassifiedContractTemplate::SCOPE_PLATFORM,
                'contract_template_id' => $template->id,
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
     */
    public function recordImplicitAcceptance(Vendor $vendor, ClassifiedContractTemplate $template): ?VendorContract
    {
        $previous = $this->activeSignature($vendor);

        if (! $previous || $previous->template_version >= $template->version) {
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
     * Publishes this version. Click-wrap versions replace the old one immediately; notice versions are scheduled
     * and the old version stays in force until the notice period ends.
     */
    public function publish(ClassifiedContractTemplate $template): void
    {
        $isNotice = $template->acceptance_mode === ClassifiedContractTemplate::ACCEPTANCE_NOTICE
            && $this->published()->exists();

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
     * an older version. Safe to run repeatedly.
     *
     * @return int implicit acceptances recorded
     */
    public function applyEffective(): int
    {
        $inForce = $this->inForce();

        if (! $inForce) {
            return 0;
        }

        $this->retireOthers($inForce, olderThanVersion: true);

        if ($inForce->acceptance_mode !== ClassifiedContractTemplate::ACCEPTANCE_NOTICE) {
            return 0;
        }

        $recorded = 0;

        Vendor::query()
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

        return $recorded;
    }

    /**
     * Every vendor that has to act on this version: all vendors without a signature on it.
     *
     * @return Builder<Vendor>
     */
    public function vendorsToNotify(ClassifiedContractTemplate $template)
    {
        return Vendor::query()
            ->whereIn('global_status', ['active', 'under_review'])
            ->whereDoesntHave('contracts', fn ($query) => $query
                ->where('contract_template_id', $template->id)
                ->where('status', 'active'));
    }

    /**
     * @return Builder<ClassifiedContractTemplate>
     */
    private function published()
    {
        return ClassifiedContractTemplate::query()
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('is_published', true)
            ->where('is_active', true);
    }

    private function retireOthers(ClassifiedContractTemplate $template, bool $olderThanVersion = false): void
    {
        ClassifiedContractTemplate::where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('id', '!=', $template->id)
            ->when($olderThanVersion, fn ($query) => $query->where('version', '<', $template->version))
            ->update(['is_published' => false, 'is_active' => false]);
    }
}
