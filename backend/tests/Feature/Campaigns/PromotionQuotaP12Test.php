<?php

namespace Tests\Feature\Campaigns;

use App\Contracts\WhatsAppGateway;
use App\Enums\PromotionCategory;
use App\Models\MarketerPromotionFine;
use App\Models\MarketerPromotionReminder;
use App\Models\Wallet;
use App\Notifications\Marketer\PromotionQuotaBehindNotification;
use App\Services\Marketer\PromotionPenaltyService;
use App\Services\Marketer\PromotionQuotaService;
use App\Services\WalletService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PromotionQuotaFixtures as Q;
use Tests\TestCase;

/** P12: per-category progress counting, month close fines, last-week reminder, scheduler registration. */
class PromotionQuotaP12Test extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
    }

    public function test_each_category_counts_only_its_own_kind_of_accepted_promotion(): void
    {
        $service = app(PromotionQuotaService::class);
        $vendorM = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 3);
        $interM = Q::marketerIn($this->s, PromotionCategory::AdminIntermediary, 2);
        $nawyM = Q::marketerIn($this->s, PromotionCategory::NawyProducts, 2);
        $peerM = Q::marketerIn($this->s, PromotionCategory::PeerInfluencerProducts);

        // category 1: seller (vendor listing) campaigns
        Q::accepted($this->s, $vendorM, 'vendor');
        Q::accepted($this->s, $vendorM, 'vendor');
        Q::accepted($this->s, $vendorM, 'admin'); // admin listing not flagged: not category 1
        $this->assertSame(2, $service->progressFor($vendorM)['done']);

        // categories 3/4: admin listing flagged with that category
        Q::accepted($this->s, $interM, 'admin');
        $this->assertSame(0, $service->progressFor($interM)['done'], 'unflagged admin listing counts for nobody');
        $this->s->adminListing->update(['promotion_category' => 3]);
        $this->assertSame(1, $service->progressFor($interM)['done']);
        $this->assertSame(0, $service->progressFor($nawyM)['done']);
        Q::accepted($this->s, $nawyM, 'admin');
        $this->assertSame(0, $service->progressFor($nawyM)['done'], 'listing is category 3, not 4');
        $this->s->adminListing->update(['promotion_category' => 4]);
        $this->assertSame(1, $service->progressFor($nawyM)['done']);
        $this->assertSame(0, $service->progressFor($interM)['done']);

        // category 2 uses its contract default (4) and counts nothing until peer campaigns exist (P13)
        $p = $service->progressFor($peerM);
        $this->assertSame(4, $p['target']);
        $this->assertSame(0, $p['done']);

        // category 3/4 without an admin row and a category 1 default
        $this->assertSame(3, PromotionCategory::VendorRequests->defaultMinimum());
        $this->assertNull(PromotionCategory::NawyProducts->defaultMinimum());
    }

    public function test_progress_is_month_scoped_and_exceeding_the_target_is_allowed(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 1);
        Q::accepted($this->s, $m, 'vendor', now()->subMonthNoOverflow()->startOfMonth()->addDays(3));
        Q::accepted($this->s, $m, 'vendor');
        Q::accepted($this->s, $m, 'vendor');

        $p = app(PromotionQuotaService::class)->progressFor($m);
        $this->assertSame(2, $p['done']);
        $this->assertFalse($p['behind']);
        $this->assertSame('ok', $p['level']);
    }

    public function test_close_month_creates_one_fine_per_marketer_category_month_and_is_idempotent(): void
    {
        $short = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 3, 5000);
        $met = Q::marketerIn($this->s, PromotionCategory::VendorRequests);
        $noFine = Q::marketerIn($this->s, PromotionCategory::AdminIntermediary, 2, 0);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();
        Q::accepted($this->s, $short, 'vendor', $lastMonth->copy()->addDays(4));
        foreach (range(1, 3) as $_) {
            Q::accepted($this->s, $met, 'vendor', $lastMonth->copy()->addDays(5));
        }

        Artisan::call('promotion:close-month');
        Artisan::call('promotion:close-month');
        Artisan::call('promotion:close-month', ['--month' => $lastMonth->format('Y-m')]);

        $fines = MarketerPromotionFine::all();
        $this->assertCount(2, $fines, 'short marketer + zero-penalty marketer; met marketer none');
        $fine = $fines->firstWhere('marketer_id', $short->id);
        $this->assertSame(5000, $fine->amount);
        $this->assertSame($lastMonth->format('Y-m'), $fine->period);
        $this->assertSame(1, $fine->done);
        $this->assertSame(3, $fine->target);
        $this->assertSame(MarketerPromotionFine::PENDING, $fine->status);

        $zero = $fines->firstWhere('marketer_id', $noFine->id);
        $this->assertSame(0, $zero->amount);
        $this->assertSame(MarketerPromotionFine::WAIVED, $zero->status);
        $this->assertNull($fines->firstWhere('marketer_id', $met->id));
    }

    public function test_fine_is_deducted_from_the_wallet_once_it_can_cover_it(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 2, 4000);
        $penalties = app(PromotionPenaltyService::class);
        $month = now()->subMonthNoOverflow()->startOfMonth();

        $penalties->closeMonth($month);
        $fine = MarketerPromotionFine::sole();
        $this->assertSame(MarketerPromotionFine::PENDING, $fine->status, 'empty wallet: stays pending');

        $wallet = app(WalletService::class)->getOrCreateWallet('marketer', $m->id, $this->s->country->currency_code);
        $this->assertSame(0, $penalties->collect($m));

        app(WalletService::class)->credit($wallet, 10000, 'test', null, 'seed');
        $this->assertSame(1, $penalties->collect($m));
        $this->assertSame(0, $penalties->collect($m), 'second collect does nothing');

        $fine->refresh();
        $this->assertSame(MarketerPromotionFine::DEDUCTED, $fine->status);
        $this->assertNotNull($fine->wallet_transaction_id);
        $this->assertSame(6000, $wallet->fresh()->balance);

        // closing again must not create or re-charge
        $penalties->closeMonth($month);
        $this->assertSame(1, MarketerPromotionFine::count());
        $this->assertSame(6000, $wallet->fresh()->balance);
    }

    public function test_waived_fine_is_never_collected(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 2, 4000);
        $penalties = app(PromotionPenaltyService::class);
        $penalties->closeMonth(now()->subMonthNoOverflow()->startOfMonth());
        $fine = MarketerPromotionFine::sole();

        $this->assertTrue($penalties->waive($fine, \App\Models\Admin::factory()->create()->id, 'goodwill'));
        $this->assertFalse($penalties->waive($fine->fresh(), 'x'));

        $wallet = app(WalletService::class)->getOrCreateWallet('marketer', $m->id, $this->s->country->currency_code);
        app(WalletService::class)->credit($wallet, 10000, 'test', null, 'seed');
        $this->assertSame(0, $penalties->collect($m));
        $this->assertSame(10000, $wallet->fresh()->balance);
    }

    public function test_reminder_goes_out_once_and_only_in_the_last_week(): void
    {
        $gateway = Q::fakeGateway();
        $this->app->instance(WhatsAppGateway::class, $gateway);
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 3);
        $ok = Q::marketerIn($this->s, PromotionCategory::VendorRequests);
        foreach (range(1, 3) as $_) {
            Q::accepted($this->s, $ok, 'vendor');
        }

        // mid month: nothing
        $this->travelTo(now()->startOfMonth()->addDays(9));
        Artisan::call('marketers:promotion-quota-reminders');
        $this->assertCount(0, $gateway->sent);
        $this->assertSame(0, MarketerPromotionReminder::count());

        // last week: marketer short gets exactly one WhatsApp + in-app notification, repeated runs add nothing
        $this->travelTo(now()->endOfMonth()->subDays(2));
        Artisan::call('marketers:promotion-quota-reminders');
        Artisan::call('marketers:promotion-quota-reminders');
        $this->travelBack();

        $this->assertCount(1, $gateway->sent);
        $this->assertSame($m->whatsapp_for_campaigns, $gateway->sent[0]['to']);
        $this->assertSame(1, MarketerPromotionReminder::where('marketer_id', $m->id)->where('whatsapp_sent', true)->count());
        $this->assertSame(0, MarketerPromotionReminder::where('marketer_id', $ok->id)->count());
        Notification::assertSentTimes(PromotionQuotaBehindNotification::class, 1);
    }

    public function test_close_month_and_reminders_are_registered_in_the_scheduler(): void
    {
        $events = collect(app(Schedule::class)->events());

        $close = $events->first(fn ($e) => str_contains((string) $e->command, 'promotion:close-month'));
        $this->assertNotNull($close, 'promotion:close-month must be scheduled');
        $this->assertSame('30 0 1 * *', $close->expression);

        $this->assertTrue($events->contains(fn ($e) => str_contains((string) $e->command, 'marketers:promotion-quota-reminders')));
    }
}
