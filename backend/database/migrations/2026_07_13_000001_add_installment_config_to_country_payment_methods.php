<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('country_payment_methods', function (Blueprint $table) {
            $table->unsignedTinyInteger('installments_count')->default(4)->after('sort_order')
                ->comment('Number of installments shown on product detail page for BNPL methods. Ignored for non-BNPL methods.');
            $table->string('installment_label_en', 200)->nullable()->after('installments_count')
                ->comment('Template: "pay in {n} interest-free installments of {amount}". {n} and {amount} are replaced at render time. NULL = use default template.');
            $table->string('installment_label_ar', 200)->nullable()->after('installment_label_en');
            $table->string('provider_logo_path', 255)->nullable()->after('installment_label_ar')
                ->comment('Path to provider logo SVG/PNG, e.g. /assets/payment-logos/tabby.svg — used on product detail page payment options section');
            $table->string('learn_more_url', 500)->nullable()->after('provider_logo_path')
                ->comment('Link for "Learn more" CTA next to this payment option on the product detail page');
        });
    }

    public function down(): void
    {
        Schema::table('country_payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'installments_count',
                'installment_label_en',
                'installment_label_ar',
                'provider_logo_path',
                'learn_more_url',
            ]);
        });
    }
};
