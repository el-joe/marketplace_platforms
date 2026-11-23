<?php

namespace Tests\Feature\Packaging;

use App\Models\Admin;
use App\Models\PackagingSupplyRequest;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client item #13 (not reproduced by QA): the admin packaging-requests date filter is an Oman-time
 * (UTC+4) calendar range, inclusive on both ends, and keeps applying while the search box is used.
 */
class PackagingRequestDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin_timezone' => 'Asia/Muscat']);

        foreach (['packaging.manage', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo('packaging.manage');
        $this->vendor = Vendor::factory()->create();
    }

    private function requestCreatedAt(string $number, string $utc): void
    {
        $request = PackagingSupplyRequest::create([
            'request_number' => $number,
            'vendor_id' => $this->vendor->id,
            'status' => 'pending',
            'total_cost' => 1,
            'delivery_fee' => 0,
            'currency' => 'OMR',
        ]);
        $request->forceFill(['created_at' => Carbon::parse($utc, 'UTC')])->save();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    private function numbers(array $params): array
    {
        $rows = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.packaging.requests.datatable'), array_merge(['draw' => 1, 'start' => 0, 'length' => 100], $params))
            ->assertOk()
            ->json('data');

        return collect($rows)->pluck('request_number')->sort()->values()->all();
    }

    private function seedAroundTheTwentyThird(string $prefix): void
    {
        $this->requestCreatedAt($prefix.'-A-BEFORE', '2026-11-22 19:59:59'); // 22 Nov 23:59:59 Oman
        $this->requestCreatedAt($prefix.'-B-START', '2026-11-22 20:00:00');  // 23 Nov 00:00 Oman
        $this->requestCreatedAt($prefix.'-C-END', '2026-11-23 19:59:59');    // 23 Nov 23:59:59 Oman
        $this->requestCreatedAt($prefix.'-D-AFTER', '2026-11-23 20:00:00');  // 24 Nov 00:00 Oman
    }

    public function test_same_day_range_includes_the_whole_oman_day_only(): void
    {
        $this->seedAroundTheTwentyThird('PKG');

        $this->assertSame(['PKG-B-START', 'PKG-C-END'], $this->numbers(['date_from' => '2026-11-23', 'date_to' => '2026-11-23']));
    }

    public function test_multi_day_range_and_open_ended_bounds(): void
    {
        $this->seedAroundTheTwentyThird('PKG');

        $this->assertSame(['PKG-A-BEFORE', 'PKG-B-START', 'PKG-C-END'], $this->numbers(['date_from' => '2026-11-22', 'date_to' => '2026-11-23']));
        $this->assertSame(['PKG-D-AFTER'], $this->numbers(['date_from' => '2026-11-24']));
        $this->assertSame(['PKG-A-BEFORE'], $this->numbers(['date_to' => '2026-11-22']));
    }

    public function test_search_box_does_not_bypass_the_date_filter(): void
    {
        $this->seedAroundTheTwentyThird('PKG');

        $this->assertSame(['PKG-B-START', 'PKG-C-END'], $this->numbers([
            'date_from' => '2026-11-23',
            'date_to' => '2026-11-23',
            'search' => ['value' => $this->vendor->store_name],
        ]));
    }

    public function test_malformed_dates_are_ignored_instead_of_failing(): void
    {
        $this->seedAroundTheTwentyThird('PKG');

        $this->assertCount(4, $this->numbers(['date_from' => 'not-a-date']));
    }
}
