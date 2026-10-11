<?php

namespace Tests\Unit\Notifications;

use App\Notifications\Customer\WalletCredited;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class WalletCreditedTest extends TestCase
{
    public function test_broadcasts_on_the_private_customer_channel_when_the_customer_id_is_given(): void
    {
        $channels = (new WalletCredited(500, 'Refund', 'customer-123'))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-customer.customer-123', $channels[0]->name);
    }

    public function test_returns_no_explicit_channel_instead_of_throwing_when_the_customer_id_is_missing(): void
    {
        $this->assertSame([], (new WalletCredited(500, 'Refund'))->broadcastOn());
    }
}
