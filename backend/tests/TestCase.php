<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Blade layouts call @vite(); CI/cloud containers have no `npm run build`
        // manifest. Stubbing the directive only removes the asset-manifest lookup;
        // routing, controllers, queries and view logic still execute for real.
        $this->withoutVite();
    }

    /**
     * Skip RefreshDatabase's per-process `migrate:fresh` under `--parallel` when
     * this worker's database (marketplace_test_test_N, kept between runs) already
     * has every migration on disk applied. Rebuilding ~400 tables in every worker
     * on every run is pure DDL/fsync cost on MySQL and made the parallel run
     * slower than the serial one. Each test still runs inside RefreshDatabase's
     * rolled-back transaction, so isolation is unchanged. Any pending migration
     * (or `--recreate-databases`, or a brand-new worker database) falls through
     * to the normal `migrate:fresh`.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $this->reuseParallelWorkerSchemaWhenCurrent();

        return parent::setUpTraits();
    }

    private function reuseParallelWorkerSchemaWhenCurrent(): void
    {
        if (RefreshDatabaseState::$migrated || ! ParallelTesting::token()) {
            return;
        }

        if (! isset(class_uses_recursive(static::class)[RefreshDatabase::class])) {
            return;
        }

        $migrator = $this->app->make('migrator');

        if (! $migrator->repositoryExists()) {
            return;
        }

        $onDisk = array_keys($migrator->getMigrationFiles(
            array_merge($migrator->paths(), [database_path('migrations')])
        ));

        // The ran list is a superset of the files on disk: the stored schema dump
        // (database/schema/mysql-schema.sql) records migrations that were squashed.
        if (array_diff($onDisk, $migrator->getRepository()->getRan()) === []) {
            RefreshDatabaseState::$migrated = true;
        }
    }
}
