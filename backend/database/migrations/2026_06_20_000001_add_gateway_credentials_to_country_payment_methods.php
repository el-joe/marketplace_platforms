<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('country_payment_methods', function (Blueprint $table) {
            $table->string('gateway_code', 50)->nullable()->after('provider')
                ->comment('Strategy class key — e.g. thawani, paytabs, tabby, noon_pay, cod, bank_transfer');

            $table->char('settlement_currency', 3)->nullable()->after('fee_fixed_cents')
                ->comment('Currency this gateway actually settles in. NULL = use country currency_code as-is.');

            $table->text('credentials_encrypted')->nullable()->after('settlement_currency')
                ->comment('JSON encrypted via Crypt::encryptString(). Contains API keys — never plaintext.');

            $table->enum('environment', ['sandbox', 'production'])->default('sandbox')->after('credentials_encrypted');

            $table->text('webhook_secret_encrypted')->nullable()->after('environment');

            $table->timestamp('last_verified_at')->nullable()->after('webhook_secret_encrypted')
                ->comment('Last successful test-connection timestamp');

            $table->enum('last_verification_status', ['success', 'failed'])->nullable()->after('last_verified_at');

            $table->text('last_verification_message')->nullable()->after('last_verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('country_payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_code',
                'settlement_currency',
                'credentials_encrypted',
                'environment',
                'webhook_secret_encrypted',
                'last_verified_at',
                'last_verification_status',
                'last_verification_message',
            ]);
        });
    }
};
