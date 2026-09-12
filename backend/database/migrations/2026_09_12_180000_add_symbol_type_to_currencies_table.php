<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->enum('symbol_type', ['text', 'image'])
                ->default('text')
                ->after('symbol')
                ->comment('text = render the symbol column as text; image = render symbol_image via an <img>/SVG.');

            $table->string('symbol_image', 500)
                ->nullable()
                ->after('symbol_type')
                ->comment('Storage path (public disk) for the symbol image/SVG. Only used when symbol_type=image.');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['symbol_type', 'symbol_image']);
        });
    }
};
