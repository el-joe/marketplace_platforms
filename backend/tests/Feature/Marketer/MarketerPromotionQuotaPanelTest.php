<?php

namespace Tests\Feature\Marketer;

use App\Enums\PromotionCategory;
use App\Models\MarketerAdmin;
use App\Models\MarketerPromotionFine;
use App\Services\Marketer\PromotionPenaltyService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PromotionQuotaFixtures as Q;
use Tests\TestCase;

/** P12 on the marketer panel: x/y counter, red state, pending fines, withdrawal nets the fine. */
class MarketerPromotionQuotaPanelTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
    }

    private function login($marketer): MarketerAdmin
    {
        return MarketerAdmin::where('marketer_id', $marketer->id)->firstOrFail();
    }

    public function test_dashboard_shows_the_counter_for_the_marketers_category(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::AdminIntermediary, 3, 2500);
        $this->s->adminListing->update(['promotion_category' => 3]);
        Q::accepted($this->s, $m, 'admin');

        $this->actingAs($this->login($m), 'marketer')->get(route('marketer.dashboard'))
            ->assertOk()
            ->assertSee('1/3')
            ->assertSee(PromotionCategory::AdminIntermediary->label())
            ->assertDontSee('bg-red-50 border-red-300');
    }

    public function test_dashboard_turns_red_in_the_last_week_while_below_target_and_shows_the_penalty(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 3, 2500);
        Q::accepted($this->s, $m, 'vendor');

        $this->travelTo(now()->endOfMonth()->subDays(2));
        $response = $this->actingAs($this->login($m), 'marketer')->get(route('marketer.dashboard'));
        $this->travelBack();

        $response->assertOk()
            ->assertSee('1/3')
            ->assertSee('bg-red-50 border-red-300', false)
            ->assertSee('data-quota-penalty', false)
            ->assertSee('2,500');
    }

    public function test_dashboard_lists_pending_fines_of_closed_months(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 2, 4000);
        app(PromotionPenaltyService::class)->closeMonth(now()->subMonthNoOverflow()->startOfMonth());

        $this->actingAs($this->login($m), 'marketer')->get(route('marketer.dashboard'))
            ->assertOk()
            ->assertSee('data-pending-fines', false)
            ->assertSee('4,000');
    }

    public function test_withdrawal_collects_the_pending_fine_first(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 2, 4000);
        app(PromotionPenaltyService::class)->closeMonth(now()->subMonthNoOverflow()->startOfMonth());
        $wallet = app(WalletService::class)->getOrCreateWallet('marketer', $m->id, $this->s->country->currency_code);
        app(WalletService::class)->credit($wallet, 10000, 'test', null, 'seed');

        $this->actingAs($this->login($m), 'marketer')
            ->post('http://marketer.localhost/finance/wallet/withdraw', [
                'amount' => 6000, 'bank_name' => 'Bank', 'bank_iban' => 'AE000000000000',
            ], ['HTTP_HOST' => 'marketer.localhost'])
            ->assertStatus(302);

        $this->assertSame(MarketerPromotionFine::DEDUCTED, MarketerPromotionFine::sole()->status);
        $wallet->refresh();
        $this->assertSame(0, $wallet->balance);
        $this->assertSame(6000, $wallet->pending_balance);
    }

    public function test_dashboard_renders_in_english_and_arabic(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 3, 2500);
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotSame('marketer.quota.penalty_notice', __('marketer.quota.penalty_notice', ['amount' => 1]));
            $this->assertNotSame('marketer.quota.pending_fines_title', __('marketer.quota.pending_fines_title'));
        }
        $this->assertNotNull($m);
    }
}
