<?php

namespace Tests\Feature\Admin;

use App\Enums\LiveStreamStatus;
use App\Models\Admin;
use App\Models\LiveStream;
use App\Services\LiveStream\RecordedStreamScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RecordedStreamTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'pages.view', 'guard_name' => 'admin']);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('pages.view');

        return $admin;
    }

    private function channel(array $attrs = []): LiveStream
    {
        return LiveStream::create($attrs + [
            'title_en' => 'Radio', 'title_ar' => 'راديو', 'source' => 'recorded', 'status' => 'scheduled',
            'media_path' => 'live-streams/media/a.mp3', 'media_type' => 'audio', 'duration_seconds' => 600,
            'scheduled_at' => now()->addHour(), 'scheduled_end_at' => now()->addHour()->addSeconds(600),
        ]);
    }

    public function test_admin_schedules_a_radio_channel_and_end_time_is_computed_from_the_duration(): void
    {
        Storage::fake('public');
        $start = now()->addDay()->startOfMinute();

        $this->actingAs($this->admin(), 'admin')->post(route('admin.live-streams.store'), [
            'source' => 'recorded', 'title_en' => 'R', 'title_ar' => 'ر',
            'scheduled_at' => $start->format('Y-m-d\TH:i'),
            'media' => UploadedFile::fake()->create('show.mp3', 100, 'audio/mpeg'),
            'duration_seconds' => 125,
        ])->assertRedirect(route('admin.live-streams.index'));

        $stream = LiveStream::firstOrFail();
        $this->assertTrue($stream->isRecorded());
        $this->assertSame('audio', $stream->media_type);
        $this->assertSame(125, $stream->duration_seconds);
        $this->assertSame($start->copy()->addSeconds(125)->timestamp, $stream->scheduled_end_at->timestamp);
        Storage::disk('public')->assertExists($stream->media_path);
    }

    public function test_radio_channel_requires_a_file_and_a_schedule(): void
    {
        $this->actingAs($this->admin(), 'admin')->post(route('admin.live-streams.store'), [
            'source' => 'recorded', 'title_en' => 'R', 'title_ar' => 'ر',
        ])->assertSessionHasErrors(['media', 'scheduled_at']);
    }

    public function test_scheduler_goes_live_then_ends_by_itself(): void
    {
        $due = $this->channel(['scheduled_at' => now()->subMinutes(2), 'scheduled_end_at' => now()->addMinutes(8)]);
        $over = $this->channel(['scheduled_at' => now()->subMinutes(20), 'scheduled_end_at' => now()->subMinutes(10)]);
        $later = $this->channel();

        $result = app(RecordedStreamScheduler::class)->sync();

        $this->assertSame(['started' => 1, 'ended' => 1], $result);
        $this->assertSame(LiveStreamStatus::Live, $due->fresh()->status);
        $this->assertSame(LiveStreamStatus::Ended, $over->fresh()->status);
        $this->assertSame(LiveStreamStatus::Scheduled, $later->fresh()->status);

        $this->travel(9)->minutes();
        app(RecordedStreamScheduler::class)->sync();
        $this->assertSame(LiveStreamStatus::Ended, $due->fresh()->status);
    }

    public function test_public_api_exposes_playback_with_offset_only_after_it_starts(): void
    {
        $live = $this->channel(['scheduled_at' => now()->subMinutes(2), 'scheduled_end_at' => now()->addMinutes(8)]);
        $scheduled = $this->channel();

        $data = $this->getJson(route('public.streams.show', $live))->assertOk()->json('data');
        $this->assertSame('live', $data['status']);
        $this->assertSame('recorded', $data['source']);
        $this->assertSame('audio', $data['playback']['type']);
        $this->assertEqualsWithDelta(120, $data['playback']['offset_seconds'], 3);
        $this->assertStringEndsWith('live-streams/media/a.mp3', $data['playback']['url']);

        $this->assertNull($this->getJson(route('public.streams.show', $scheduled))->json('data.playback'));

        $list = collect($this->getJson(route('public.streams.index'))->json('data'));
        $this->assertSame('recorded', $list->firstWhere('id', $live->id)['source']);
    }

    public function test_go_live_is_rejected_for_radio_channels_and_ended_ones_keep_a_replay(): void
    {
        $admin = $this->admin();
        $channel = $this->channel();
        $this->actingAs($admin, 'admin')->postJson(route('admin.live-streams.go-live', $channel))->assertStatus(422);

        $ended = $this->channel(['status' => 'ended', 'scheduled_at' => now()->subHour(), 'scheduled_end_at' => now()->subMinutes(50)]);
        $replay = $this->getJson(route('public.streams.show', $ended))->json('data.playback');
        $this->assertNotNull($replay['url']);
        $this->assertSame(0, $replay['offset_seconds']);
    }

    public function test_admin_pages_render_for_radio_channels(): void
    {
        $admin = $this->admin();
        $channel = $this->channel();

        $this->actingAs($admin, 'admin')->get(route('admin.live-streams.create'))->assertOk()->assertSee('media-file', false);
        $this->get(route('admin.live-streams.edit', $channel))->assertOk()->assertSee('a.mp3');
        $this->get(route('admin.live-streams.show', $channel))->assertOk()->assertSee('btn-end-channel', false);
        $this->get(route('admin.live-streams.index'))->assertOk()->assertSee('📻');
    }
}
