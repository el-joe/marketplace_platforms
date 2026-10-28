<?php

namespace Tests\Feature\Marketer;

use App\Events\MarketerChatMessageSent;
use App\Models\MarketerAdmin;
use App\Models\MarketerConversation;
use App\Models\MarketerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerChatTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->s->marketer->forceFill(['global_status' => 'active'])->save();
        $profile = MarketerProfile::firstOrNew(['marketer_id' => $this->s->marketer->id]);
        $profile->profile_slug = $this->slug = 'chat-'.Str::lower(Str::random(6));
        $profile->save();
    }

    private function base(): string
    {
        return '/api/customer/v1/'.$this->s->country->site_code.'/chats';
    }

    public function test_customer_starts_chat_sends_message_and_marketer_is_flagged_unread(): void
    {
        Event::fake([MarketerChatMessageSent::class]);

        $start = $this->actingAs($this->s->customer, 'customer')
            ->postJson($this->base(), ['marketer_slug' => $this->slug])->assertOk();
        $id = $start->json('data.id');

        // Starting twice is idempotent (no duplicate profile-level conversation).
        $this->postJson($this->base(), ['marketer_slug' => $this->slug])->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, MarketerConversation::where('customer_id', $this->s->customer->id)->count());

        $this->postJson("{$this->base()}/{$id}/messages", ['body' => ' hello '])->assertCreated()
            ->assertJsonPath('data.body', 'hello')->assertJsonPath('data.sender_type', 'customer');

        $this->assertTrue((bool) MarketerConversation::find($id)->marketer_has_unread);
        Event::assertDispatched(MarketerChatMessageSent::class, fn ($e) => $e->message->body === 'hello');
    }

    public function test_unknown_or_inactive_marketer_cannot_be_chatted_with(): void
    {
        $this->actingAs($this->s->customer, 'customer')
            ->postJson($this->base(), ['marketer_slug' => 'nope'])->assertNotFound();

        $this->s->marketer->forceFill(['global_status' => 'suspended'])->save();
        $this->postJson($this->base(), ['marketer_slug' => $this->slug])->assertNotFound();
    }

    public function test_customer_cannot_read_or_post_to_another_customers_conversation(): void
    {
        $other = $this->s->customer->replicate();
        $other->email = 'other-'.Str::random(6).'@example.test';
        $other->phone = '+9715'.fake()->numerify('########');
        $other->save();
        $conv = MarketerConversation::forceCreate([
            'marketer_id' => $this->s->marketer->id, 'customer_id' => $other->id,
        ]);

        $this->actingAs($this->s->customer, 'customer')->getJson("{$this->base()}/{$conv->id}")->assertNotFound();
        $this->postJson("{$this->base()}/{$conv->id}/messages", ['body' => 'x'])->assertNotFound();
    }

    public function test_guest_is_rejected_and_body_is_validated(): void
    {
        $this->getJson($this->base())->assertUnauthorized();

        $this->actingAs($this->s->customer, 'customer');
        $id = $this->postJson($this->base(), ['marketer_slug' => $this->slug])->json('data.id');
        $this->postJson("{$this->base()}/{$id}/messages", ['body' => ''])->assertUnprocessable();
    }

    public function test_marketer_reply_flags_customer_unread_and_opening_marks_read(): void
    {
        $this->actingAs($this->s->customer, 'customer');
        $id = $this->postJson($this->base(), ['marketer_slug' => $this->slug])->json('data.id');

        $admin = MarketerAdmin::create([
            'marketer_id' => $this->s->marketer->id, 'name' => 'M', 'email' => 'm-'.Str::random(6).'@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
        $this->s->marketer->forceFill(['onboarding_completed_at' => now()])->save();
        $this->actingAs($admin, 'marketer_api')
            ->postJson("/api/marketer/conversations/{$id}/messages", ['body' => 'welcome'])->assertCreated();

        $this->assertTrue((bool) MarketerConversation::find($id)->customer_has_unread);

        $this->actingAs($this->s->customer, 'customer')->getJson("{$this->base()}/{$id}")
            ->assertOk()->assertJsonCount(1, 'data.messages');
        $this->assertFalse((bool) MarketerConversation::find($id)->customer_has_unread);
        $this->getJson("{$this->base()}/unread-count")->assertJsonPath('data.count', 0);
    }
}
