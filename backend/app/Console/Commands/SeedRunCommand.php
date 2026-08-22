<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedRunCommand extends Command
{
    protected $signature = 'seed:run {--permissions-only : Seed only roles and permissions}';
    protected $description = 'Run the database seeders, optionally limited to roles/permissions only';

    public function handle(): int
    {
        $seeder = new DatabaseSeeder();
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);

        $seeder->run($this->option('permissions-only'));

        return self::SUCCESS;
    }
}
// pa seed:run --permissions-only
