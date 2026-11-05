<?php

namespace Database\Factories;

use App\Models\ClassifiedContractTemplate;
use App\Models\Marketer;
use App\Models\MarketerSellerContract;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketerSellerContract>
 */
class MarketerSellerContractFactory extends Factory
{
    protected $model = MarketerSellerContract::class;

    public function definition(): array
    {
        $content = '<p>'.fake()->sentence().'</p>';

        return [
            'contract_template_id' => ClassifiedContractTemplate::factory(),
            'template_version' => 1,
            'vendor_id' => Vendor::factory(),
            'marketer_id' => Marketer::factory(),
            'language_signed' => 'en',
            'content_mode' => 'editor',
            'rendered_content' => $content,
            'rendered_content_hash' => hash('sha256', $content.'[]'),
            'variables' => [],
            'signer_name' => fake()->name(),
            'signed_ip' => '127.0.0.1',
            'signed_user_agent' => 'phpunit',
            'signed_at' => now(),
            'status' => MarketerSellerContract::STATUS_ACTIVE,
        ];
    }
}
