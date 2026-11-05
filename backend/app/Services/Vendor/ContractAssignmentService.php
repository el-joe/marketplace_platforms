<?php

namespace App\Services\Vendor;

use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Models\Country;
use App\Models\Product;
use App\Services\ActivityLoggerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-side configuration of "requires individual contract": the switch, the all-countries (legacy) template and
 * the template per country, for product categories, classified categories and single products.
 *
 * Validation guarantees that a template is only ever assigned to a country it belongs to, and that switching a
 * category on without any usable template is rejected. Every change is written to the audit log.
 */
class ContractAssignmentService
{
    public function __construct(
        private readonly ContractResolver $resolver,
        private readonly ActivityLoggerService $activity,
    ) {}

    /**
     * Validation rules for the contract fields of a category / product form.
     *
     * @return array<string, mixed>
     */
    public function rules(string $scope): array
    {
        $published = fn () => Rule::exists('classified_contract_templates', 'id')
            ->where('category_scope', $scope)
            ->where('is_published', true);

        return [
            'requires_contract' => ['nullable', 'boolean'],
            'contract_template_id' => ['nullable', 'uuid', $published()->whereNull('country_id')],
            'country_contracts' => ['nullable', 'array'],
            'country_contracts.*' => ['nullable', 'uuid', $published()->whereNotNull('country_id')],
        ];
    }

    /**
     * Cross-field validation: every country template belongs to its country, and switching on needs a usable template.
     *
     * @param  array<string, mixed>  $input  request data
     * @param  Model|null  $subject  the category / product being edited (null on create)
     *
     * @throws ValidationException
     */
    public function assertConfigurable(string $scope, array $input, ?Model $subject = null): void
    {
        $countryTemplates = $this->countryTemplates($input);

        if ($countryTemplates->isNotEmpty()) {
            $templates = ClassifiedContractTemplate::whereIn('id', $countryTemplates->values())->pluck('country_id', 'id');

            foreach ($countryTemplates as $countryId => $templateId) {
                if (($templates[$templateId] ?? null) !== $countryId || ! Country::whereKey($countryId)->exists()) {
                    throw ValidationException::withMessages(['country_contracts' => __('contracts.admin.template_country_mismatch')]);
                }
            }
        }

        $requires = filter_var($input['requires_contract'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ($input['contract_requirement'] ?? null) === Product::CONTRACT_REQUIRED;

        if (! $requires) {
            return;
        }

        $hasTemplate = ! empty($input['contract_template_id'])
            || $countryTemplates->isNotEmpty()
            || ($subject && $subject->contract_template_id)
            || ($subject && ContractCountryAssignment::where($this->column($subject), $subject->getKey())->exists())
            || $this->countryDefaultExists($scope);

        if (! $hasTemplate) {
            throw ValidationException::withMessages(['requires_contract' => __('contracts.admin.requires_needs_template')]);
        }
    }

    /**
     * Saves the switch and the per-country assignments. The all-countries template (contract_template_id) is
     * written by the caller together with the rest of the model. Returns the countries with sellers that this
     * subject has no usable contract for yet, so the admin can be warned.
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, Country>
     */
    public function apply(Model $subject, string $scope, array $input, ?Model $causer = null): Collection
    {
        $before = $this->snapshot($subject);

        DB::transaction(function () use ($subject, $input) {
            if ($subject instanceof Product) {
                $requirement = $input['contract_requirement'] ?? null;

                if (in_array($requirement, Product::contractRequirements(), true)) {
                    $subject->forceFill(['contract_requirement' => $requirement === Product::CONTRACT_INHERIT ? null : $requirement])->save();
                }

                if (array_key_exists('contract_template_id', $input)) {
                    $subject->forceFill(['contract_template_id' => $input['contract_template_id'] ?: null])->save();
                }
            } elseif (array_key_exists('requires_contract', $input)) {
                $subject->forceFill(['requires_contract' => filter_var($input['requires_contract'], FILTER_VALIDATE_BOOLEAN)])->save();
            }

            if (array_key_exists('country_contracts', $input)) {
                $this->syncCountryTemplates($subject, $this->countryTemplates($input));
            }
        });

        $subject->refresh();
        $after = $this->snapshot($subject);

        if ($before !== $after) {
            $this->activity->log(
                description: 'contract_requirement.changed: '.$subject->name_en,
                subject: $subject,
                causer: $causer ?? auth('admin')->user(),
                properties: ['scope' => $scope, 'before' => $before, 'after' => $after],
                logName: 'contracts',
                event: 'contract_requirement.changed',
            );
        }

        return $this->gaps($subject, $scope);
    }

    /**
     * Countries with sellers where this subject requires a contract but none is configured.
     *
     * @return Collection<int, Country>
     */
    public function gaps(Model $subject, string $scope): Collection
    {
        $requires = $subject instanceof Product
            ? $subject->contractRequirementValue() === Product::CONTRACT_REQUIRED || $subject->contractRequirementValue() === Product::CONTRACT_INHERIT
            : $subject->requiresIndividualContract();

        if (! $requires) {
            return collect();
        }

        return $this->resolver->countriesWithoutIndividualContract($scope, $subject);
    }

    /**
     * Rows for the "template per country" table of the admin forms.
     *
     * @return array{countries: Collection<int, Country>, templates: Collection<int, ClassifiedContractTemplate>, selected: array<string, string>}
     */
    public function formData(string $scope, ?Model $subject = null): array
    {
        $templates = ClassifiedContractTemplate::forScope($scope)->enforceable()->orderBy('name')->orderByDesc('version')->get();

        $selected = $subject
            ? ContractCountryAssignment::where($this->column($subject), $subject->getKey())->pluck('contract_template_id', 'country_id')->all()
            : [];

        return [
            'countries' => Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'iso_code_2']),
            'templates' => $templates,
            'selected' => $selected,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<string, string> template id keyed by country id (blank selections dropped)
     */
    private function countryTemplates(array $input): Collection
    {
        return collect($input['country_contracts'] ?? [])
            ->filter(fn ($templateId) => is_string($templateId) && $templateId !== '');
    }

    /**
     * @param  Collection<string, string>  $desired
     */
    private function syncCountryTemplates(Model $subject, Collection $desired): void
    {
        $column = $this->column($subject);

        ContractCountryAssignment::where($column, $subject->getKey())
            ->whereNotIn('country_id', $desired->keys())
            ->delete();

        foreach ($desired as $countryId => $templateId) {
            ContractCountryAssignment::updateOrCreate(
                [$column => $subject->getKey(), 'country_id' => $countryId],
                ['contract_template_id' => $templateId],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $subject): array
    {
        return [
            'requires_contract' => $subject instanceof Product ? $subject->contractRequirementValue() : $subject->requiresIndividualContract(),
            'contract_template_id' => $subject->contract_template_id,
            'countries' => ContractCountryAssignment::where($this->column($subject), $subject->getKey())->orderBy('country_id')->pluck('contract_template_id', 'country_id')->all(),
        ];
    }

    private function countryDefaultExists(string $scope): bool
    {
        return ClassifiedContractTemplate::enforceable()->forScope($scope)->whereNotNull('country_id')->where('is_country_default', true)->exists();
    }

    private function column(Model $subject): string
    {
        return match (true) {
            $subject instanceof Product => 'product_id',
            $subject instanceof Category => 'product_category_id',
            $subject instanceof ClassifiedCategory => 'classified_category_id',
            default => throw new \InvalidArgumentException('Unsupported contract subject.'),
        };
    }
}
