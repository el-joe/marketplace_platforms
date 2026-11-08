<?php

namespace App\Console\Commands;

use App\Exceptions\ContractNotConfiguredException;
use App\Models\Category;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractResolver;
use Illuminate\Console\Command;

/**
 * Read-only: explains why a seller is (or is not) asked to sign an individual contract before listing a product.
 * Prints the seller's country, the product override, every level of the category chain with its switch and its
 * template for that country, the country default, the resolved result and the matching signatures.
 */
class ExplainListingContract extends Command
{
    protected $signature = 'contracts:explain-listing
                            {vendor : Vendor id, store name or contact email}
                            {product : Product id, variant id or variant SKU}';

    protected $description = 'Explain which individual contract a vendor needs to list a product (read-only)';

    public function handle(ContractResolver $resolver, CategoryContractService $contracts): int
    {
        $vendor = $this->findVendor((string) $this->argument('vendor'));
        $product = $this->findProduct((string) $this->argument('product'));

        if (! $vendor || ! $product) {
            $this->error(! $vendor ? 'Vendor not found.' : 'Product not found.');

            return self::FAILURE;
        }

        $country = $resolver->countryOf($vendor);
        $this->line("Vendor:  {$vendor->store_name} ({$vendor->id})");
        $this->line('Country: '.($country ? "{$country->name_en} ({$country->id})" : 'NONE — only country-agnostic templates can apply'));
        $this->line("Product: {$product->name_en} / {$product->name_ar} ({$product->id})");
        $this->line('Product requirement: '.$product->contractRequirementValue().($product->contract_template_id ? " · own template {$product->contract_template_id}" : ''));
        $this->newLine();

        $category = $product->category_id ? Category::withTrashed()->find($product->category_id) : null;

        if (! $category) {
            $this->warn("Product category {$product->category_id} not found: no category contract can apply.");
        }

        $rows = [];
        foreach ($category ? $resolver->chain($category) : [] as $level) {
            $assignment = $country ? ContractCountryAssignment::where('product_category_id', $level->id)->where('country_id', $country->id)->with('contractTemplate')->first() : null;
            $rows[] = [
                $level->name_en,
                $level->id,
                var_export($level->requires_contract, true),
                $level->requiresIndividualContract() ? 'yes' : 'no',
                $this->describe($assignment?->contractTemplate),
                $level->contract_template_id ? $this->describe(ClassifiedContractTemplate::find($level->contract_template_id)) : '—',
                $level->trashed() ? 'DELETED' : '',
            ];
        }

        $this->table(['Category (nearest first)', 'Id', 'requires_contract', 'Requires', 'Country template', 'All-countries template', ''], $rows);
        $this->line('Country default (product scope): '.$this->describe($resolver->countryDefault(ClassifiedContractTemplate::SCOPE_PRODUCT, $country?->id)));
        $this->newLine();

        try {
            $resolved = $resolver->resolveForProduct($product, $country);
        } catch (ContractNotConfiguredException $e) {
            $this->warn('Result: required but NOT CONFIGURED for this country — listing is blocked ('.$e->getMessage().')');

            return self::SUCCESS;
        }

        if (! $resolved->isIndividual()) {
            $this->info('Result: no individual contract — the global Seller Agreement alone covers this product.');

            return self::SUCCESS;
        }

        $subjectType = $resolved->subject instanceof Product ? 'product' : 'category';
        $this->info("Result: individual contract {$this->describe($resolved->template)} for {$subjectType} \"{$resolved->subject->name_en}\" ({$resolved->subject->getKey()})");

        $signed = $contracts->hasCurrentSignature($vendor, $resolved->scope, $resolved->subject, $resolved->template);
        $this->line($signed ? 'Signature: SIGNED (current version) — listing allowed.' : 'Signature: NOT SIGNED — listing is blocked until signed.');

        $others = VendorContract::where('vendor_id', $vendor->id)->where('contract_template_id', $resolved->template->id)->where('status', 'active')->get();
        foreach ($others as $contract) {
            $this->line("  · active signature of this template for category {$contract->product_category_id} / product {$contract->product_id} (v{$contract->template_version})");
        }

        return self::SUCCESS;
    }

    private function describe(?ClassifiedContractTemplate $template): string
    {
        if (! $template) {
            return '—';
        }

        $state = $template->is_published && $template->is_active ? 'published' : 'NOT enforceable';

        return "{$template->name} v{$template->version} [{$state}, scope {$template->category_scope}, country ".($template->country_id ?? 'all').']';
    }

    private function findVendor(string $needle): ?Vendor
    {
        return Vendor::whereKey($needle)
            ->orWhere('store_name', $needle)
            ->orWhere('contact_email', $needle)
            ->first();
    }

    private function findProduct(string $needle): ?Product
    {
        if ($product = Product::find($needle)) {
            return $product;
        }

        $variant = ProductVariant::whereKey($needle)->orWhere('sku', $needle)->first();

        return $variant?->product;
    }
}
