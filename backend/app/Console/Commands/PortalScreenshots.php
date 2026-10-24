<?php

namespace App\Console\Commands;

use App\Models\AdSupportArticle;
use App\Models\AdSupportCollection;
use App\Models\BlogPost;
use App\Models\Country;
use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Optional helper: regenerates the section screenshots shown in the admin
 * "Portal content" editor. Thin wrapper around scripts/portal-screenshots.mjs
 * (Playwright + Chromium). See docs/PORTAL_SCREENSHOTS.md.
 */
class PortalScreenshots extends Command
{
    protected $signature = 'portal:screenshots
        {--base=http://portal.localhost:8000 : Base URL of a locally served portal (php artisan serve)}
        {--country= : Country site_code to render (default: first active country)}
        {--page= : Only this page_key from config/portal_sections.php}';

    protected $description = 'Capture portal section screenshots (local render) into public/images/portal-sections';

    public function handle(): int
    {
        $country = $this->option('country')
            ?: Country::where('is_active', true)->orderByDesc('is_launched')->orderBy('name_en')->value('site_code');

        if (! $country) {
            $this->error('No active country found.');

            return self::FAILURE;
        }

        $config = [
            'base' => $this->option('base'),
            'country' => $country,
            'params' => [
                'collection' => AdSupportCollection::where('is_active', true)->value('slug'),
                'article' => AdSupportArticle::published()->value('slug'),
                'category' => HelpCenterCategory::where('is_active', true)->value('slug'),
                'slug' => BlogPost::published()->value('slug'),
                'help_article' => HelpCenterArticle::published()->value('slug'),
            ],
            'sections' => config('portal_sections'),
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'portal-shots-');
        file_put_contents($tmp, json_encode($config, JSON_UNESCAPED_UNICODE));

        $cmd = ['node', base_path('scripts/portal-screenshots.mjs'), '--config='.$tmp];
        if ($this->option('page')) {
            $cmd[] = '--page='.$this->option('page');
        }

        $process = new Process($cmd, base_path(), null, null, 1800);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));
        @unlink($tmp);

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
