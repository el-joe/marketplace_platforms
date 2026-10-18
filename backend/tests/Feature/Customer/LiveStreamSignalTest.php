<?php

namespace Tests\Feature\Customer;

use App\Events\StreamSignal;
use App\Models\LiveStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LiveStreamSignalTest extends TestCase
{
    use RefreshDatabase;

    private function stream(string $status = 'live'): LiveStream
    {
        return LiveStream::create(['title_en' => 'T', 'title_ar' => 'ت', 'status' => $status]);
    }

    private function url(LiveStream $s): string
    {
        return route('public.streams.signal', $s);
    }

    public function test_thumbnail_urls_are_absolute_in_list_and_detail(): void
    {
        $s = LiveStream::create(['title_en' => 'T', 'title_ar' => 'ت', 'status' => 'ended', 'thumbnail_path' => 'live-streams/thumbnails/a.jpg']);

        $detail = $this->getJson(route('public.streams.show', $s))->assertOk()->json('data.thumbnail_url');
        $list = $this->getJson(route('public.streams.index'))->assertOk()->json('data.0.thumbnail_url');

        foreach ([$detail, $list] as $url) {
            $this->assertMatchesRegularExpression('#^https?://[^/]+/storage/live-streams/thumbnails/a\.jpg$#', $url);
        }
    }

    public function test_viewer_signal_forces_target_admin_and_uses_peer_id_as_from(): void
    {
        Event::fake([StreamSignal::class]);
        $s = $this->stream();

        $this->postJson($this->url($s), [
            'type' => 'offer', 'payload' => ['type' => 'offer', 'sdp' => 'v=0'], 'peer_id' => 'viewer-ab12', 'target' => 'viewer-zzzzzz',
        ])->assertOk();

        Event::assertDispatched(StreamSignal::class, fn ($e) => $e->targetPeerId === 'admin'
            && $e->fromPeerId === 'viewer-ab12' && $e->streamKey === $s->stream_key);
    }

    public function test_hello_and_bye_accept_empty_payload(): void
    {
        Event::fake([StreamSignal::class]);
        $s = $this->stream();
        foreach (['hello', 'bye'] as $type) {
            $this->postJson($this->url($s), ['type' => $type, 'peer_id' => 'viewer-ab12'])->assertOk();
        }
        Event::assertDispatchedTimes(StreamSignal::class, 2);
    }

    public function test_validation_rules(): void
    {
        Event::fake([StreamSignal::class]);
        $s = $this->stream();
        $ok = ['type' => 'ice-candidate', 'payload' => ['candidate' => 'x'], 'peer_id' => 'viewer-ab12'];

        $this->postJson($this->url($s), array_merge($ok, ['type' => 'ended']))->assertStatus(422);
        $this->postJson($this->url($s), array_merge($ok, ['type' => 'media-state']))->assertStatus(422);
        $this->postJson($this->url($s), array_merge($ok, ['peer_id' => 'ab']))->assertStatus(422);
        $this->postJson($this->url($s), array_merge($ok, ['peer_id' => 'bad id!!']))->assertStatus(422);
        $this->postJson($this->url($s), array_merge($ok, ['payload' => ['sdp' => str_repeat('a', 70000)]]))->assertStatus(422);
        $this->postJson($this->url($s), array_merge($ok, ['payload' => []]))->assertStatus(422);
        Event::assertNothingDispatched();
    }

    public function test_not_live_returns_422(): void
    {
        Event::fake([StreamSignal::class]);
        $this->postJson($this->url($this->stream('ended')), ['type' => 'hello', 'peer_id' => 'viewer-ab12'])->assertStatus(422);
        $this->postJson($this->url($this->stream('scheduled')), ['type' => 'hello', 'peer_id' => 'viewer-ab12'])->assertStatus(422);
        Event::assertNothingDispatched();
    }

    public function test_route_is_throttled_to_429(): void
    {
        Event::fake([StreamSignal::class]);
        $s = $this->stream();
        $last = null;
        for ($i = 0; $i < 241; $i++) {
            // vary peer so the per-peer limiter is not what trips
            $last = $this->postJson($this->url($s), ['type' => 'hello', 'peer_id' => 'viewer-'.str_pad((string) ($i % 50), 4, '0', STR_PAD_LEFT)]);
        }
        $last->assertStatus(429);
    }

    public function test_per_peer_limiter_returns_429(): void
    {
        Event::fake([StreamSignal::class]);
        $s = $this->stream();
        $key = 'stream-signal:'.$s->id.':viewer-ab12';
        for ($i = 0; $i < 300; $i++) {
            RateLimiter::hit($key, 60);
        }
        $this->postJson($this->url($s), ['type' => 'hello', 'peer_id' => 'viewer-ab12'])->assertStatus(429);
    }
}
