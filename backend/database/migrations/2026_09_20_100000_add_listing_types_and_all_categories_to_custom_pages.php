<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_pages', function (Blueprint $t) {
            $t->json('listing_types')->nullable()->after('has_filters')
                ->comment('Allowed listing sources (admin|vendor|marketer). NULL = all.');
            $t->boolean('all_categories')->default(false)->after('listing_types')
                ->comment('When true, no category restriction; linked categories are ignored.');
        });
    }

    public function down(): void
    {
        Schema::table('custom_pages', function (Blueprint $t) {
            $t->dropColumn(['listing_types', 'all_categories']);
        });
    }
};
