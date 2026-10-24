<?php

namespace Tests\Feature\Marketer;

use App\Models\Admin;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MarketerGenderUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $perms): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($perms);

        return $admin;
    }

    private function marketerAdmin(Marketer $marketer): MarketerAdmin
    {
        $marketer->forceFill(['onboarding_completed_at' => now()])->save();

        return MarketerAdmin::create([
            'marketer_id' => $marketer->id, 'name' => 'M', 'email' => 'm-'.uniqid().'@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    public function test_admin_sets_validates_and_clears_gender(): void
    {
        $admin = $this->admin(['marketers.view', 'marketers.manage']);
        $marketer = Marketer::factory()->withProfile()->create();
        $url = route('admin.marketers.profile.update', $marketer);

        $this->actingAs($admin, 'admin')->put($url, ['gender' => 'female'])->assertSessionHasNoErrors();
        $this->assertSame('female', $marketer->marketerProfile()->first()->gender->value);

        $this->actingAs($admin, 'admin')->put($url, ['gender' => 'other'])->assertSessionHasErrors('gender');
        $this->assertSame('female', $marketer->marketerProfile()->first()->gender->value);

        $this->actingAs($admin, 'admin')->put($url, ['gender' => ''])->assertSessionHasNoErrors();
        $this->assertNull($marketer->marketerProfile()->first()->gender);
    }

    public function test_admin_index_gender_filter_and_column(): void
    {
        $this->withoutVite();
        $admin = $this->admin(['marketers.view']);
        Marketer::factory()->withProfile('male')->create(['name' => 'Mario Male']);
        Marketer::factory()->withProfile('female')->create(['name' => 'Fiona Female']);
        Marketer::factory()->withProfile(null)->create(['name' => 'Nora Notset']);
        Marketer::factory()->create(['name' => 'Nina Noprofile']);

        $see = fn (string $g) => $this->actingAs($admin, 'admin')->get(route('admin.marketers.index', ['gender' => $g]));

        $see('male')->assertOk()->assertSee('Mario Male')->assertDontSee('Fiona Female')->assertDontSee('Nora Notset');
        $see('female')->assertOk()->assertSee('Fiona Female')->assertDontSee('Mario Male');
        $see('none')->assertOk()->assertSee('Nora Notset')->assertSee('Nina Noprofile')->assertDontSee('Mario Male')->assertDontSee('Fiona Female');
        $see('')->assertOk()->assertSee('Mario Male')->assertSee('Nina Noprofile');
    }

    public function test_marketer_api_profile_updates_and_returns_gender(): void
    {
        $marketer = Marketer::factory()->withProfile()->create();
        $user = $this->marketerAdmin($marketer);

        $this->actingAs($user, 'marketer_api')->postJson('/api/marketer/profile', ['gender' => 'male'])->assertOk();
        $this->actingAs($user, 'marketer_api')->getJson('/api/marketer/profile')
            ->assertOk()->assertJsonPath('gender', 'male')->assertJsonPath('profile.gender', 'male');

        $this->actingAs($user, 'marketer_api')->postJson('/api/marketer/profile', ['gender' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('gender');

        $this->actingAs($user, 'marketer_api')->postJson('/api/marketer/profile', ['gender' => null])->assertOk();
        $this->assertNull($marketer->marketerProfile()->first()->gender);
    }

    public function test_marketer_panel_profile_page_saves_gender(): void
    {
        $this->withoutVite();
        $marketer = Marketer::factory()->withProfile()->create();
        $user = $this->marketerAdmin($marketer);

        $this->actingAs($user, 'marketer')->get(route('marketer.profile'))->assertOk()->assertSee('name="gender"', false);

        $this->actingAs($user, 'marketer')->put(route('marketer.profile.update'), ['gender' => 'female'])->assertSessionHasNoErrors();
        $this->assertSame('female', $marketer->marketerProfile()->first()->gender->value);

        $this->actingAs($user, 'marketer')->put(route('marketer.profile.update'), ['gender' => 'zzz'])->assertSessionHasErrors('gender');
    }
}
