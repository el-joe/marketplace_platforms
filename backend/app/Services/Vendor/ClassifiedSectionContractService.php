<?php

namespace App\Services\Vendor;

use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Services\ActivityLoggerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Which classified sections (categories) a classified contract template applies to.
 *
 * There is no second link table. The association is the one the ContractResolver already reads:
 *  - a country contract is linked through `contract_country_assignments` (classified_category_id + country_id,
 *    unique per pair, so a section can never have two contracts for the same country);
 *  - a country-agnostic (legacy) contract is linked through `classified_categories.contract_template_id`.
 * The category form writes the same rows, so the template form and the category form are always in sync.
 *
 * A link belongs to a contract lineage (same name, scope and country: its versions), not to one version. New links go
 * to the lineage's published version, or to the draft while nothing is published yet; publishing a newer version moves
 * the links along (CategoryContractService::publish). A section covers its sub-sections (resolver parent inheritance)
 * unless a sub-section has a contract of its own. Links to a draft are inert until it is published, and the
 * "requires a contract" switch of a section only turns on when a published contract is linked, so linking a draft
 * never blocks sellers.
 */
class ClassifiedSectionContractService
{
    public function __construct(
        private readonly CategoryContractService $contracts,
        private readonly ActivityLoggerService $activity,
    ) {}

    /**
     * Ids of the sections linked to this template's lineage.
     *
     * @return list<string>
     */
    public function categoryIdsFor(ClassifiedContractTemplate $template): array
    {
        if ($template->category_scope !== ClassifiedContractTemplate::SCOPE_CLASSIFIED) {
            return [];
        }

        $lineage = $this->lineage($template);

        if ($template->country_id) {
            return ContractCountryAssignment::whereIn('contract_template_id', $lineage)
                ->whereNotNull('classified_category_id')
                ->pluck('classified_category_id')
                ->all();
        }

        return ClassifiedCategory::whereIn('contract_template_id', $lineage)->pluck('id')->all();
    }

    /**
     * Sections linked to each template version row (for the index badges), keyed by template id.
     *
     * @param  Collection<int, ClassifiedContractTemplate>  $templates
     * @return array<string, Collection<int, ClassifiedCategory>>
     */
    public function sectionsByTemplate(Collection $templates): array
    {
        $ids = $templates->where('category_scope', ClassifiedContractTemplate::SCOPE_CLASSIFIED)->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $pairs = ContractCountryAssignment::whereIn('contract_template_id', $ids)
            ->whereNotNull('classified_category_id')
            ->get(['contract_template_id', 'classified_category_id'])
            ->map(fn ($row) => [$row->contract_template_id, $row->classified_category_id])
            ->toBase()
            ->merge(
                ClassifiedCategory::whereIn('contract_template_id', $ids)->get(['id', 'contract_template_id'])
                    ->map(fn ($category) => [$category->contract_template_id, $category->id]),
            );

        $categories = ClassifiedCategory::whereIn('id', $pairs->pluck(1)->unique())->get(['id', 'name_en', 'name_ar', 'parent_id'])->keyBy('id');

        return $pairs->groupBy(0)
            ->map(fn (Collection $rows) => $rows->pluck(1)->map(fn ($id) => $categories[$id] ?? null)->filter()->sortBy('name_en')->values())
            ->all();
    }

    /**
     * Template ids that apply to a section: linked to it or to one of its ancestors (parent inheritance).
     *
     * @return Collection<int, string>
     */
    public function templateIdsForSection(string $categoryId): Collection
    {
        $chainIds = collect($this->contracts->resolver()->chain(ClassifiedCategory::findOrFail($categoryId)))->pluck('id');

        return ContractCountryAssignment::whereIn('classified_category_id', $chainIds)->pluck('contract_template_id')
            ->merge(ClassifiedCategory::whereIn('id', $chainIds)->whereNotNull('contract_template_id')->pluck('contract_template_id'))
            ->unique()
            ->values();
    }

