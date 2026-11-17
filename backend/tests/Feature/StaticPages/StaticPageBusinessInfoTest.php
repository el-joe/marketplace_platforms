<?php

namespace Tests\Feature\StaticPages;

use App\Models\Country;
use App\Models\Setting;
use App\Models\StaticPage;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StaticPageBusinessInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Country::factory()->create(['site_code' => 'omn', 'iso_code_2' => 'OM', 'is_active' => true]);
    }

    private function setBusiness(string $key, string $value): void
    {
        Setting::where('key', $key)->firstOrFail()->update(['value' => $value]);
        Cache::forget('setting:'.$key);
    }

    public function test_business_placeholders_are_filled_escaped_and_per_language(): void
    {
        $this->setBusiness('support_email', 'help@nawyweb.com');
        $this->setBusiness('support_phone', '+968 9123 4567');
        $this->setBusiness('business_legal_name', 'Nawy <Trading> LLC');
        $this->setBusiness('business_address_en', 'Muscat, Oman');
        $this->setBusiness('business_address_ar', 'مسقط، عُمان');

        StaticPage::factory()->storefront()->create([
            'slug' => 'contact-us',
            // As stored after the admin editor's sanitizer, which URL-encodes the braces inside href.
            'content_en' => HtmlSanitizer::clean('<p><a href="mailto:{{business_support_email}}">{{ business_support_email }}</a> <a href="tel:{{business_support_phone_tel}}">call</a></p><p>{{business_legal_name}}, {{business_address}}</p>'),
            'content_ar' => '<p>{{business_address}}</p>',
        ]);

        $content = $this->getJson('/api/customer/v1/omn/static-pages/contact-us')->assertOk()->json('data.content');

        $this->assertStringContainsString('<a href="mailto:help@nawyweb.com">help@nawyweb.com</a>', $content['en']);
        $this->assertStringContainsString('href="tel:+96891234567"', $content['en']);
        $this->assertStringContainsString('Nawy &lt;Trading&gt; LLC, Muscat, Oman', $content['en']);
        $this->assertStringContainsString('مسقط، عُمان', $content['ar']);
    }

    public function test_lines_using_an_unset_business_detail_are_left_out(): void
    {
        $this->setBusiness('support_email', 'help@nawyweb.com');
        $this->setBusiness('business_cr_number', '');

        StaticPage::factory()->storefront()->create([
            'slug' => 'contact-us',
            'content_en' => '<ul><li>Email: {{business_support_email}}</li><li>CR No.: {{business_cr_number}}</li></ul>',
        ]);

        $html = $this->getJson('/api/customer/v1/omn/static-pages/contact-us')->assertOk()->json('data.content.en');

        $this->assertSame('<ul><li>Email: help@nawyweb.com</li></ul>', $html);
    }
}
