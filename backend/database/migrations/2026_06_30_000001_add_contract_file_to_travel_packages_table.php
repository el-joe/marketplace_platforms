<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->string('contract_file_path')->nullable()->after('rejection_reason');
            $table->string('contract_file_original_name')->nullable()->after('contract_file_path');
            $table->timestamp('contract_uploaded_at')->nullable()->after('contract_file_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn(['contract_file_path', 'contract_file_original_name', 'contract_uploaded_at']);
        });
    }
};
