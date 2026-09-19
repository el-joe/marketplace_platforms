<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\MarketerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerSamplesLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
    }

    private function sample(string $status = 'pending', $campaign = null, $inv = null): MarketerCampaignSample
    {
        return MarketerCampaignSample::create([
            'campaign_id' => ($campaign ?? $this->s->marketerCampaign)->id,
            'invitation_id' => ($inv ?? $this->s->marketerCampaignInvitation)->id,
            'sample_owner' => 'marketer', 'quantity' => 1, 'status' => $status,
        ]);
    }

    private function mAdmin(Marketer $m): MarketerAdmin
    {
        return MarketerAdmin::create([
            'marketer_id' => $m->id, 'name' => 'M', 'email' => 'm-' . Str::random(8) . '@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function admin(): Admin
    {
        $perms = ['vendors.assigned_only', 'marketer_campaigns.approve', 'marketer_campaigns.view', 'marketers.view', 'marketers.manage'];
        foreach ($perms as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $a = Admin::factory()->create();
        $a->givePermissionTo(array_slice($perms, 1));

        return $a;
    }

    public function test_admin_status_transitions_enforced(): void
    {
        $admin = $this->admin();
        $sample = $this->sample();
        $url = route('admin.marketer-campaigns.samples.update', [$this->s->marketerCampaign->id, $sample->id]);

        $this->actingAs($admin, 'admin')->patch($url, ['status' => 'delivered'])->assertStatus(422);
        $this->actingAs($admin, 'admin')->patch($url, ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->actingAs($admin, 'admin')->patch($url, ['status' => 'dispatched'])->assertSessionHasNoErrors();
        $this->assertNotNull($sample->fresh()->dispatched_at);
        $this->actingAs($admin, 'admin')->patch($url, ['status' => 'pending'])->assertStatus(422);
        $this->actingAs($admin, 'admin')->patch($url, ['status' => 'delivered']);
        $this->actingAs($admin, 'admin')->patch($url, ['status' => 'returned']);
        $this->assertSame('returned', $sample->fresh()->status);
    }

    public function test_sample_must_belong_to_campaign(): void
    {
        $other = $this->s->marketerCampaign->replicate();
        $other->save();
        $sample = $this->sample('pending', $other);
        $this->actingAs($this->admin(), 'admin')
            ->patch(route('admin.marketer-campaigns.samples.update', [$this->s->marketerCampaign->id, $sample->id]), ['status' => 'dispatched'])
            ->assertForbidden();
    }

    public function test_marketer_address_and_receipt_ownership(): void
    {
        $sample = $this->sample();
        $owner = $this->mAdmin($this->s->marketer);
        $other = Marketer::create([
            'name' => 'O', 'email' => 'o-' . Str::random(6) . '@example.test', 'phone' => '+9715' . fake()->numerify('########'),
            'marketer_type' => 'influencer', 'global_status' => 'active', 'country_id' => $this->s->country->id, 'approved_at' => now(),
        ]);
        MarketerProfile::create(['marketer_id' => $other->id]);
        $intruder = $this->mAdmin($other);
        $addr = ['address_line_1' => 'x', 'city' => 'c', 'country' => 'AE', 'phone' => '1'];

        $this->post(route('marketer.samples.address', $sample->id), $addr)->assertRedirect();
        $this->actingAs($intruder, 'marketer')->post(route('marketer.samples.address', $sample->id), $addr)->assertForbidden();
        $this->actingAs($owner, 'marketer')->post(route('marketer.samples.address', $sample->id), [])->assertSessionHasErrors('address_line_1');
        $this->actingAs($owner, 'marketer')->post(route('marketer.samples.address', $sample->id), $addr)->assertSessionHasNoErrors();
        $this->assertSame('x', $sample->fresh()->delivery_address_snapshot['address_line_1']);

        // Receipt: only when dispatched, only owner.
        $this->actingAs($owner, 'marketer')->post(route('marketer.samples.received', $sample->id))->assertStatus(422);
        $sample->update(['status' => 'dispatched']);
        $this->actingAs($intruder, 'marketer')->post(route('marketer.samples.received', $sample->id))->assertForbidden();
        $this->actingAs($owner, 'marketer')->post(route('marketer.samples.received', $sample->id))->assertSessionHasNoErrors();
        $this->assertSame('delivered', $sample->fresh()->status);
        $this->actingAs($owner, 'marketer')->post(route('marketer.samples.address', $sample->id), $addr)->assertStatus(422);
    }

    public function test_admin_size_validation(): void
    {
        $inf = Marketer::create([
            'name' => 'I', 'email' => 'i-' . Str::random(6) . '@example.test', 'phone' => '+9715' . fake()->numerify('########'),
            'marketer_type' => 'influencer', 'global_status' => 'active', 'country_id' => $this->s->country->id, 'approved_at' => now(),
        ]);
        MarketerProfile::create(['marketer_id' => $inf->id]);
        $admin = $this->admin();
        $url = route('admin.marketers.profile.update', $inf->id);
        foreach ([['shoe_size_system' => 'XX'], ['chest_cm' => -1], ['chest_cm' => 9999]] as $bad) {
            $this->actingAs($admin, 'admin')->put($url, $bad)
                ->assertSessionHasErrors(array_key_first($bad));
        }
    }
}
