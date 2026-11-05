<?php

namespace App\Services\Marketer;

use App\Models\ClassifiedContractTemplate;
use App\Models\ExclusiveContract;
use App\Models\Marketer;
use App\Models\MarketerContract;
use App\Models\MarketerContractAcceptance;
use App\Models\MarketerContractVersion;
use App\Models\MarketerSellerContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a marketer profile shows about contracts, and the contract filters of the marketer lists.
 *
 * Three different kinds of contract meet on a profile and stay different records:
 *  - collaboration: seller <-> marketer contracts (templates that apply + the signatures sellers gave);
 *  - onboarding:    the marketer <-> platform contract (MarketerContract / version / acceptance);
 *  - exclusive:     exclusive classified contracts granted by the admin.
 *
 * Filters (all optional, server side): type (collaboration | onboarding | exclusive), status (active | pending | ended),
 * classified category, country, effective date range (from / to). A type that has no notion of the filter is hidden
 * instead of being shown unfiltered (e.g. the onboarding contract has no category).
 */
class MarketerProfileContractsService
{
    public const TYPES = ['collaboration', 'onboarding', 'exclusive'];

    public const STATUSES = ['active', 'pending', 'ended'];

    public function __construct(private readonly MarketerCollaborationContractService $collaboration) {}

    /**
     * @return array{type: ?string, status: ?string, category: ?string, country: ?string, from: ?string, to: ?string}
     */
    public function filtersFrom(Request $request): array
    {
        $date = fn (string $key): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) ? (string) $request->query($key) : null;
        $uuid = fn (string $key): ?string => preg_match('/^[0-9a-f-]{36}$/i', (string) $request->query($key)) ? (string) $request->query($key) : null;

