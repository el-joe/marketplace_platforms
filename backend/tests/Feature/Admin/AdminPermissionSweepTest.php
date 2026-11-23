<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminListingController;
use App\Http\Controllers\Admin\AdSlotController;
use App\Http\Controllers\Admin\AppContextController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CartCardOfferController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DisputeController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\MarketerCampaignController;
use App\Http\Controllers\Admin\MarketerSettingsController;
use App\Http\Controllers\Admin\PageBuilderController;
use App\Http\Controllers\Admin\PaidAdBookingController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\VendorApplicationController;
use App\Http\Controllers\Admin\VendorListingController;
use App\Http\Controllers\Admin\WarrantyClaimController;
use App\Models\Admin;
use App\Models\SubscriptionPlan;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Client item #16b: every admin route must be behind an `admin.permission:*`
 * gate, and mutating actions must not be reachable with a `*.view` permission alone.
 */
class AdminPermissionSweepTest extends TestCase
{
    use RefreshDatabase;

    /** Admin routes that are intentionally reachable by any signed-in (or guest) admin. */
    private const UNGATED_ALLOW_LIST = [
        'admin.login', 'admin.login.submit', 'admin.logout',
        'admin.password.reset', 'admin.password.update', 'admin.password.request', 'admin.password.email',
        'admin.set-locale', 'admin.country',
        'admin.profile.edit', 'admin.profile.update',
        'admin.admins.stop-impersonating',
        // Own notifications, scoped to the signed-in admin.
        'admin.notifications.index', 'admin.notifications.recent', 'admin.notifications.unread',
        'admin.notifications.unread-count', 'admin.notifications.mark-all-read', 'admin.notifications.mark-read',
        // Own acquisition commissions, scoped to the signed-in admin.
        'admin.my-acquisition-commissions.index', 'admin.my-acquisition-commissions.datatable',
    ];

    /** Unnamed admin routes (by URI) that are allowed without a permission gate. */
    private const UNGATED_URI_ALLOW_LIST = [
        'broadcasting/auth',                       // channel authorization does its own checks
        'travel-agencies/bookable-units',          // redirect to a gated route
        'travel-agencies/bookable-units/{any}',    // redirect to a gated route
    ];

    /** Read-only routes that use POST (calculators, server-side tables). */
    private const READ_ONLY_POST_ROUTES = [
        'admin.marketing-fees.calculate',
        'admin.products.cost.calculate',
        'admin.shipping-zones.rates.estimate',
        'admin.vouchers.redemptions.data',
    ];

    /**
     * Controllers whose mutating actions sit in a `*.view` route group but check a
     * stronger permission inside every action (abort_unless / authorize / policy).
     */
    private const CONTROLLER_ENFORCED = [
        AdCampaignController::class, AdSlotController::class, AdminController::class,
        AdminListingController::class, AppContextController::class, BannerController::class,
        CartCardOfferController::class, CouponController::class, CustomerController::class,
        DisputeController::class, FaqController::class, MarketerCampaignController::class,
        MarketerSettingsController::class, PageBuilderController::class, PaidAdBookingController::class,
        ReturnController::class, ReviewController::class, RoleController::class,
        SupportTicketController::class, TransactionController::class, VendorApplicationController::class,
        VendorListingController::class, WarrantyClaimController::class,
    ];

