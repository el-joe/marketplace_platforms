<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('commission_discount_type', ['none', 'flat', 'percentage'])
                ->default('none')
                ->after('commission_rate')
                ->comment('Admin-granted discount off this vendor\'s platform commission.');

            $table->bigInteger('commission_discount_flat')
                ->default(0)
                ->after('commission_discount_type')
                ->comment('Flat discount in platform base currency units. Only used when type=flat.');

            $table->decimal('commission_discount_percentage', 5, 2)
                ->default(0)
                ->after('commission_discount_flat')
                ->comment('Percentage discount off computed commission. Only used when type=percentage.');

            $table->text('commission_discount_notes')
                ->nullable()
                ->after('commission_discount_percentage')
                ->comment('Admin notes explaining why this discount was granted.');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'commission_discount_type',
                'commission_discount_flat',
                'commission_discount_percentage',
                'commission_discount_notes',
            ]);
        });
    }
};