    /**
     * For the template form: who already holds each section, per country ('legacy' for country-agnostic
     * contracts), ignoring the lineage being edited and retired contracts (their links are replaceable).
     *
     * @param  list<string>  $ignoreLineage
     * @return array<string, array<string, array{id: string, name: string}>>
     */
    public function takenSections(array $ignoreLineage = []): array
    {
        $taken = [];

        $assignments = ContractCountryAssignment::whereNotNull('classified_category_id')
            ->whereNotIn('contract_template_id', $ignoreLineage)
            ->with('contractTemplate:id,name,is_active')
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->contractTemplate?->is_active) {
                $taken[$assignment->country_id][$assignment->classified_category_id] = ['id' => $assignment->contract_template_id, 'name' => $assignment->contractTemplate->name];
            }
        }

        $legacy = ClassifiedCategory::whereNotNull('contract_template_id')
            ->whereNotIn('contract_template_id', $ignoreLineage)
            ->with('contractTemplate:id,name,is_active')
            ->get();

        foreach ($legacy as $category) {
            if ($category->contractTemplate?->is_active) {
                $taken['legacy'][$category->id] = ['id' => $category->contract_template_id, 'name' => $category->contractTemplate->name];
            }
        }

        return $taken;
    }

    /**
     * Rejects a selection in which a section already holds another active contract for the same country.
     *
     * @param  list<string>  $lineage
     * @param  list<string>  $categoryIds
     *
     * @throws ValidationException
     */
    public function assertAssignable(array $lineage, ?string $countryId, array $categoryIds): void
    {
        $taken = $this->takenSections($lineage)[$countryId ?? 'legacy'] ?? [];
        $conflicts = collect($categoryIds)->filter(fn (string $id) => isset($taken[$id]));

        if ($conflicts->isEmpty()) {
            return;
        }

        $names = ClassifiedCategory::whereIn('id', $conflicts)->pluck('name_en', 'id');

        throw ValidationException::withMessages([
            'classified_category_ids' => $conflicts
                ->map(fn (string $id) => __('contracts.sections.conflict', ['category' => $names[$id] ?? $id, 'template' => $taken[$id]['name']]))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Makes the linked sections of this template exactly $categoryIds.
     *
     * @param  list<string>  $categoryIds
     * @return array{added: list<string>, removed: list<string>, switched_off: list<string>}
     *
     * @throws ValidationException when a section already holds another contract for the template's country
     */
    public function sync(ClassifiedContractTemplate $template, array $categoryIds, ?Model $causer = null): array
    {
        $desired = array_values(array_unique($categoryIds));
        $result = ['added' => [], 'removed' => [], 'switched_off' => []];

        if ($template->category_scope !== ClassifiedContractTemplate::SCOPE_CLASSIFIED) {
            return $result;
        }

        DB::transaction(function () use ($template, $desired, &$result) {
            $lineage = $this->lineage($template);
            $current = $this->categoryIdsFor($template);
            $add = array_values(array_diff($desired, $current));
            $remove = array_values(array_diff($current, $desired));

            $this->assertAssignable($lineage, $template->country_id, $add);

            $live = ClassifiedContractTemplate::whereIn('id', $lineage)->where('is_published', true)->orderByDesc('version')->first() ?? $template;

            if ($remove !== []) {
                $this->unlink($template, $lineage, $remove);
                $result['switched_off'] = $this->switchOffUnlinked($remove);
            }

            foreach ($add as $categoryId) {
                $this->link($template, $live, $categoryId);
            }

            if ($live->is_published && $add !== []) {
                ClassifiedCategory::whereIn('id', $add)->where(fn ($q) => $q->whereNull('requires_contract')->orWhere('requires_contract', false))
                    ->update(['requires_contract' => true]);
            }

            $result['added'] = $add;
            $result['removed'] = $remove;
        });

        if ($result['added'] !== [] || $result['removed'] !== []) {
            ClassifiedCategory::flushStorefrontCaches();
            $this->activity->log(
                description: 'contract_template.sections_changed: '.$template->name.' v'.$template->version,
                subject: $template,
                causer: $causer ?? auth('admin')->user(),
                properties: $result + ['template_id' => $template->id, 'country_id' => $template->country_id],
                logName: 'contracts',
                event: 'contract_template.sections_changed',
            );
        }

        return $result;
    }

    /**
     * Called after a template is published: its sections now really require the contract.
     */
    public function activateLinkedSections(ClassifiedContractTemplate $template): void
    {
        $ids = $this->categoryIdsFor($template);

        if ($ids === []) {
            return;
        }

        ClassifiedCategory::whereIn('id', $ids)->where(fn ($q) => $q->whereNull('requires_contract')->orWhere('requires_contract', false))
            ->update(['requires_contract' => true]);
        ClassifiedCategory::flushStorefrontCaches();
    }

    /**
     * Drops every link that points at exactly this template row (before the row is deleted or changes country).
     */
    public function detach(ClassifiedContractTemplate $template): void
    {
        ContractCountryAssignment::where('contract_template_id', $template->id)->whereNotNull('classified_category_id')->delete();
        ClassifiedCategory::where('contract_template_id', $template->id)->update(['contract_template_id' => null]);
        ClassifiedCategory::flushStorefrontCaches();
    }

    /**
     * Active sections that no published, active classified contract covers (directly or through a parent).
     *
     * @return Collection<int, ClassifiedCategory>
     */
    public function uncoveredSections(): Collection
    {
        $usable = fn ($query) => $query->where('is_published', true)->where('is_active', true)
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_CLASSIFIED);

        $linked = ContractCountryAssignment::whereNotNull('classified_category_id')
            ->whereHas('contractTemplate', $usable)
            ->pluck('classified_category_id')
            ->merge(ClassifiedCategory::whereNotNull('contract_template_id')->whereHas('contractTemplate', $usable)->pluck('id'))
            ->flip();

        $categories = ClassifiedCategory::orderBy('sort_order')->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'parent_id', 'is_active']);
        $byId = $categories->keyBy('id');

        return $categories->filter(function (ClassifiedCategory $category) use ($linked, $byId): bool {
            if (! $category->is_active) {
                return false;
            }

            for ($node = $category, $depth = 0; $node && $depth < 20; $node = $byId[$node->parent_id] ?? null, $depth++) {
                if ($linked->has($node->id)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @return list<string> ids of every version of the template's contract in its country
     */
    private function lineage(ClassifiedContractTemplate $template): array
    {
        return $this->contracts->lineageIds($template)->push($template->id)->unique()->values()->all();
    }

    private function link(ClassifiedContractTemplate $template, ClassifiedContractTemplate $live, string $categoryId): void
    {
        if ($template->country_id) {
            ContractCountryAssignment::updateOrCreate(
                ['classified_category_id' => $categoryId, 'country_id' => $template->country_id],
                ['contract_template_id' => $live->id],
            );

            return;
        }

        ClassifiedCategory::whereKey($categoryId)->update(['contract_template_id' => $live->id]);
    }

    /**
     * @param  list<string>  $lineage
     * @param  list<string>  $categoryIds
     */
    private function unlink(ClassifiedContractTemplate $template, array $lineage, array $categoryIds): void
    {
        if ($template->country_id) {
            ContractCountryAssignment::whereIn('contract_template_id', $lineage)->whereIn('classified_category_id', $categoryIds)->delete();

            return;
        }

        ClassifiedCategory::whereIn('contract_template_id', $lineage)->whereIn('id', $categoryIds)->update(['contract_template_id' => null]);
    }

    /**
     * A section left with no contract at all no longer requires one (explicitly deselecting it is the admin's
     * decision; signatures already collected stay untouched).
     *
     * @param  list<string>  $categoryIds
     * @return list<string> english names of the sections switched off
     */
    private function switchOffUnlinked(array $categoryIds): array
    {
        $switched = [];

        foreach (ClassifiedCategory::whereIn('id', $categoryIds)->get() as $category) {
            $stillLinked = $category->contract_template_id !== null
                || ContractCountryAssignment::where('classified_category_id', $category->id)->exists();

            if (! $stillLinked && $category->requires_contract) {
                $category->forceFill(['requires_contract' => false])->save();
                $switched[] = $category->name_en;
            }
        }

        return $switched;
    }
}
