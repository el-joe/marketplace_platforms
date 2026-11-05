<?php

namespace App\Services\Vendor;

use App\Exceptions\ContractNotConfiguredException;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Models\Country;
use App\Models\CustomerContractAssignment;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Decides which contract applies to a seller (Requirement 4 priority):
 *
 *  1. the contracting party's country (the seller's country);
 *  2. the active contract configured for that country;
 *  3. whether the product / category requires an individual contract
 *     (product: required | exempt | inherit; category: switch, inherited from parent categories);
 *  4. if yes, the individual contract of that country: the product's own assignment, then the category's
 *     (nearest requiring ancestor first), then the country's default contract for the scope;
 *  5. otherwise the global platform Seller Agreement of that country.
 *
 * Country-agnostic ("legacy") templates have country_id = NULL. They are the global default: they serve a
 * country that has no template of its own for the same purpose, so existing contracts keep working until an
 * admin configures a country-specific one. Resolution NEVER falls back from one country to another: a template
 * of country A is never used for a seller of country B. A required individual contract with no template for
 * the seller's country throws {@see ContractNotConfiguredException}; it is never replaced by the global agreement.
 */
class ContractResolver
{
    /** @var array<string, list<Model>> */
    private array $chains = [];

    public function countryOf(Vendor $vendor): ?Country
    {
        return $vendor->country_id ? ($vendor->relationLoaded('country') ? $vendor->country : Country::find($vendor->country_id)) : null;
    }

    // ── Platform (global) agreement ───────────────────────────────────────────

    /**
     * Published, active platform agreements that serve a country: its own versions, otherwise the legacy
     * country-agnostic ones. Never another country's.
     *
     * @return Builder<ClassifiedContractTemplate>
     */
    public function platformQuery(?string $countryId): Builder
    {
        $base = fn (): Builder => ClassifiedContractTemplate::query()
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('is_published', true)
            ->where('is_active', true);

        if ($countryId && $base()->where('country_id', $countryId)->exists()) {
            return $base()->where('country_id', $countryId);
        }

        return $base()->whereNull('country_id');
    }

    /**
     * The country lineage that serves a country: the country itself when it has a published agreement of its own,
     * otherwise null (the legacy country-agnostic lineage).
     */
    public function platformLineageCountryId(?string $countryId): ?string
    {
        if ($countryId && ClassifiedContractTemplate::query()
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('is_published', true)->where('is_active', true)
            ->where('country_id', $countryId)->exists()) {
            return $countryId;
        }

        return null;
    }

    /**
     * Whether any platform agreement is published anywhere. While none is, nothing is enforced (the state
     * before contracts were introduced), so registration is not blocked by a missing country agreement.
     */
    public function anyPlatformAgreementPublished(): bool
    {
        return ClassifiedContractTemplate::query()
            ->where('category_scope', ClassifiedContractTemplate::SCOPE_PLATFORM)
            ->where('is_published', true)->where('is_active', true)->exists();
    }

    /**
     * The agreement a seller of this country has to sign (newest published version).
     *
     * @param  bool  $strict  throw when agreements exist but none serves this country
     */
    public function platformAgreementFor(?Country $country, bool $strict = false): ?ClassifiedContractTemplate
    {
        $template = $this->platformQuery($country?->id)->orderByDesc('version')->first();

        if (! $template && $strict && $this->anyPlatformAgreementPublished()) {
            throw ContractNotConfiguredException::forCountry($country, ClassifiedContractTemplate::SCOPE_PLATFORM);
        }

        return $template;
    }

    // ── Individual contracts ──────────────────────────────────────────────────

    public function resolveForVendorProduct(Vendor $vendor, Product $product): ResolvedContract
    {
        return $this->resolveForProduct($product, $this->countryOf($vendor));
    }

    public function resolveForVendorCategory(Vendor $vendor, string $scope, Category|ClassifiedCategory $category): ResolvedContract
    {
        return $this->resolveForCategory($scope, $category, $this->countryOf($vendor));
    }

