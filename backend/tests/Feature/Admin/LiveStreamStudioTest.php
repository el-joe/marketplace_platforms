<?php

namespace Tests\Feature\Admin;

use App\Enums\LiveStreamStatus;
use App\Events\StreamSignal;
use App\Models\Admin;
use App\Models\LiveStream;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LiveStreamStudioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $perms = ['pages.view']): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($perms);

        return $admin;
    }

    private function stream(string $status = 'live'): LiveStream
    {
        return LiveStream::create([
            'title_en' => 'T', 'title_ar' => 'ت', 'status' => $status,
        ]);
    }

    public function test_nav_lists_live_streams_outside_marketing(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $groups = collect(app(NavigationService::class)->adminNavigation());

        $group = $groups->first(fn ($g) => collect($g['items'])->pluck('route')->contains('admin.live-streams.index'));

        $this->assertNotNull($group);

        $marketing = $groups->first(fn ($g) => $g['group'] === __('admin.nav.marketing'));
        if ($marketing) {
            $this->assertNotContains('admin.live-streams.index', collect($marketing['items'])->pluck('route')->all());
        }
    }

    public function test_show_page_renders_meta_tags_and_studio_assets(): void
    {
        config(['services.turn.tls_url' => 'turns:turn.example.com:5349', 'services.turn.url' => 'turn:turn.example.com:3478']);
        $stream = $this->stream('scheduled');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.live-streams.show', $stream))
            ->assertOk()
            ->assertSee('content="'.$stream->stream_key.'"', false)
            ->assertSee('name="turns-url"', false)
            ->assertSee('content="turns:turn.example.com:5349"', false)
            ->assertSee('name="turn-url"', false)
            ->assertSee('LIVE_STUDIO_I18N', false)
            ->assertSee('live-stream-studio.js', false)
            ->assertSee('id="sel-mode"', false)
            ->assertDontSee('unpkg.com/laravel-echo', false);
    }

    public function test_media_endpoint_persists_and_broadcasts_when_live(): void
    {
        Event::fake([StreamSignal::class]);
        $stream = $this->stream();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson(route('admin.live-streams.media', $stream), ['mode' => 'audio'])
            ->assertOk()->assertJson(['media_mode' => 'audio']);

        $this->assertSame('audio', $stream->fresh()->media_mode);
        Event::assertDispatched(StreamSignal::class, fn ($e) => $e->type === 'media-state'
            && $e->payload === ['mode' => 'audio', 'audio' => true, 'video' => false]
            && $e->fromPeerId === 'admin' && $e->targetPeerId === null);

        $this->actingAs($admin, 'admin')->postJson(route('admin.live-streams.media', $stream), ['mode' => 'bogus'])
            ->assertStatus(422);
    }

    public function test_public_detail_and_list_expose_media_mode(): void
    {
        $stream = $this->stream();
        $stream->update(['media_mode' => 'video']);

        $this->getJson(route('public.streams.show', $stream))->assertOk()->assertJsonPath('data.media_mode', 'video');
        $this->getJson(route('public.streams.index'))->assertOk()->assertJsonPath('data.0.media_mode', 'video');
    }

    public function test_go_live_broadcasts_hello_and_end_broadcasts_ended_idempotently(): void
    {
        Event::fake([StreamSignal::class]);
        $stream = $this->stream('scheduled');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson(route('admin.live-streams.go-live', $stream))->assertOk();
        $this->assertTrue($stream->fresh()->isLive());
        Event::assertDispatched(StreamSignal::class, fn ($e) => $e->type === 'hello' && $e->fromPeerId === 'admin' && $e->targetPeerId === null);

        $this->postJson(route('admin.live-streams.end', $stream))->assertOk();
        $this->assertSame(LiveStreamStatus::Ended, $stream->fresh()->status);
        Event::assertDispatched(StreamSignal::class, fn ($e) => $e->type === 'ended');
        Event::assertDispatched(StreamSignal::class, fn ($e) => $e->type === 'media-state' && $e->payload['mode'] === null);

        $this->postJson(route('admin.live-streams.end', $stream))->assertOk()->assertJson(['success' => true]);
        Event::assertDispatchedTimes(StreamSignal::class, 3);
    }

    public function test_admin_signal_validation_and_target(): void
    {
        Event::fake([StreamSignal::class]);
        $stream = $this->stream();
        $this->actingAs($this->admin(), 'admin');
        $url = route('admin.live-streams.signal', $stream);

        $this->postJson($url, ['type' => 'nope', 'payload' => ['a' => 1]])->assertStatus(422);
        $this->postJson($url, ['type' => 'offer', 'payload' => []])->assertStatus(422);
        $this->postJson($url, ['type' => 'offer', 'payload' => ['sdp' => str_repeat('a', 70000)]])->assertStatus(422);

        $this->postJson($url, ['type' => 'offer', 'payload' => ['type' => 'offer', 'sdp' => 'x'], 'target' => 'viewer-abc123'])->assertOk();
        Event::assertDispatched(StreamSignal::class, fn ($e) => $e->type === 'offer' && $e->targetPeerId === 'viewer-abc123' && $e->fromPeerId === 'admin');

        $ended = $this->stream('ended');
        $this->postJson(route('admin.live-streams.signal', $ended), ['type' => 'hello'])->assertStatus(422);
    }
}
