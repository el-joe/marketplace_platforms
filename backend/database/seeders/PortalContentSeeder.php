<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SyncsPortalContent;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Portal Content" CMS — admin-editable bilingual text, links and
 * images rendered on resources/views/portal/** and the portal layouts via the
 * portal_content(), portal_link() and portal_image() helpers
 * (see app/Helpers/portal_content.php).
 *
 * The canonical rows live in database/seeders/data/portal_content.php as
 *   [page_key, block_key, field_key, type, value_en, value_ar, value_url, sort_order]
 *
 *   page_key   groups all rows for one logical page/partial family, e.g.
 *              'home', 'faq', 'nav', 'layout', 'helpcenter'.
 *   block_key  one visual block/component within that page, e.g. 'hero'.
 *   field_key  a single field within that block, e.g. 'title', 'cta_button'.
 *   type       'text', 'richtext', 'link' (value_en/value_ar = label,
 *              value_url = href) or 'image' (value_en/value_ar = alt text,
 *              value_url = src: absolute URL, "/site/path" or public-disk path).
 *
 * To make another piece of portal copy editable:
 *   1. Wrap it in Blade with the matching helper, passing today's copy as the
 *      fallback, e.g. portal_content('home', 'hero', 'title', 'EN', 'AR').
 *   2. Add the identical values as a row in data/portal_content.php.
 *   3. Run this seeder — it is non-destructive (SyncsPortalContent), so it
 *      never overwrites values an admin has edited.
 */
class PortalContentSeeder extends Seeder
{
    use SyncsPortalContent;

    public function run(): void
    {
        $stats = $this->syncPortalContent(require __DIR__.'/data/portal_content.php');

        $this->command?->info(sprintf(
            'Portal content: %d created, %d refreshed, %d admin-edited rows preserved.',
            $stats['created'],
            $stats['refreshed'],
            $stats['preserved'],
        ));
    }
}