    /**
     * @throws ContractNotConfiguredException when an individual contract is required but none is configured for the country
     */
    public function resolveForProduct(Product $product, ?Country $country): ResolvedContract
    {
        $requirement = $product->contractRequirementValue();

        if ($requirement === Product::CONTRACT_EXEMPT) {
            return $this->globalFor($country);
        }

        $category = $product->category_id ? Category::find($product->category_id) : null;
        $levels = $category ? $this->requiringLevels($category) : [];

        if ($requirement === Product::CONTRACT_INHERIT && $levels === []) {
            return $this->globalFor($country);
        }

        $template = $this->assignedTemplate($product, 'product_id', ClassifiedContractTemplate::SCOPE_PRODUCT, $country?->id);

        if ($template) {
            return ResolvedContract::individual($country, $template, ClassifiedContractTemplate::SCOPE_PRODUCT, $product);
        }

        foreach ($levels as $level) {
            if ($template = $this->assignedTemplate($level, 'product_category_id', ClassifiedContractTemplate::SCOPE_PRODUCT, $country?->id)) {
                return ResolvedContract::individual($country, $template, ClassifiedContractTemplate::SCOPE_PRODUCT, $level);
            }
        }

        if ($template = $this->countryDefault(ClassifiedContractTemplate::SCOPE_PRODUCT, $country?->id)) {
            return ResolvedContract::individual($country, $template, ClassifiedContractTemplate::SCOPE_PRODUCT, $levels[0] ?? $product);
        }

        if ($requirement === Product::CONTRACT_INHERIT && ! $this->explicitlyRequired($levels)) {
            return $this->globalFor($country);
        }

        throw ContractNotConfiguredException::forCountry($country, ClassifiedContractTemplate::SCOPE_PRODUCT);
    }

    /**
     * @throws ContractNotConfiguredException
     */
    public function resolveForCategory(string $scope, Category|ClassifiedCategory $category, ?Country $country): ResolvedContract
    {
        $levels = $this->requiringLevels($category);

        if ($levels === []) {
            return $this->globalFor($country);
        }

        $column = $scope === ClassifiedContractTemplate::SCOPE_PRODUCT ? 'product_category_id' : 'classified_category_id';

        foreach ($levels as $level) {
            if ($template = $this->assignedTemplate($level, $column, $scope, $country?->id)) {
                return ResolvedContract::individual($country, $template, $scope, $level);
            }
        }

        if ($template = $this->countryDefault($scope, $country?->id)) {
            return ResolvedContract::individual($country, $template, $scope, $levels[0]);
        }

        // A legacy category that only has a template link (switch never set) and whose template is no
        // longer enforceable is not required, exactly as before the switch existed.
        if (! $this->explicitlyRequired($levels)) {
            return $this->globalFor($country);
        }

        throw ContractNotConfiguredException::forCountry($country, $scope);
    }

    // ── Customer purchase contracts ───────────────────────────────────────────

    /**
     * The customer purchase contract a classified advertisement of this category and country needs, or null when
     * none applies (the customer then buys / inquires exactly as before).
     *
     * Independent of the seller contracts above: it reads customer_contract_assignments and templates of the
     * `customer` scope only, so a category's seller contract can never be returned here and vice versa.
     * Parent inheritance: the nearest category (the ad's own, then its parents) with a usable assignment wins; for
     * one category the country's own assignment is preferred over the all-countries (country_id NULL) one. A template
     * of another country is never returned, and a country without a template simply has no customer contract.
     */
    public function resolveForCustomerPurchase(ClassifiedCategory $category, ?Country $country): ?ClassifiedContractTemplate
    {
        $chain = $this->chain($category);

        $assignments = CustomerContractAssignment::query()
            ->whereIn('classified_category_id', array_map(fn (ClassifiedCategory $level) => $level->getKey(), $chain))
            ->where(fn (Builder $query) => $query->whereNull('country_id')->when($country, fn (Builder $c) => $c->orWhere('country_id', $country->id)))
            ->with('contractTemplate')
            ->get();

        foreach ($chain as $level) {
            $levelRows = $assignments->where('classified_category_id', $level->getKey());
            $candidates = [
                $country ? $levelRows->firstWhere('country_id', $country->id) : null,
                $levelRows->first(fn (CustomerContractAssignment $row) => $row->country_id === null),
            ];

            foreach ($candidates as $assignment) {
                $template = $assignment?->contractTemplate;

                if ($this->usableForCustomer($template, $country?->id)) {
                    return $template;
                }
            }
        }

        return null;
    }

    private function usableForCustomer(?ClassifiedContractTemplate $template, ?string $countryId): bool
    {
        return $this->usable($template, ClassifiedContractTemplate::SCOPE_CUSTOMER, $countryId)
            && ($template->effective_at === null || $template->effective_at->lte(now()));
    }

