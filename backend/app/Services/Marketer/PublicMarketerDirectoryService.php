<?php

namespace App\Services\Marketer;

use App\Enums\MarketerGender;
use App\Http\Resources\Public\MarketerBannerResource;
use App\Http\Resources\Public\MarketerCardResource;
use App\Models\Marketer;
use App\Models\MarketerJob;
use App\Models\MarketerProfile;
use App\Services\BannerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Query/filter logic behind GET /marketers (flat list, grouped sections, banners).
 * Never cached: filters, gender and banner schedules make every response unique.
 */
class PublicMarketerDirectoryService
{
    public const TYPES = ['influencer', 'affiliate', 'broker'];

    public const SECTION_LIMIT = 12;

    public function __construct(private readonly BannerService $banners) {}

    /**
     * Normalise the request into a filter array. Gender accepts male|female (men|women aliases).
     *
     * @return array{gender: ?string, type: ?string, search: ?string, sort: string}
     */
    public function filtersFrom(Request $request): array
    {
        $gender = match (strtolower((string) $request->query('gender'))) {
            'male', 'men', 'man' => MarketerGender::Male->value,
            'female', 'women', 'woman' => MarketerGender::Female->value,
            default => null,
        };
        $type = in_array($request->query('type'), self::TYPES, true) ? (string) $request->query('type') : null;
        $search = trim((string) $request->query('search', ''));
        $sort = in_array($request->query('sort'), ['top', 'newest', 'name'], true) ? (string) $request->query('sort') : 'top';

        return ['gender' => $gender, 'type' => $type, 'search' => $search !== '' ? $search : null, 'sort' => $sort];
    }

    /**
     * @param  array{gender: ?string, type: ?string, search: ?string, sort: string}  $filters
     */
    public function query(array $filters, ?string $countryId, ?string $typeOverride = null): Builder
    {
        $type = $typeOverride ?? $filters['type'];

        return MarketerProfile::query()
            ->whereNotNull('profile_slug')
            ->whereHas('marketer', fn ($q) => $q->where('global_status', 'active'))
            ->with([
                'marketer' => fn ($q) => $q->select('id', 'name', 'country_id', 'total_campaigns', 'total_conversions')
                    ->withCount(['classifiedListings as classified_count' => fn ($c) => $c->where('status', 'active')]),
                'marketer.marketerJobs',
                'bannerFile',
                'avatarFile',
            ])
            ->select('marketer_profiles.*')
            // Gender-less marketers stay visible on both tabs.
            ->when($filters['gender'], fn ($q, $g) => $q->where(fn ($w) => $w->where('marketer_profiles.gender', $g)->orWhereNull('marketer_profiles.gender')))
            ->when($type, fn ($q, $t) => $q->whereHas('marketer.marketerJobs', fn ($s) => $s->where('key', $t)))
            ->when($countryId, fn ($q, $c) => $q->whereHas('marketer', fn ($s) => $s->where('country_id', $c)))
            ->when($filters['search'], function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w
                    ->whereHas('marketer', fn ($m) => $m->where('name', 'like', $like))
                    ->orWhere('marketer_profiles.specialty_ar', 'like', $like)
                    ->orWhere('marketer_profiles.specialty_en', 'like', $like));
            })
            ->tap(fn ($q) => $this->applySort($q, $filters['sort']));
    }

    public function paginate(array $filters, ?string $countryId, int $perPage, Request $request): LengthAwarePaginator
    {
        $paginator = $this->query($filters, $countryId)->paginate($perPage);
        $paginator->setCollection($paginator->getCollection()->filter(fn (MarketerProfile $p) => $p->marketer !== null)->values());

        return $paginator;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(iterable $profiles, Request $request, ?string $typeKey = null): array
    {
        return collect($profiles)
            ->filter(fn (MarketerProfile $p) => $p->marketer !== null)
            ->map(fn (MarketerProfile $p) => (new MarketerCardResource($p, $typeKey))->resolve($request))
            ->values()
            ->all();
    }

    /**
     * One section per type (empty types omitted), each capped at SECTION_LIMIT, most popular first.
     *
     * @return list<array{type: string, label: string, total: int, items: list<array<string, mixed>>}>
     */
    public function sections(array $filters, ?string $countryId, Request $request): array
    {
        $locale = MarketerBannerResource::localeFor($request);
        $jobs = MarketerJob::whereIn('key', self::TYPES)->get()->keyBy('key');
        $sections = [];

        foreach ($filters['type'] ? [$filters['type']] : self::TYPES as $type) {
            $total = (clone $this->query($filters, $countryId, $type))->reorder()->count();
            if ($total === 0) {
                continue;
            }

            $profiles = $this->query($filters, $countryId, $type)->limit(self::SECTION_LIMIT)->get();
            $job = $jobs->get($type);

            $sections[] = [
                'type' => $type,
                'label' => $job ? ($locale === 'ar' ? $job->name_ar : $job->name_en) : $type,
                'total' => $total,
                'items' => $this->cards($profiles, $request, $type),
            ];
        }

        return $sections;
    }

    /**
     * Banners for the list page. Fresh on every request (never cached).
     *
     * @return array{hero: list<array<string, mixed>>, strip: list<array<string, mixed>>}
     */
    public function listBanners(?string $gender, ?string $countryId, Request $request): array
    {
        $resolve = fn (string $placement) => MarketerBannerResource::collection(
            $this->banners->activeForMarketerList($placement, $gender, $countryId)
        )->resolve($request);

        return [
            'hero' => $resolve('marketers_list_hero'),
            'strip' => $resolve('marketers_list_strip'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function profileBanners(Marketer $marketer, ?string $countryId, Request $request): array
    {
        return MarketerBannerResource::collection(
            $this->banners->activeForMarketerProfile($marketer, $countryId)
        )->resolve($request);
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('marketer_profiles.created_at'),
            'name' => $query->orderBy(Marketer::query()->select('name')->whereColumn('marketers.id', 'marketer_profiles.marketer_id')),
            default => $query->orderByDesc('marketer_profiles.total_conversions'),
        };
        $query->orderBy('marketer_profiles.id');
    }
}
