<?php

namespace Tests\Feature\Cart;

use App\Enums\BannerDeviceTarget;
use App\Models\Banner;
use App\Models\Country;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The cart API's `cart_banner` (Cart Page Banner placement): device targeting, image choice by
 * locale × device, and per-device winners when separate desktop/mobile banners exist.
 */
class CartBannerTargetingTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::factory()->create([
            'site_code' => 'om-'.Str::lower(Str::random(6)),
            'is_launched' => true,
        ]);
    }

    /**
     * @param  list<string>  $slots  e.g. ['desktop_en', 'mobile_ar']
     */
    private function cartBanner(BannerDeviceTarget $target, array $slots, array $attributes = []): Banner
    {
        $banner = Banner::factory()->placement('cart_banner')->create([
            'device_target' => $target,
            'country_id' => $this->country->id,
            ...$attributes,
        ]);

        foreach ($slots as $slot) {
            File::create([
                'key' => "banners/{$banner->id}/{$slot}.png",
                'path' => "banners/{$banner->id}/{$slot}.png",
                'storage_type' => 'public',
                'file_type' => 'banner_'.$slot,
                'mime_type' => 'image/png',
                'extension' => 'png',
                'size' => 10,
                'model_type' => Banner::class,
                'model_id' => $banner->id,
            ]);
        }

        return $banner;
    }

    /** @return array<string, mixed>|null */
    private function fetchCartBanner(): ?array
    {
        return $this->getJson("/api/customer/v1/{$this->country->site_code}/cart")
            ->assertOk()
            ->json('data.cart_banner');
    }

    private function assertImage(?string $expectedSlot, ?string $url, string $field): void
    {
        if ($expectedSlot === null) {
            $this->assertNull($url, "{$field} should be empty");

            return;
        }

        $this->assertNotNull($url, "{$field} should be set");
        $this->assertStringEndsWith("/{$expectedSlot}.png", $url, $field);
    }

    public function test_mobile_only_banner_is_never_offered_to_desktop_and_needs_no_desktop_image(): void
    {
        $this->cartBanner(BannerDeviceTarget::Mobile, ['mobile_en', 'mobile_ar']);

        $banner = $this->fetchCartBanner();

        $this->assertSame('mobile', $banner['device_target']);
        $this->assertNull($banner['desktop_image_url']);
        $this->assertNull($banner['desktop_image_url_ar']);
        $this->assertImage('mobile_en', $banner['mobile_image_url'], 'mobile_image_url');
        $this->assertImage('mobile_ar', $banner['mobile_image_url_ar'], 'mobile_image_url_ar');
    }

    public function test_desktop_only_banner_is_never_offered_to_mobile_even_with_mobile_images(): void
    {
        $this->cartBanner(BannerDeviceTarget::Desktop, ['desktop_en', 'desktop_ar', 'mobile_en', 'mobile_ar']);

        $banner = $this->fetchCartBanner();

        $this->assertNull($banner['mobile_image_url']);
        $this->assertNull($banner['mobile_image_url_ar']);
        $this->assertImage('desktop_en', $banner['desktop_image_url'], 'desktop_image_url');
        $this->assertImage('desktop_ar', $banner['desktop_image_url_ar'], 'desktop_image_url_ar');
    }

    public function test_app_only_banner_is_not_served_to_the_web(): void
    {
        $this->cartBanner(BannerDeviceTarget::App, ['desktop_en', 'mobile_en']);

        $this->assertNull($this->fetchCartBanner());
    }

    public function test_separate_desktop_and_mobile_banners_each_win_their_own_device(): void
    {
        $desktop = $this->cartBanner(BannerDeviceTarget::Desktop, ['desktop_en'], ['priority' => 10, 'cta_url' => '/desktop-deals']);
        $mobile = $this->cartBanner(BannerDeviceTarget::Mobile, ['mobile_en'], ['priority' => 1, 'cta_url' => '/mobile-deals']);

        $banner = $this->fetchCartBanner();

        $this->assertSame($desktop->id, $banner['id']);
        $this->assertSame('/desktop-deals', $banner['cta_url']);
        $this->assertNull($banner['mobile_image_url']);
        $this->assertSame($mobile->id, $banner['mobile_variant']['id']);
        $this->assertSame('/mobile-deals', $banner['mobile_variant']['cta_url']);
        $this->assertNull($banner['mobile_variant']['desktop_image_url']);
        $this->assertImage('mobile_en', $banner['mobile_variant']['mobile_image_url'], 'mobile_variant.mobile_image_url');
    }

    public function test_banner_without_any_image_does_not_block_a_lower_priority_banner(): void
    {
        $this->cartBanner(BannerDeviceTarget::All, [], ['priority' => 10]);
        $withImage = $this->cartBanner(BannerDeviceTarget::All, ['desktop_en'], ['priority' => 1]);

        $this->assertSame($withImage->id, $this->fetchCartBanner()['id']);
    }

    /**
     * Fallback for device D / locale L: exact → same device other locale → other device same locale
     * (only when the banner targets D) → other device other locale.
     *
     * @return array<string, array{BannerDeviceTarget, list<string>, array<string, ?string>}>
     */
    public static function imageChoices(): array
    {
        return [
            'all slots: each locale × device gets its own image' => [
                BannerDeviceTarget::All,
                ['desktop_en', 'desktop_ar', 'mobile_en', 'mobile_ar'],
                ['desktop_image_url' => 'desktop_en', 'desktop_image_url_ar' => 'desktop_ar', 'mobile_image_url' => 'mobile_en', 'mobile_image_url_ar' => 'mobile_ar'],
            ],
            'missing AR uses same-device EN' => [
                BannerDeviceTarget::All,
                ['desktop_en', 'mobile_en'],
                ['desktop_image_url' => 'desktop_en', 'desktop_image_url_ar' => 'desktop_en', 'mobile_image_url' => 'mobile_en', 'mobile_image_url_ar' => 'mobile_en'],
            ],
            'missing EN uses same-device AR' => [
                BannerDeviceTarget::All,
                ['desktop_ar', 'mobile_ar'],
                ['desktop_image_url' => 'desktop_ar', 'desktop_image_url_ar' => 'desktop_ar', 'mobile_image_url' => 'mobile_ar', 'mobile_image_url_ar' => 'mobile_ar'],
            ],
            'all devices with only mobile images: desktop borrows the same-locale mobile image' => [
                BannerDeviceTarget::All,
                ['mobile_en', 'mobile_ar'],
                ['desktop_image_url' => 'mobile_en', 'desktop_image_url_ar' => 'mobile_ar', 'mobile_image_url' => 'mobile_en', 'mobile_image_url_ar' => 'mobile_ar'],
            ],
            'same device other locale beats other device same locale' => [
                BannerDeviceTarget::All,
                ['desktop_en', 'mobile_ar'],
                ['desktop_image_url' => 'desktop_en', 'desktop_image_url_ar' => 'desktop_en', 'mobile_image_url' => 'mobile_ar', 'mobile_image_url_ar' => 'mobile_ar'],
            ],
            'mobile only with only a desktop image falls back to it on mobile' => [
                BannerDeviceTarget::Mobile,
                ['desktop_ar'],
                ['desktop_image_url' => null, 'desktop_image_url_ar' => null, 'mobile_image_url' => 'desktop_ar', 'mobile_image_url_ar' => 'desktop_ar'],
            ],
        ];
    }

    /**
     * @param  list<string>  $slots
     * @param  array<string, ?string>  $expected
     */
    #[DataProvider('imageChoices')]
    public function test_image_is_chosen_by_locale_and_device(BannerDeviceTarget $target, array $slots, array $expected): void
    {
        $this->cartBanner($target, $slots);

        $banner = $this->fetchCartBanner();

        foreach ($expected as $field => $slot) {
            $this->assertImage($slot, $banner[$field], $field);
        }
    }
}
