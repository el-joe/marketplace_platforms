<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the generated config/portal_bands.php: full coverage of the seeded
 * blocks, unique pairs, valid keys, screenshot files and DOM-order sanity.
 * Pure file-level test: no framework boot, no DB.
 */
class PortalBandsConfigTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    private function bands(): array
    {
        return require dirname(__DIR__, 2).'/config/portal_bands.php';
    }

    /** @return list<string> "page|block" */
    private function seededPairs(): array
    {
        $pairs = [];
        foreach (require dirname(__DIR__, 2).'/database/seeders/data/portal_content.php' as $row) {
            $pairs[$row[0].'|'.$row[1]] = true;
        }

        return array_keys($pairs);
    }

    public function test_every_seeded_block_is_in_exactly_one_band(): void
    {
        $seen = [];
        foreach ($this->bands() as $page => $def) {
            foreach ($def['bands'] as $band) {
                foreach ($band['blocks'] as [$p, $b]) {
                    $seen["{$p}|{$b}"][] = "{$page}/{$band['key']}";
                }
            }
        }

        $this->assertSame([], array_values(array_diff($this->seededPairs(), array_keys($seen))), 'Seeded blocks missing from portal_bands.');
        $this->assertSame([], array_values(array_diff(array_keys($seen), $this->seededPairs())), 'Bands reference unknown blocks.');
        $dupes = array_filter($seen, fn ($where) => count($where) > 1);
        $this->assertSame([], array_keys($dupes), 'Blocks listed in more than one band.');
    }

    public function test_structure_keys_and_order(): void
    {
        foreach ($this->bands() as $page => $def) {
            foreach (['label_en', 'label_ar', 'route', 'scope', 'bands'] as $k) {
                $this->assertArrayHasKey($k, $def, "{$page} missing {$k}");
            }
            $this->assertNotEmpty($def['bands'], "{$page} has no bands");

            $keys = array_column($def['bands'], 'key');
            $this->assertSame(array_values(array_unique($keys)), $keys, "{$page} duplicate band keys");

            $prev = -1;
            foreach ($def['bands'] as $band) {
                $this->assertMatchesRegularExpression('/^\d{2}-[a-z0-9-]+$/', $band['key']);
                $n = (int) substr($band['key'], 0, 2);
                $this->assertGreaterThan($prev, $n, "{$page} bands out of order at {$band['key']}");
                $prev = $n;
                $this->assertNotSame('', trim($band['label_en']));
                $this->assertNotSame('', trim($band['label_ar']));
                $this->assertNotEmpty($band['blocks'], "{$page}/{$band['key']} has no blocks");
                if ($n === 0) {
                    $this->assertNull($band['image']);
                    $this->assertSame('00-page-settings', $band['key']);
                } else {
                    $this->assertSame("{$page}/{$band['key']}", $band['image']);
                }
            }
        }
    }

    public function test_band_images_exist_for_both_locales(): void
    {
        $dir = dirname(__DIR__, 2).'/public/images/portal-sections/';
        $total = 0;
        foreach ($this->bands() as $def) {
            foreach ($def['bands'] as $band) {
                if ($band['image'] === null) {
                    continue;
                }
                foreach (['en', 'ar'] as $locale) {
                    $file = $dir.$band['image'].".{$locale}.png";
                    $this->assertFileExists($file);
                    $total += filesize($file);
                }
            }
        }
        $this->assertLessThan(30 * 1024 * 1024, $total, 'Band images exceed the 30 MB budget.');
    }

    public function test_home_page_pulls_cross_key_blocks(): void
    {
        $pairs = [];
        foreach ($this->bands()['home']['bands'] as $band) {
            foreach ($band['blocks'] as $pair) {
                $pairs[] = implode('|', $pair);
            }
        }
        foreach (['how-it-works|why-sell', 'how-it-works|checklist', 'how-it-works|trader_banner'] as $pair) {
            $this->assertContains($pair, $pairs);
        }
    }
}
