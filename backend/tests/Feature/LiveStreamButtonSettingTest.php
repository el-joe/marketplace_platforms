<?php

namespace Tests\Feature;

use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Settings → General toggle that shows/hides the storefront's floating Live Streams button.
 */
class LiveStreamButtonSettingTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    public function test_toggle_is_public_and_turning_it_off_is_reflected_immediately(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->getJson(route('public.settings'))->assertOk()->assertJsonPath('data.live_stream_button_enabled', true);

        $this->actingAs($this->adminWith(['settings.view', 'settings.edit']), 'admin')
            ->postJson(route('admin.settings.save', 'general'), ['settings' => ['live_stream_button_enabled' => '0']])
            ->assertOk();

        $this->getJson(route('public.settings'))->assertOk()->assertJsonPath('data.live_stream_button_enabled', false);
    }
}
