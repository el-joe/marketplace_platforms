<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_job_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('marketer_job_categories', 'category_id')) {
                $table->uuid('category_id')->nullable()->after('category_type');
            }
        });

        $indexes = collect(Schema::getIndexes('marketer_job_categories'));

        if (! $indexes->contains('name', 'marketer_job_categories_marketer_job_id_index')) {
            Schema::table('marketer_job_categories', function (Blueprint $table) {
                $table->index('marketer_job_id');
            });
        }

        if (! $indexes->contains('name', 'marketer_job_categories_category_id_index')) {
            Schema::table('marketer_job_categories', function (Blueprint $table) {
                $table->index('category_id');
            });
        }

        if ($indexes->contains('name', 'marketer_job_categories_marketer_job_id_category_type_unique')) {
            Schema::table('marketer_job_categories', function (Blueprint $table) {
                $table->dropUnique('marketer_job_categories_marketer_job_id_category_type_unique');
            });
        }

        $indexes = collect(Schema::getIndexes('marketer_job_categories'));

        if (! $indexes->contains('name', 'marketer_job_categories_job_type_category_unique')) {
            Schema::table('marketer_job_categories', function (Blueprint $table) {
                // NULL category_id = job supports ALL categories of this type.
                // A row per specific category_id = job restricted to that whitelist.
                $table->unique(['marketer_job_id', 'category_type', 'category_id'], 'marketer_job_categories_job_type_category_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('marketer_job_categories', function (Blueprint $table) {
            $table->dropUnique('marketer_job_categories_job_type_category_unique');
            $table->dropIndex(['category_id']);
            $table->dropIndex(['marketer_job_id']);
            $table->dropColumn('category_id');
            $table->unique(['marketer_job_id', 'category_type']);
        });
    }
};
