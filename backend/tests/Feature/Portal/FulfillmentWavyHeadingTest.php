<?php

namespace Tests\Feature\Portal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client feedback #22: the animated "Built for Flexibility" heading on /fulfillment split Arabic into one element
 * per character, which breaks letter joining (تصميم showed as ت ص م ي م). Arabic must wave whole words; Latin text
 * keeps the letter-by-letter wave.
 */
class FulfillmentWavyHeadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_text_waves_whole_words_so_letters_stay_joined(): void
    {
        $view = $this->blade('<span x-data="{ play: true }"><x-portal.wavy-text text="تصميم مرن للشركاء" /></span>');

        $view->assertSee('data-wavy-mode="words"', false);
        $this->assertSame(3, substr_count((string) $view, 'data-wavy-segment'));
        $view->assertSeeInOrder(['>تصميم</span>', '>مرن</span>', '>للشركاء</span>'], false);
        $view->assertDontSee('>ت</span>', false);
    }

    public function test_latin_text_keeps_the_letter_wave(): void
    {
        $view = $this->blade('<span x-data="{ play: true }"><x-portal.wavy-text text="Built for Flexibility" /></span>');

        $view->assertSee('data-wavy-mode="letters"', false);
        $this->assertSame(strlen('BuiltforFlexibility'), substr_count((string) $view, 'data-wavy-segment'));
        // Screen readers get the phrase once.
        $view->assertSee('<span class="sr-only">Built for Flexibility</span>', false);
    }

    public function test_fulfillment_page_renders_the_partner_heading_by_word_in_arabic_and_by_letter_in_english(): void
    {
        $this->withSession(['locale' => 'ar'])->get(route('portal.fulfillment'))
            ->assertOk()
            ->assertSee('data-wavy-mode="words"', false)
            ->assertDontSee('data-wavy-mode="letters"', false)
            ->assertDontSee("text.split('')", false)
            ->assertSee('prefers-reduced-motion', false);

        $this->withSession(['locale' => 'en'])->get(route('portal.fulfillment'))
            ->assertOk()
            ->assertSee('data-wavy-mode="letters"', false);
    }
}
