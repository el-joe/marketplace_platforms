<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ExclusiveContract;
use App\Models\Marketer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExclusiveContractListTest extends TestCase
{
    use RefreshDatabase;

    private function setUpAdminAndContract(): array
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketers.manage', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketers.view', 'guard_name' => 'admin']);
        $admin = Admin::factory()->create(['name' => 'Contract Admin']);
        $admin->givePermissionTo(['marketers.manage', 'marketers.view']);
        $marketer = Marketer::create([
            'name' => 'M', 'email' => 'm-'.uniqid().'@example.test', 'phone' => '+9715'.random_int(10000000, 99999999),
            'marketer_type' => 'influencer', 'global_status' => 'active', 'approved_at' => now(),
        ]);

        Storage::fake('private');
        Storage::disk('private')->put('exclusive-contracts/c.pdf', 'pdf');

        $contract = ExclusiveContract::create([
            'marketer_id' => $marketer->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(9),
            'status' => 'active',
            'contract_file_path' => 'exclusive-contracts/c.pdf',
            'notes' => 'VIP note here',
            'created_by' => $admin->id,
        ]);

        return [$admin, $marketer, $contract];
    }

    public function test_all_contract_details_show_in_the_table(): void
    {
        [$admin, $marketer] = $this->setUpAdminAndContract();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketers.show', $marketer))
            ->assertOk()
            ->assertSee('VIP note here')
            ->assertSee('Contract Admin')
            ->assertSee('نشط')
            ->assertSee('تحميل')
            ->assertSee('متبقي')
            ->assertSee('كل الأقسام');
    }

    public function test_contract_file_downloads_and_is_scoped_to_marketer(): void
    {
        [$admin, $marketer, $contract] = $this->setUpAdminAndContract();
        $other = Marketer::create([
            'name' => 'O', 'email' => 'o-'.uniqid().'@example.test', 'phone' => '+9715'.random_int(10000000, 99999999),
            'marketer_type' => 'influencer', 'global_status' => 'active', 'approved_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketers.exclusive-contracts.download', [$marketer, $contract]))
            ->assertOk();
        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketers.exclusive-contracts.download', [$other, $contract]))
            ->assertNotFound();
    }
}
