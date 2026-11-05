<?php

namespace Database\Factories;

use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedListing;
use App\Models\CustomerContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerContract>
 */
class CustomerContractFactory extends Factory
{
    protected $model = CustomerContract::class;

    public function definition(): array
    {
        $content = '<p>'.fake()->sentence().'</p>';

        return [
            'contract_template_id' => ClassifiedContractTemplate::factory()->state(['category_scope' => ClassifiedContractTemplate::SCOPE_CUSTOMER]),
            'template_version' => 1,
            'purpose' => CustomerContract::PURPOSE_PURCHASE,
            'customer_name' => fake()->name(),
            'language_signed' => 'en',
            'content_mode' => 'editor',
            'rendered_content' => $content,
            'rendered_content_hash' => hash('sha256', $content.'[]'),
            'variables' => [],
            'signer_name' => fake()->name(),
            'signed_ip' => '127.0.0.1',
            'signed_user_agent' => 'phpunit',
            'signed_at' => now(),
            'status' => CustomerContract::STATUS_ACTIVE,
        ];
    }

    public function forListing(ClassifiedListing $listing): static
    {
        return $this->state([
            'classified_listing_id' => $listing->id,
            'classified_category_id' => $listing->classified_category_id,
            'country_id' => $listing->country_id,
            'listing_number' => $listing->listing_number,
            'listing_title' => $listing->title_en,
        ]);
    }
}
