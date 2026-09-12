<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('currencies')->where('code', 'OMR')->update(['symbol' => 'ر.ع.']);
    }

    public function down(): void
    {
        DB::table('currencies')->where('code', 'OMR')->update(['symbol' => 'RO']);
    }
};