    /**
     * Countries (with at least one seller) for which this category / product has no usable individual contract.
     * Used by the admin form to warn before the switch goes live.
     *
     * @return Collection<int, Country>
     */
    public function countriesWithoutIndividualContract(string $scope, Model $subject): Collection
    {
        return $this->sellerCountries()->filter(function (Country $country) use ($scope, $subject): bool {
            try {
                if ($subject instanceof Product) {
                    $this->resolveForProduct($subject, $country);
                } else {
                    $this->resolveForCategory($scope, $subject, $country);
                }

                return false;
            } catch (ContractNotConfiguredException) {
                return true;
            }
        })->values();
    }

    /**
     * Countries that have sellers but no published platform agreement serving them.
     *
     * @return Collection<int, Country>
     */
    public function countriesWithoutPlatformAgreement(): Collection
    {
        return $this->sellerCountries()
            ->filter(fn (Country $country) => $this->platformQuery($country->id)->doesntExist())
            ->values();
    }

    /**
     * Countries that have at least one active or under-review seller.
     *
     * @return Collection<int, Country>
     */
    public function sellerCountries(): Collection
    {
        $ids = Vendor::query()
            ->whereIn('global_status', ['active', 'under_review'])
            ->whereNotNull('country_id')
            ->distinct()
            ->pluck('country_id');

        return Country::whereIn('id', $ids)->orderBy('name_en')->get();
    }

    /**
     * The category plus every ancestor, nearest first, whose switch is on. Parent-category inheritance: a
     * sub-category is covered by the individual contract of the nearest ancestor that requires one.
     *
     * @return list<Category|ClassifiedCategory>
     */
    public function requiringLevels(Category|ClassifiedCategory $category): array
    {
        return array_values(array_filter(
            $this->chain($category),
            fn (Category|ClassifiedCategory $level) => $level->requiresIndividualContract(),
        ));
    }

    /**
     * @return list<Category|ClassifiedCategory>
     */
    public function chain(Category|ClassifiedCategory $category): array
    {
        $key = $category::class.':'.$category->getKey();

        if (isset($this->chains[$key])) {
            return $this->chains[$key];
        }

        $class = $category::class;
        $chain = [];
        $seen = [];
        $current = $category;

        while ($current && ! isset($seen[$current->getKey()])) {
            $seen[$current->getKey()] = true;
            $chain[] = $current;
            $current = $current->parent_id ? $class::find($current->parent_id) : null;
        }

        return $this->chains[$key] = $chain;
    }

    /**
     * The template assigned to a subject for a country: the country's own assignment first, then the subject's
     * country-agnostic link (contract_template_id). A template of another country is never returned.
     */
    private function assignedTemplate(Model $subject, string $column, string $scope, ?string $countryId): ?ClassifiedContractTemplate
    {
        if ($countryId) {
            $assignment = ContractCountryAssignment::query()
                ->where($column, $subject->getKey())
                ->where('country_id', $countryId)
                ->with('contractTemplate')
                ->first();

            if ($assignment && $this->usable($assignment->contractTemplate, $scope, $countryId)) {
                return $assignment->contractTemplate;
            }
        }

        $legacy = $subject->contract_template_id ? ClassifiedContractTemplate::find($subject->contract_template_id) : null;

        return $this->usable($legacy, $scope, $countryId) ? $legacy : null;
    }

    /**
     * The country's default individual contract for a scope (published, active, flagged default).
     */
    public function countryDefault(string $scope, ?string $countryId): ?ClassifiedContractTemplate
    {
        if (! $countryId) {
            return null;
        }

        return ClassifiedContractTemplate::query()
            ->enforceable()
            ->where('category_scope', $scope)
            ->where('country_id', $countryId)
            ->where('is_country_default', true)
            ->orderByDesc('version')
            ->first();
    }

    private function usable(?ClassifiedContractTemplate $template, string $scope, ?string $countryId): bool
    {
        return $template !== null
            && $template->is_published
            && $template->is_active
            && $template->category_scope === $scope
            && ($template->country_id === null || $template->country_id === $countryId);
    }

    /**
     * @param  list<Category|ClassifiedCategory>  $levels
     */
    private function explicitlyRequired(array $levels): bool
    {
        foreach ($levels as $level) {
            if ($level->requires_contract === true) {
                return true;
            }
        }

        return false;
    }

    private function globalFor(?Country $country): ResolvedContract
    {
        return ResolvedContract::global($country, $this->platformAgreementFor($country));
    }
}