    /** @return list<RoutingRoute> */
    private function adminRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $route) => Str::startsWith((string) $route->getDomain(), 'admin.')
        ));
    }

    /** @return list<string> */
    private function permissionsOf(RoutingRoute $route): array
    {
        $permissions = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && Str::startsWith($middleware, 'admin.permission:')) {
                array_push($permissions, ...explode('|', Str::after($middleware, 'admin.permission:')));
            }
        }

        return $permissions;
    }

    private function isMutating(RoutingRoute $route): bool
    {
        return array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];
    }

    public function test_every_admin_route_is_behind_a_permission_gate(): void
    {
        $ungated = [];
        foreach ($this->adminRoutes() as $route) {
            $allowed = in_array($route->getName(), self::UNGATED_ALLOW_LIST, true)
                || in_array($route->uri(), self::UNGATED_URI_ALLOW_LIST, true)
                || Str::startsWith((string) $route->getName(), 'admin.docs.');

            if (! $allowed && $this->permissionsOf($route) === []) {
                $ungated[] = implode('|', $route->methods()).' '.$route->uri().' ('.$route->getName().')';
            }
        }

        $this->assertNotEmpty($this->adminRoutes());
        $this->assertSame([], $ungated, "Admin routes without an admin.permission gate:\n".implode("\n", $ungated));
    }

    public function test_mutating_admin_routes_need_more_than_a_view_permission(): void
    {
        $weak = [];
        foreach ($this->adminRoutes() as $route) {
            $permissions = $this->permissionsOf($route);
            $controller = Str::before((string) $route->getActionName(), '@');

            if (! $this->isMutating($route)
                || $permissions === []
                || Str::contains($route->uri(), 'datatable')
                || in_array($route->getName(), self::READ_ONLY_POST_ROUTES, true)
                || in_array($controller, self::CONTROLLER_ENFORCED, true)) {
                continue;
            }

            $hasActionPermission = collect($permissions)->contains(fn (string $p) => ! Str::endsWith($p, '.view'));
            if (! $hasActionPermission) {
                $weak[] = $route->uri().' ('.$route->getName().') → '.implode(', ', $permissions);
            }
        }

        $this->assertSame([], $weak, "Mutating admin routes gated only by *.view:\n".implode("\n", $weak));
    }

    public function test_sidebar_items_use_the_permission_that_opens_their_page(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $mismatches = [];
        foreach (app(NavigationService::class)->adminNavigation() as $group) {
            foreach ($group['items'] as $item) {
                if (empty($item['route']) || empty($item['permission']) || ! Route::has($item['route'])) {
                    continue;
                }
                $routePermissions = $this->permissionsOf(Route::getRoutes()->getByName($item['route']));
                if ($routePermissions !== [] && ! in_array($item['permission'], $routePermissions, true)) {
                    $mismatches[] = $item['route'].': sidebar '.$item['permission'].' vs route '.implode(', ', $routePermissions);
                }
            }
        }

        $this->assertSame([], $mismatches, implode("\n", $mismatches));
    }

    public function test_every_new_permission_group_has_an_arabic_and_english_label(): void
    {
        foreach (['orders', 'subscriptions', 'wallets', 'fbn', 'delivery', 'shipping_companies', 'contracts', 'classifieds', 'acquisition_commissions', 'ai'] as $group) {
            $key = 'admin.permission_groups.'.$group;
            $this->assertNotSame($key, __($key, [], 'en'), "Missing EN label for {$group}");
            $this->assertNotSame($key, __($key, [], 'ar'), "Missing AR label for {$group}");
        }
    }

    // ── Behaviour ────────────────────────────────────────────────────────────

    private function adminWithRole(string $roleName, array $permissions = []): Admin
    {
        foreach (['vendors.assigned_only', ...$permissions] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'admin']);
        $role->givePermissionTo($permissions);

        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    private function faqOnlyAdmin(): Admin
    {
        return $this->adminWithRole('faqs_only_'.Str::random(6), ['faqs.view', 'faqs.create', 'faqs.edit', 'faqs.delete']);
    }

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name_en' => 'Gold '.Str::random(4),
            'name_ar' => 'ذهبي',
            'price' => 10,
            'currency' => 'OMR',
            'billing_cycle' => 'monthly',
            'commission_discount_pct' => 0,
        ]);
    }

    public function test_faq_only_admin_can_open_faqs_but_not_subscription_plans(): void
    {
        $admin = $this->faqOnlyAdmin();
        $plan = $this->plan();

        $this->actingAs($admin, 'admin')->get(route('admin.faqs.index'))->assertOk();
        $this->get(route('admin.subscriptions.plans.index'))->assertForbidden();
        $this->postJson(route('admin.subscriptions.plans.store'), ['name_en' => 'X'])->assertForbidden();
        $this->putJson(route('admin.subscriptions.plans.update', $plan), ['name_en' => 'X'])->assertForbidden();
        $this->postJson(route('admin.subscriptions.plans.toggle-active', $plan))->assertForbidden();
        $this->deleteJson(route('admin.subscriptions.plans.destroy', $plan))->assertForbidden();

        $this->assertModelExists($plan);
    }

    public function test_subscriptions_viewer_sees_plans_without_management_buttons(): void
    {
        $viewer = $this->adminWithRole('subs_viewer_'.Str::random(6), ['subscriptions.view']);
        $plan = $this->plan();

        $this->actingAs($viewer, 'admin')->withSession(['locale' => 'en'])->get(route('admin.subscriptions.plans.index'))
            ->assertOk()
            ->assertSee($plan->name_en)
            ->assertDontSee('id="btn-create-plan"', false)
            ->assertDontSee('btn-danger btn-delete-plan', false);
        $this->deleteJson(route('admin.subscriptions.plans.destroy', $plan))->assertForbidden();
    }

    public function test_super_admin_still_manages_subscription_plans(): void
    {
        $admin = $this->adminWithRole('super_admin');
        $plan = $this->plan();

        $this->actingAs($admin, 'admin')->get(route('admin.subscriptions.plans.index'))
            ->assertOk()
            ->assertSee('id="btn-create-plan"', false)
            ->assertSee('btn-danger btn-delete-plan', false);
        $this->deleteJson(route('admin.subscriptions.plans.destroy', $plan))->assertOk();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function fbnStorageFeeActions(): array
    {
        return [
            'index' => ['get', 'admin.fbn.storage-fees.index'],
            'generate' => ['postJson', 'admin.fbn.storage-fees.generate'],
            'rules index' => ['get', 'admin.fbn.storage-fees.free-period-rules.index'],
            'rule store' => ['postJson', 'admin.fbn.storage-fees.free-period-rules.store'],
            'recalculate pending' => ['postJson', 'admin.fbn.storage-fees.free-period-rules.recalculate-pending'],
            'inbound index' => ['get', 'admin.fbn.inbound.index'],
            'marketplace rule store' => ['postJson', 'admin.fbn.marketplace.store'],
        ];
    }

    #[DataProvider('fbnStorageFeeActions')]
    public function test_faq_only_admin_cannot_use_fbn_screens_or_actions(string $method, string $routeName): void
    {
        $this->actingAs($this->faqOnlyAdmin(), 'admin')->{$method}(route($routeName))->assertForbidden();
    }

    public function test_fbn_storage_fee_viewer_cannot_generate_or_change_rules(): void
    {
        $viewer = $this->adminWithRole('fees_viewer_'.Str::random(6), ['fbn_storage_fees.view']);

        $this->actingAs($viewer, 'admin')->get(route('admin.fbn.storage-fees.index'))->assertOk();
        $this->postJson(route('admin.fbn.storage-fees.generate'))->assertForbidden();
        $this->postJson(route('admin.fbn.storage-fees.free-period-rules.store'))->assertForbidden();
        $this->postJson(route('admin.fbn.storage-fees.free-period-rules.recalculate-pending'))->assertForbidden();
    }

    /** @return array<string, array{0: string}> */
    public static function orderActions(): array
    {
        return [
            'force cancel' => ['admin.orders.force-cancel'],
            'cancel items' => ['admin.orders.cancel-items'],
            'refund' => ['admin.orders.refund'],
            'status change' => ['admin.orders.update-status'],
            'flag fraud' => ['admin.orders.flag-fraud'],
            'dispute' => ['admin.orders.dispute'],
        ];
    }

    #[DataProvider('orderActions')]
    public function test_order_viewer_cannot_run_order_actions(string $routeName): void
    {
        $viewer = $this->adminWithRole('orders_viewer_'.Str::random(6), ['orders.view']);

        $this->actingAs($viewer, 'admin')
            ->postJson(route($routeName, ['id' => (string) Str::uuid()]))
            ->assertForbidden();
    }

    public function test_order_actions_require_their_own_permission(): void
    {
        $canceller = $this->adminWithRole('orders_cancel_'.Str::random(6), ['orders.view', 'orders.cancel']);
        $orderId = (string) Str::uuid();

        $this->actingAs($canceller, 'admin');
        $this->assertNotForbidden($this->postJson(route('admin.orders.force-cancel', ['id' => $orderId])));
        $this->postJson(route('admin.orders.refund', ['id' => $orderId]))->assertForbidden();
        $this->postJson(route('admin.orders.update-status', ['id' => $orderId]))->assertForbidden();
        $this->postJson(route('admin.orders.update-sub-order-status'))->assertForbidden();
    }

    private function assertNotForbidden(TestResponse $response): void
    {
        $this->assertNotSame(403, $response->getStatusCode(), 'Expected the permission gate to let the request through.');
    }
}
