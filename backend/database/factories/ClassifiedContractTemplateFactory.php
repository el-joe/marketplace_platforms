<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\ClassifiedContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassifiedContractTemplate>
 */
class ClassifiedContractTemplateFactory extends Factory
{
    protected $model = ClassifiedContractTemplate::class;

    public function definition(): array
    {
        return [
            'name' => 'Contract '.fake()->unique()->bothify('??##'),
            'category_scope' => ClassifiedContractTemplate::SCOPE_CLASSIFIED,
            'content_mode' => ClassifiedContractTemplate::MODE_EDITOR,
            'version' => 1,
            'content_en' => '<p>EN {{vendor.store_name}}</p>',
            'content_ar' => '<p>AR {{vendor.store_name}}</p>',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
            'effective_at' => now()->subMinute(),
            'variables_schema' => ClassifiedContractTemplate::variableKeys(),
            'created_by_admin_id' => Admin::factory(),
        ];
    }

    public function marketerCollaboration(): static
    {
        return $this->state(['category_scope' => ClassifiedContractTemplate::SCOPE_MARKETER]);
    }

    public function customerPurchase(): static
    {
        return $this->state([
            'category_scope' => ClassifiedContractTemplate::SCOPE_CUSTOMER,
            'content_en' => '<p>Customer {{customer.name}} accepts {{listing.title}} of {{seller.name}} ({{listing.price}}) signed {{customer.signer_name}}</p>',
            'content_ar' => '<p>العميل {{customer.name}} يقبل {{listing.title}} من {{seller.name}}</p>',
        ]);
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false, 'published_at' => null, 'effective_at' => null]);
    }
}