        return [
            'type' => in_array($request->query('contract_type'), self::TYPES, true) ? (string) $request->query('contract_type') : null,
            'status' => in_array($request->query('contract_status'), self::STATUSES, true) ? (string) $request->query('contract_status') : null,
            'category' => $uuid('contract_category'),
            'country' => $uuid('contract_country'),
            'from' => $date('contract_from'),
            'to' => $date('contract_to'),
        ];
    }

    /**
     * @param  array<string, ?string>  $filters
     */
    public function hasFilters(array $filters): bool
    {
        return array_filter($filters) !== [];
    }

    private function wants(array $filters, string $type): bool
    {
        return $filters['type'] === null || $filters['type'] === $type;
    }

    // ── Collaboration: signatures ─────────────────────────────────────────────

    /**
     * @param  array<string, ?string>  $filters
     * @return Builder<MarketerSellerContract>
     */
    public function signedQuery(Marketer $marketer, array $filters): Builder
    {
        return MarketerSellerContract::query()
            ->where('marketer_id', $marketer->id)
            ->when($filters['status'] === 'active', fn (Builder $q) => $q->where('status', MarketerSellerContract::STATUS_ACTIVE))
            ->when($filters['status'] === 'ended', fn (Builder $q) => $q->where('status', '!=', MarketerSellerContract::STATUS_ACTIVE))
            ->when($filters['status'] === 'pending', fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when($filters['category'], fn (Builder $q, string $id) => $q->whereHas('template', fn (Builder $t) => $t->where('classified_category_id', $id)))
            ->when($filters['country'], fn (Builder $q, string $id) => $q->where('country_id', $id))
            ->when($filters['from'], fn (Builder $q, string $d) => $q->whereDate('signed_at', '>=', $d))
            ->when($filters['to'], fn (Builder $q, string $d) => $q->whereDate('signed_at', '<=', $d));
    }

    /**
     * @param  array<string, ?string>  $filters
     */
    public function signed(Marketer $marketer, array $filters, string $pageName = 'signed_page', int $perPage = 10): PaginatorContract
    {
        if (! $this->wants($filters, 'collaboration')) {
            return new LengthAwarePaginator([], 0, $perPage, 1, ['pageName' => $pageName]);
        }

        return $this->signedQuery($marketer, $filters)
            ->with(['vendor:id,store_name', 'template:id,name,classified_category_id', 'template.category:id,name_en,name_ar', 'country:id,name_en,name_ar,iso_code_2', 'campaign:id,title'])
            ->orderByDesc('signed_at')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }

    // ── Collaboration: templates that apply ───────────────────────────────────

    /**
     * Collaboration contracts that apply to the marketer (whether or not a seller signed one yet).
     *
     * @param  array<string, ?string>  $filters
     */
    public function applicable(Marketer $marketer, array $filters, Request $request, string $pageName = 'templates_page', int $perPage = 10): PaginatorContract
    {
        $items = $this->wants($filters, 'collaboration') && ! in_array($filters['status'], ['pending', 'ended'], true)
            ? $this->collaboration->applicableTo($marketer)
                ->when($filters['category'], fn (Collection $c, string $id) => $c->filter(fn (ClassifiedContractTemplate $t) => $t->classified_category_id === $id))
                ->when($filters['country'], fn (Collection $c, string $id) => $c->filter(fn (ClassifiedContractTemplate $t) => $t->country_id === $id))
                ->when($filters['from'], fn (Collection $c, string $d) => $c->filter(fn (ClassifiedContractTemplate $t) => $t->effective_at === null || $t->effective_at->toDateString() >= $d))
                ->when($filters['to'], fn (Collection $c, string $d) => $c->filter(fn (ClassifiedContractTemplate $t) => $t->effective_at === null || $t->effective_at->toDateString() <= $d))
                ->values()
            : collect();

        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => $request->url(),
            'pageName' => $pageName,
            'query' => $request->query(),
        ]);
    }

    // ── Onboarding contract ───────────────────────────────────────────────────

    /**
     * @param  array<string, ?string>  $filters
     * @return array{contract: MarketerContract, version: MarketerContractVersion, accepted: bool, accepted_at: ?Carbon}|null
     */
    public function onboarding(Marketer $marketer, array $filters): ?array
    {
        if (! $this->wants($filters, 'onboarding') || $filters['category'] || ($filters['country'] && $filters['country'] !== $marketer->country_id)) {
            return null;
        }

        $contract = $marketer->contract()->with('activeVersion')->first();
        $version = $contract?->activeVersion;

        if (! $contract || ! $version) {
            return null;
        }

        $acceptance = MarketerContractAcceptance::where('marketer_id', $marketer->id)
            ->whereNull('customer_id')
            ->where('marketer_contract_version_id', $version->id)
            ->first();

        $accepted = $acceptance !== null;

        if (($filters['status'] === 'active' && ! $accepted) || ($filters['status'] === 'pending' && $accepted) || $filters['status'] === 'ended') {
            return null;
        }

        $date = ($acceptance?->accepted_at ?? $version->created_at)?->toDateString();

        if (($filters['from'] && $date < $filters['from']) || ($filters['to'] && $date > $filters['to'])) {
            return null;
        }

        return ['contract' => $contract, 'version' => $version, 'accepted' => $accepted, 'accepted_at' => $acceptance?->accepted_at];
    }

    // ── Exclusive contracts ───────────────────────────────────────────────────

    /**
     * @param  array<string, ?string>  $filters
     * @return Builder<ExclusiveContract>
     */
    public function exclusiveQuery(Marketer $marketer, array $filters): Builder
    {
        return ExclusiveContract::query()
            ->where('marketer_id', $marketer->id)
            ->when($filters['status'] === 'active', fn (Builder $q) => $q->where('status', 'active')->where(fn (Builder $e) => $e->whereNull('ends_at')->orWhere('ends_at', '>=', now())))
            ->when($filters['status'] === 'pending', fn (Builder $q) => $q->where('status', 'pending'))
            ->when($filters['status'] === 'ended', fn (Builder $q) => $q->where(fn (Builder $e) => $e->whereIn('status', ['expired', 'revoked'])->orWhere(fn (Builder $a) => $a->where('status', 'active')->where('ends_at', '<', now()))))
            ->when($filters['category'], fn (Builder $q, string $id) => $q->where(fn (Builder $c) => $c->where('classified_category_id', $id)->orWhereHas('classifiedListing', fn (Builder $l) => $l->where('classified_category_id', $id))))
            ->when($filters['from'], fn (Builder $q, string $d) => $q->where(fn (Builder $e) => $e->whereNull('ends_at')->orWhereDate('ends_at', '>=', $d)))
            ->when($filters['to'], fn (Builder $q, string $d) => $q->whereDate('starts_at', '<=', $d));
    }

    /**
     * @param  array<string, ?string>  $filters
     */
    public function exclusive(Marketer $marketer, array $filters, string $pageName = 'exclusive_page', int $perPage = 10): PaginatorContract
    {
        if (! $this->wants($filters, 'exclusive') || ($filters['country'] && $filters['country'] !== $marketer->country_id)) {
            return new LengthAwarePaginator([], 0, $perPage, 1, ['pageName' => $pageName]);
        }

        return $this->exclusiveQuery($marketer, $filters)
            ->with(['classifiedCategory', 'classifiedListing', 'createdBy'])
            ->latest('starts_at')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }

    // ── Marketer lists ────────────────────────────────────────────────────────

    /**
     * Restricts a marketers query to those with at least one contract matching the filters (and the "has contract"
     * switch: yes / no). Uses EXISTS sub-queries, so the list stays one paginated query.
     *
     * @param  Builder<Marketer>  $marketers
     * @param  array<string, ?string>  $filters
     */
    public function filterMarketers(Builder $marketers, array $filters, ?string $hasContract = null): Builder
    {
        $any = $this->hasFilters($filters);

        if (! $any && ! in_array($hasContract, ['yes', 'no'], true)) {
            return $marketers;
        }

        $matching = function (Builder $query) use ($filters): void {
            $query->where(function (Builder $or) use ($filters) {
                if ($this->wants($filters, 'collaboration')) {
                    $or->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('marketer_seller_contracts as msc')
                        ->whereColumn('msc.marketer_id', 'marketers.id')
                        ->when($filters['status'] === 'active', fn ($q) => $q->where('msc.status', 'active'))
                        ->when($filters['status'] === 'ended', fn ($q) => $q->where('msc.status', '!=', 'active'))
                        ->when($filters['status'] === 'pending', fn ($q) => $q->whereRaw('1 = 0'))
                        ->when($filters['category'], fn ($q, $id) => $q->whereExists(fn ($t) => $t->selectRaw('1')->from('classified_contract_templates as ct')->whereColumn('ct.id', 'msc.contract_template_id')->where('ct.classified_category_id', $id)))
                        ->when($filters['country'], fn ($q, $id) => $q->where('msc.country_id', $id))
                        ->when($filters['from'], fn ($q, $d) => $q->whereDate('msc.signed_at', '>=', $d))
                        ->when($filters['to'], fn ($q, $d) => $q->whereDate('msc.signed_at', '<=', $d)));
                }

                if ($this->wants($filters, 'exclusive')) {
                    $or->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('exclusive_contracts as ec')
                        ->whereColumn('ec.marketer_id', 'marketers.id')
                        ->when($filters['status'] === 'active', fn ($q) => $q->where('ec.status', 'active')->where(fn ($e) => $e->whereNull('ec.ends_at')->orWhere('ec.ends_at', '>=', now())))
                        ->when($filters['status'] === 'pending', fn ($q) => $q->where('ec.status', 'pending'))
                        ->when($filters['status'] === 'ended', fn ($q) => $q->where(fn ($e) => $e->whereIn('ec.status', ['expired', 'revoked'])->orWhere(fn ($a) => $a->where('ec.status', 'active')->where('ec.ends_at', '<', now()))))
                        ->when($filters['category'], fn ($q, $id) => $q->where('ec.classified_category_id', $id))
                        ->when($filters['country'], fn ($q, $id) => $q->whereExists(fn ($m) => $m->selectRaw('1')->from('marketers as mk')->whereColumn('mk.id', 'ec.marketer_id')->where('mk.country_id', $id)))
                        ->when($filters['from'], fn ($q, $d) => $q->where(fn ($e) => $e->whereNull('ec.ends_at')->orWhereDate('ec.ends_at', '>=', $d)))
                        ->when($filters['to'], fn ($q, $d) => $q->whereDate('ec.starts_at', '<=', $d)));
                }

                if ($this->wants($filters, 'onboarding') && ! $filters['category'] && $filters['status'] !== 'ended') {
                    $or->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('marketer_contracts as mc')
                        ->join('marketer_contract_versions as mcv', fn ($j) => $j->on('mcv.marketer_contract_id', '=', 'mc.id')->where('mcv.is_active', true))
                        ->whereColumn('mc.marketer_id', 'marketers.id')
                        ->when($filters['status'] === 'active', fn ($q) => $q->whereExists(fn ($a) => $a->selectRaw('1')->from('marketer_contract_acceptances as mca')->whereColumn('mca.marketer_contract_version_id', 'mcv.id')->whereColumn('mca.marketer_id', 'marketers.id')->whereNull('mca.customer_id')))
                        ->when($filters['status'] === 'pending', fn ($q) => $q->whereNotExists(fn ($a) => $a->selectRaw('1')->from('marketer_contract_acceptances as mca')->whereColumn('mca.marketer_contract_version_id', 'mcv.id')->whereColumn('mca.marketer_id', 'marketers.id')->whereNull('mca.customer_id')))
                        ->when($filters['country'], fn ($q, $id) => $q->whereExists(fn ($m) => $m->selectRaw('1')->from('marketers as mk')->whereColumn('mk.id', 'mc.marketer_id')->where('mk.country_id', $id)))
                        ->when($filters['from'], fn ($q, $d) => $q->whereDate('mcv.created_at', '>=', $d))
                        ->when($filters['to'], fn ($q, $d) => $q->whereDate('mcv.created_at', '<=', $d)));
                }
            });
        };

        if ($hasContract === 'no') {
            return $marketers->whereNot(fn (Builder $q) => $matching($q));
        }

        return $marketers->where(fn (Builder $q) => $matching($q));
    }
}
