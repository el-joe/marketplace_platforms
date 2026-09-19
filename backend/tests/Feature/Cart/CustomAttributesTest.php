<?php

namespace Tests\Feature\Cart;

use App\Models\Product;
use App\Models\ProductCustomAttribute;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CustomAttributesTest extends TestCase
{
    use RefreshDatabase;

    private function setup2(): array
    {
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $s->product->update(['has_custom_attributes' => true]);
        $svc = app(CartService::class);
        $cart = $svc->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);

        return [$s, $svc, $cart];
    }

    private function mk(Product $p, array $a): ProductCustomAttribute
    {
        return $p->customAttributes()->create($a + ['label' => 'x']);
    }

    private function add($svc, $cart, $s, array $vals)
    {
        return $svc->addItem($cart, $s->vendorListingFbp->id, 1, null, $s->country->id, $vals);
    }

    public function test_required_missing_rejected_and_types_validated(): void
    {
        [$s, $svc, $cart] = $this->setup2();
        $req = $this->mk($s->product, ['label' => 'Len', 'type' => 'number', 'is_required' => true]);
        $sel = $this->mk($s->product, ['label' => 'Style', 'type' => 'select', 'options' => ['open', 'closed']]);
        $chk = $this->mk($s->product, ['label' => 'Lined', 'type' => 'checkbox']);
        $v = fn ($id, $val) => ['product_custom_attribute_id' => $id, 'value' => $val];

        $bad = [
            [],                                              // required missing
            [$v($req->id, 'abc')],                           // non-numeric
            [$v($req->id, '5'), $v($sel->id, 'half')],       // option not in list
            [$v($req->id, '5'), $v($chk->id, 'maybe')],      // bad checkbox
        ];
        foreach ($bad as $vals) {
            try {
                $this->add($svc, $cart, $s, $vals);
                $this->fail('expected DomainException');
            } catch (\DomainException) {
                $this->assertTrue(true);
            }
        }
        $item = $this->add($svc, $cart, $s, [$v($req->id, '5'), $v($sel->id, 'open'), $v($chk->id, 'true')]);
        $this->assertSame(3, $item->customAttributeValues()->count());
    }

    public function test_foreign_product_attribute_rejected(): void
    {
        [$s, $svc, $cart] = $this->setup2();
        $other = Product::factory()->create(['category_id' => $s->category->id]);
        $foreign = $this->mk($other, ['label' => 'F']);
        $this->expectException(\DomainException::class);
        $this->add($svc, $cart, $s, [['product_custom_attribute_id' => $foreign->id, 'value' => 'z']]);
    }

    public function test_same_product_different_values_stay_separate_lines(): void
    {
        [$s, $svc, $cart] = $this->setup2();
        $a = $this->mk($s->product, ['label' => 'Size', 'type' => 'number']);
        $i1 = $this->add($svc, $cart, $s, [['product_custom_attribute_id' => $a->id, 'value' => '10']]);
        $i2 = $this->add($svc, $cart, $s, [['product_custom_attribute_id' => $a->id, 'value' => '20']]);
        $i3 = $this->add($svc, $cart, $s, [['product_custom_attribute_id' => $a->id, 'value' => '10']]);
        $this->assertNotSame($i1->id, $i2->id);
        $this->assertSame($i1->id, $i3->id);
        $this->assertSame(2, $cart->items()->count());
    }
}
