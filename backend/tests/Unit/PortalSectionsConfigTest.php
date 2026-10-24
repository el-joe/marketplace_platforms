<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards config/portal_sections.php against drifting from the canonical
 * portal content defaults. Pure file-level test: no framework boot, no DB.
 */
class PortalSectionsConfigTest extends TestCase
{
    private function config(): array
    {
        return require dirname(__DIR__, 2).'/config/portal_sections.php';
    }

    /** @return array<string, list<string>> page_key => distinct block_keys (seeder order) */
    private function seededBlocks(): array
    {
        $rows = require dirname(__DIR__, 2).'/database/seeders/data/portal_content.php';
        $blocks = [];
        foreach ($rows as $row) {
            $blocks[$row[0]][$row[1]] = true;
        }

        return array_map('array_keys', $blocks);
    }

    public function test_every_seeded_page_and_block_is_mapped(): void
    {
        $config = $this->config();

        foreach ($this->seededBlocks() as $page => $blocks) {
            $this->assertArrayHasKey($page, $config, "Page '{$page}' is missing from portal_sections.");
            $mapped = array_column($config[$page]['sections'], 'block');
            $this->assertSame([], array_values(array_diff($blocks, $mapped)), "Page '{$page}' has unmapped blocks.");
        }
    }

    public function test_no_unknown_pages_or_blocks_and_no_duplicates(): void
    {
        $config = $this->config();
        $seeded = $this->seededBlocks();

        foreach ($config as $page => $def) {
            $this->assertArrayHasKey($page, $seeded, "Config page '{$page}' is not in the seeder data.");
            $mapped = array_column($def['sections'], 'block');
            $this->assertSame([], array_values(array_diff($mapped, $seeded[$page])), "Page '{$page}' maps unknown blocks.");
            $this->assertSame(
                [],
                array_keys(array_filter(array_count_values($mapped), fn ($n) => $n > 1)),
                "Page '{$page}' lists a block more than once."
            );
        }
    }

    public function test_page_and_section_structure(): void
    {
        foreach ($this->config() as $page => $def) {
            foreach (['label_en', 'label_ar', 'route', 'scope'] as $key) {
                $this->assertNotEmpty($def[$key] ?? null, "Page '{$page}' needs '{$key}'.");
            }
            $this->assertContains($def['scope'], ['page', 'shared'], "Page '{$page}' has an invalid scope.");
            $this->assertStringStartsWith('/', $def['route']);
            $this->assertNotEmpty($def['sections'], "Page '{$page}' has no sections.");

            foreach ($def['sections'] as $section) {
                $this->assertNotEmpty($section['block'] ?? null);
                $this->assertNotEmpty($section['label_en'] ?? null, "{$page}/{$section['block']} needs label_en.");
                $this->assertNotEmpty($section['label_ar'] ?? null, "{$page}/{$section['block']} needs label_ar.");
                $this->assertArrayHasKey('selector', $section, "{$page}/{$section['block']} needs a selector (or null).");
                $this->assertTrue(
                    $section['selector'] === null || (is_string($section['selector']) && $section['selector'] !== ''),
                    "{$page}/{$section['block']} has an invalid selector."
                );
                if (isset($section['route'])) {
                    $this->assertStringStartsWith('/', $section['route']);
                }
            }
        }
    }

    public function test_data_attribute_selectors_exist_in_blade_views(): void
    {
        $views = '';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views'));
        foreach ($it as $file) {
            if (str_ends_with((string) $file, '.blade.php')) {
                $views .= file_get_contents((string) $file);
            }
        }

        foreach ($this->config() as $page => $def) {
            foreach ($def['sections'] as $section) {
                if (is_string($section['selector']) && preg_match('/\[data-portal-section="([^"]+)"\]/', $section['selector'], $m)) {
                    $this->assertStringContainsString(
                        'data-portal-section="'.$m[1].'"',
                        $views,
                        "{$page}/{$section['block']} references data-portal-section=\"{$m[1]}\" which no Blade view declares."
                    );
                }
            }
        }
    }

    public function test_every_editable_block_used_in_blade_views_is_mapped(): void
    {
        $config = $this->config();
        $unmapped = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views'));
        foreach ($it as $file) {
            if (! str_ends_with((string) $file, '.blade.php')) {
                continue;
            }

            preg_match_all("/portal_(?:content|link|image)\(\s*'([^']+)'\s*,\s*'([^']+)'/", file_get_contents((string) $file), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $page, $block]) {
                // Blocks built dynamically in Blade ('section_'.$section, 'card_'.$type) end with "_".
                if (str_ends_with($block, '_')) {
                    continue;
                }
                $mapped = array_column($config[$page]['sections'] ?? [], 'block');
                if (! in_array($block, $mapped, true)) {
                    $unmapped[] = "{$page}/{$block}";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($unmapped)), 'Blade views read portal content blocks missing from portal_sections.');
    }

    public function test_marketer_type_blocks_cover_every_marketer_type(): void
    {
        $blocks = array_column($this->config()['marketer-type']['sections'], 'block');

        $this->assertSame(['influencer', 'affiliate', 'broker'], $blocks);
    }
}
