<?php

namespace App\Services\Vendor;

use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\CustomerContractAssignment;
use App\Services\ActivityLoggerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Which classified sections (categories) a customer purchase contract applies to, and publishing of its versions.
 *
 * The association lives in `customer_contract_assignments` (category + country, country NULL = every country without
 * a contract of its own), a table separate from the seller contract assignments: the seller contract and the customer
 * contract of a section never mix. A section needs no switch: it requires a customer contract exactly while a
 * published, active, in-force customer template is assigned to it (or to a parent section) for the ad's country.
 *
 * A link belongs to a contract lineage (same name, scope and country: its versions). New links go to the lineage's
 * published version, or to the draft while nothing is published yet (inert until published); publishing a newer
 * version moves the links along and retires the older version. Signatures are never touched.
 */
class CustomerContractAssignmentService
{
    public function __construct(
        private readonly ContractResolver $resolver,
        private readonly ActivityLoggerService $activity,
    ) {}

    /**
     * Ids of every version of the same customer contract in the same country.
     *
     * @return Collection<int, string>
     */
    public function lineageIds(ClassifiedContractTemplate $template): Collection
    {
        return ClassifiedContractTemplate::where('name', $template->name)
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_CUSTOMER)
            ->when($template->country_id, fn ($query) => $query->where('country_id', $template->country_id), fn ($query) => $query->whereNull('country_id'))
            ->pluck('id')
            ->push($template->id)
            ->unique()
            ->values();
    }

    /**
     * Ids of the sections linked to this template's lineage.
     *
     * @return list<string>
     */
    public function categoryIdsFor(ClassifiedContractTemplate $template): array
    {
        if (! $template->isCustomerScope()) {
            return [];
        }

        return CustomerContractAssignment::whereIn('contract_template_id', $this->lineageIds($template))
            ->pluck('classified_category_id')
            ->all();
    }

    /**
     * Sections linked to each template version row (for the index badges), keyed by template id.
     *
     * @param  Collection<int, ClassifiedContractTemplate>  $templates
     * @return array<string, Collection<int, ClassifiedCategory>>
     */
    public function sectionsByTemplate(Collection $templates): array
    {
        $ids = $templates->where('category_scope', ClassifiedContractTemplate::SCOPE_CUSTOMER)->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $rows = CustomerContractAssignment::whereIn('contract_template_id', $ids)->get(['contract_template_id', 'classified_category_id']);
        $categories = ClassifiedCategory::whereIn('id', $rows->pluck('classified_category_id')->unique())->get(['id', 'name_en', 'name_ar', 'parent_id'])->keyBy('id');

        return $rows->groupBy('contract_template_id')
            ->map(fn (Collection $group) => $group->pluck('classified_category_id')->map(fn ($id) => $categories[$id] ?? null)->filter()->sortBy('name_en')->values())
            ->all();
    }

    /**
     * Template ids that apply to a section: linked to it or to one of its ancestors (parent inheritance).
     *
     * @return Collection<int, string>
     */
    public function templateIdsForSection(string $categoryId): Collection
    {
        $chainIds = collect($this->resolver->chain(ClassifiedCategory::findOrFail($categoryId)))->pluck('id');

        return CustomerContractAssignment::whereIn('classified_category_id', $chainIds)->pluck('contract_template_id')->unique()->values();
    }

    /**
     * For the template form: who already holds each section, per country ('legacy' for the all-countries contract),
     * ignoring the lineage being edited and retired contracts (their links are replaceable).
     *
     * @param  list<string>  $ignoreLineage
     * @return array<string, array<string, array{id: string, name: string}>>
     */
    public function takenSections(array $ignoreLineage = []): array
    {
        $taken = [];

        $assignments = CustomerContractAssignment::whereNotIn('contract_template_id', $ignoreLineage)
            ->with('contractTemplate:id,name,is_active')
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->contractTemplate?->is_active) {
                $taken[$assignment->country_id ?? 'legacy'][$assignment->classified_category_id] = ['id' => $assignment->contract_template_id, 'name' => $assignment->contractTemplate->name];
            }
        }

        return $taken;
    }

    /**
     * Rejects a selection in which a section already holds another active customer contract for the same country.
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
     * @return array{added: list<string>, removed: list<string>}
     *
     * @throws ValidationException when a section already holds another customer contract for the template's country
     */
    public function sync(ClassifiedContractTemplate $template, array $categoryIds, ?Model $causer = null): array
    {
        $result = ['added' => [], 'removed' => []];

        if (! $template->isCustomerScope()) {
            return $result;
        }

        $desired = array_values(array_unique($categoryIds));

        DB::transaction(function () use ($template, $desired, &$result) {
            $lineage = $this->lineageIds($template)->all();
            $current = $this->categoryIdsFor($template);
            $add = array_values(array_diff($desired, $current));
            $remove = array_values(array_diff($current, $desired));

            $this->assertAssignable($lineage, $template->country_id, $add);

            $live = ClassifiedContractTemplate::whereIn('id', $lineage)->where('is_published', true)->orderByDesc('version')->first() ?? $template;

            if ($remove !== []) {
                CustomerContractAssignment::whereIn('contract_template_id', $lineage)->whereIn('classified_category_id', $remove)->delete();
            }

            foreach ($add as $categoryId) {
                CustomerContractAssignment::updateOrCreate(
                    ['classified_category_id' => $categoryId, 'country_id' => $template->country_id],
                    ['contract_template_id' => $live->id],
                );
            }

            $result = ['added' => $add, 'removed' => $remove];
        });

        if ($result['added'] !== [] || $result['removed'] !== []) {
            $this->activity->log(
                description: 'customer_contract_template.sections_changed: '.$template->name.' v'.$template->version,
                subject: $template,
                causer: $causer ?? auth('admin')->user(),
                properties: $result + ['template_id' => $template->id, 'country_id' => $template->country_id],
                logName: 'contracts',
                event: 'customer_contract_template.sections_changed',
            );
        }

        return $result;
    }

    /**
     * Makes this version the one customers sign within its own country lineage: older versions are retired and the
     * sections move onto it, so customers who accepted an older version are asked again. Other countries and all
     * signatures are untouched.
     */
    public function publish(ClassifiedContractTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            $lineage = $this->lineageIds($template);

            ClassifiedContractTemplate::whereIn('id', $lineage)
                ->where('id', '!=', $template->id)
                ->update(['is_published' => false, 'is_active' => false]);

            $template->update([
                'is_published' => true,
                'is_active' => true,
                'is_country_default' => false,
                'published_at' => now(),
                'effective_at' => now(),
            ]);

            CustomerContractAssignment::whereIn('contract_template_id', $lineage)->update(['contract_template_id' => $template->id]);
        });
    }

    /**
     * Drops every link that points at exactly this template row (before the row is deleted or changes country).
     */
    public function detach(ClassifiedContractTemplate $template): void
    {
        CustomerContractAssignment::where('contract_template_id', $template->id)->delete();
    }
}
