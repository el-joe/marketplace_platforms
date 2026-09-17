<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Jobs\SendGiftCardDeliveryJob;
use App\Mail\GiftCardDeliveryMail;
use App\Models\GiftCard;
use App\Models\GiftCardBatch;
use App\Models\GiftCardPurchase;
use App\Services\GiftCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Gift card lifecycle audit (2026-09-18): purchase -> delivery -> wallet
 * redemption -> can't redeem twice, plus the payment-status honesty and
 * batch-expiry-default fixes made alongside it.
 */
class GiftCardPurchaseAndRedemptionTest extends TestCase
{
    use RefreshDatabase;

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function makeBatch(array $overrides = []): GiftCardBatch
    {
        return GiftCardBatch::create(array_merge([
            'name' => 'Congratulations 100',
            'title_en' => 'Congratulations',
            'amount' => 100,
            'currency_code' => 'AED',
            'quantity' => 5,
            'is_purchasable' => true,
            'min_quantity' => 1,
            'max_quantity' => 5,
            'sort_order' => 1,
        ], $overrides));
    }

    /** Seeds one active, unassigned card directly (bypassing the admin PIN-generation flow). */
    private function makeActiveCard(GiftCardBatch $batch, string $pin = '1234'): GiftCard
    {
        return GiftCard::create([
            'id' => (string) Str::uuid(),
            'gift_card_batch_id' => $batch->id,
            'source' => 'batch',
            'code' => GiftCard::generateUniqueCode(),
            'pin_hash' => Hash::make($pin),
            'amount' => $batch->amount,
            'remaining_balance' => $batch->amount,
            'currency_code' => $batch->currency_code,
            'status' => 'active',
        ]);
    }

    public function test_full_lifecycle_purchase_then_redeem_then_cannot_redeem_twice(): void
    {
        Mail::fake();
        Queue::fake([SendGiftCardDeliveryJob::class]);

        $scenario = $this->buildScenario();
        $batch = $this->makeBatch();
        $this->makeActiveCard($batch);

        $this->actingAs($scenario->customer, 'customer');

        // COD is not a valid gift-card payment method (nothing to physically
        // deliver), so pick a non-COD gateway explicitly rather than the first one.
        $gateway = $scenario->countryPaymentGateways['wallet'];

        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-store/purchase",
            [
                'gift_card_batch_id' => $batch->id,
                'quantity' => 1,
                'country_payment_gateway_id' => $gateway->id,
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $purchase = GiftCardPurchase::firstOrFail();
        $order = $purchase->order;

        // Gap #1: the order must not be marked "completed" with unconfirmed
        // payment — status should reflect "placed, payment pending", not done.
        $this->assertSame(OrderStatus::Placed, $order->status);
        $this->assertSame(OrderPaymentStatus::Pending, $order->payment_status);

        // Gap #2/#3: purchase dispatches the real delivery job (which mints a
        // fresh PIN and emails it), not the broken/dead notification job.
        Queue::assertPushed(SendGiftCardDeliveryJob::class, fn ($job) => $job->giftCardPurchaseId === $purchase->id);

        app(\App\Services\GiftCardPurchaseService::class)->deliverCard($purchase->fresh());

        Mail::assertSent(GiftCardDeliveryMail::class, function (GiftCardDeliveryMail $mail) use ($purchase) {
            return $mail->purchase->id === $purchase->id && strlen($mail->plainPin) === 4;
        });

        $sentPin = null;
        Mail::assertSent(GiftCardDeliveryMail::class, function (GiftCardDeliveryMail $mail) use (&$sentPin) {
            $sentPin = $mail->plainPin;

            return true;
        });

        $card = $purchase->giftCard()->firstOrFail();

        $walletBalanceBefore = \App\Models\CustomerWallet::where('customer_id', $scenario->customer->id)
            ->where('currency_code', 'AED')
            ->value('balance') ?? 0;

        // Redeem into the wallet using the freshly-minted PIN sent by email.
        $redeemResponse = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-wallet/redeem-gift-card",
            ['code' => $card->code, 'pin' => $sentPin]
        );

        $redeemResponse->assertStatus(200);
        $redeemResponse->assertJsonPath('success', true);
        $redeemResponse->assertJsonPath('data.wallet.new_balance', $walletBalanceBefore + 100);

        $this->assertSame('redeemed', $card->fresh()->status);
        $this->assertSame(0, $card->fresh()->remaining_balance);

        // Redeeming the same code+PIN again must fail — no double credit.
        $secondAttempt = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-wallet/redeem-gift-card",
            ['code' => $card->code, 'pin' => $sentPin]
        );

        $secondAttempt->assertStatus(422);
        $secondAttempt->assertJsonPath('success', false);
    }

    public function test_cannot_purchase_gift_card_with_cash_on_delivery(): void
    {
        $scenario = $this->buildScenario();
        $batch = $this->makeBatch();
        $this->makeActiveCard($batch);

        $this->actingAs($scenario->customer, 'customer');

        $codGateway = $scenario->countryPaymentGateways['cod'];

        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-store/purchase",
            [
                'gift_card_batch_id' => $batch->id,
                'quantity' => 1,
                'country_payment_gateway_id' => $codGateway->id,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['country_payment_gateway_id']);
        $this->assertSame(0, GiftCardPurchase::count());
    }

    public function test_generate_batch_defaults_expiry_to_one_year_when_blank(): void
    {
        $scenario = $this->buildScenario();

        $admin = \App\Models\Admin::factory()->create();

        $result = app(GiftCardService::class)->generateBatch([
            'name' => 'No expiry given',
            'amount' => 50,
            'currency_code' => 'AED',
            'quantity' => 2,
        ], $admin);

        $batch = $result['batch']->fresh();

        $this->assertNotNull($batch->expires_at);
        $this->assertTrue($batch->expires_at->between(now()->addYear()->subDay(), now()->addYear()->addDay()));

        $card = $batch->giftCards()->first();
        $this->assertNotNull($card->expires_at);
    }

    public function test_expire_due_cards_is_idempotent_on_rerun(): void
    {
        $scenario = $this->buildScenario();
        $batch = $this->makeBatch();
        $card = $this->makeActiveCard($batch);
        $card->update(['expires_at' => now()->subDay(), 'status' => 'active']);

        $service = app(GiftCardService::class);

        $firstRun = $service->expireDueCards();
        $this->assertSame(1, $firstRun);
        $this->assertSame('expired', $card->fresh()->status);
        $this->assertSame(1, $card->fresh()->transactions()->where('type', 'expiry')->count());

        // Re-running the scheduled command must not touch already-expired
        // cards or create a second expiry transaction.
        $secondRun = $service->expireDueCards();
        $this->assertSame(0, $secondRun);
        $this->assertSame(1, $card->fresh()->transactions()->where('type', 'expiry')->count());
    }
}
