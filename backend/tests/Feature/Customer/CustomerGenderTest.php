<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Storefront sign-up + profile (client item #14): gender is optional (male | female, null = not specified).
 */
class CustomerGenderTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        // Registration notifies admins holding customers.view.
        Permission::firstOrCreate(['name' => 'customers.view', 'guard_name' => 'admin']);
        $this->scenario = MarketplaceScenario::make()->build();
        // Unique site code: DetectCountry caches the code → id lookup across parallel workers.
        $this->scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/auth/register", $overrides + [
            'name' => 'Gender Tester',
            'email' => 'gender-'.Str::lower(Str::random(10)).'@example.test',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ]);
    }

    public function test_registration_stores_the_selected_gender(): void
    {
        $response = $this->register(['gender' => 'female'])
            ->assertCreated()
            ->assertJsonPath('data.customer.gender', 'female');

        $this->assertSame('female', Customer::findOrFail($response->json('data.customer.id'))->gender?->value);
    }

    public function test_registration_without_gender_leaves_it_not_specified(): void
    {
        $response = $this->register()->assertCreated();

        $this->assertNull(Customer::findOrFail($response->json('data.customer.id'))->gender);
    }

    public function test_registration_rejects_an_unknown_gender(): void
    {
        $this->register(['gender' => 'unknown'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gender');
    }

    public function test_profile_update_sets_and_clears_gender(): void
    {
        $customer = $this->scenario->customer;
        $url = "/api/customer/v1/{$this->scenario->country->site_code}/profile";
        $this->actingAs($customer, 'customer');

        $this->putJson($url, ['name' => $customer->name, 'gender' => 'male'])->assertOk();
        $this->assertSame('male', $customer->fresh()->gender?->value);

        $this->putJson($url, ['name' => $customer->name, 'gender' => null])->assertOk();
        $this->assertNull($customer->fresh()->gender);

        $this->putJson($url, ['name' => $customer->name, 'gender' => 'prefer_not_to_say'])->assertOk();
        $this->assertNull($customer->fresh()->gender, 'Legacy "prefer_not_to_say" means not specified.');

        $this->putJson($url, ['name' => $customer->name, 'gender' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gender');
    }
}
