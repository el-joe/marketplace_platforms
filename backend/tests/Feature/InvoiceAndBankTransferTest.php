<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class InvoiceAndBankTransferTest extends TestCase
{
    use RefreshDatabase;

    private function place(string $gateway): array
    {
        \Illuminate\Support\Facades\Notification::fake();
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->actingAs($s->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $s->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $s->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $r = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways[$gateway]->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $r->assertStatus(201);
        $n = $r->json('data.order.order_number') ?? $r->json('data.order_number');

        return [$s, Order::where('order_number', $n)->firstOrFail()];
    }

    private function other(MarketplaceScenario $s): Customer
    {
        return Customer::create(["name"=>"O","email"=>Str::random(6)."@x.test","phone"=>"9".random_int(1000000,9999999),"password"=>"password12","status"=>"active"]);
    }

    public function test_invoice_owner_only_and_has_tax_discount_lines(): void
    {
        [$s, $order] = $this->place('cod');
        $base = "/api/customer/v1/{$s->country->site_code}/api-orders/{$order->order_number}/invoice";

        $this->getJson($base)->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonStructure(['data' => ['summary' => ['subtotal', 'discount', 'shipping', 'tax', 'total']]]);

        $this->actingAs($this->other($s), 'customer');
        $this->getJson($base)->assertStatus(404);
    }

    public function test_invoice_requires_auth(): void
    {
        [$s, $order] = $this->place('cod');
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/customer/v1/{$s->country->site_code}/api-orders/{$order->order_number}/invoice")->assertStatus(401);
    }

    public function test_bank_transfer_details_only_for_bank_transfer_and_owner(): void
    {
        [$s, $bank] = $this->place('bank_transfer');
        $url = "/api/customer/v1/{$s->country->site_code}/orders/{$bank->order_number}";
        $r = $this->getJson($url)->assertOk();
        $this->assertSame($bank->order_number, $r->json('data.bank_transfer_details.details.reference'));

        $this->actingAs($this->other($s), 'customer');
        $this->getJson($url)->assertStatus(404);
    }

    public function test_no_bank_details_for_cod(): void
    {
        [$s, $cod] = $this->place('cod');
        $this->getJson("/api/customer/v1/{$s->country->site_code}/orders/{$cod->order_number}")
            ->assertOk()->assertJsonPath('data.bank_transfer_details', null);
    }
}
