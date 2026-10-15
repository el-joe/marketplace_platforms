<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R2): the influencer's fixed amount is PER CAMPAIGN. It is snapshotted on the invitation when the
 * campaign is created, reserved from the campaign's max_commission_budget, and paid (net of the commission fee)
 * on delivery.
 *
 * influencer_fixed_status: none (default, not an influencer slot) | reserved | paid | released (reserve returned
 * to the budget: rejected, cancelled or not fulfilled).
 */
return new class extends Migration
{
    /** @return array<string, callable(Blueprint): void> */
    private function columns(): array
    {
        return [
            'influencer_fixed_amount' => fn (Blueprint $t) => $t->decimal('influencer_fixed_amount', 19, 4)->nullable()->comment('Snapshot: fixed amount per campaign'),
            'influencer_fixed_status' => fn (Blueprint $t) => $t->string('influencer_fixed_status', 20)->default('none')->comment('none | reserved | paid | released'),
            'influencer_fixed_source' => fn (Blueprint $t) => $t->string('influencer_fixed_source', 40)->nullable()->comment('profile_rule | default | manual'),
            'influencer_fixed_reserved_at' => fn (Blueprint $t) => $t->timestamp('influencer_fixed_reserved_at')->nullable(),
            'influencer_fixed_paid_at' => fn (Blueprint $t) => $t->timestamp('influencer_fixed_paid_at')->nullable(),
            'influencer_fixed_released_at' => fn (Blueprint $t) => $t->timestamp('influencer_fixed_released_at')->nullable(),
            'influencer_fixed_fee_amount' => fn (Blueprint $t) => $t->decimal('influencer_fixed_fee_amount', 19, 4)->nullable()->comment('Platform commission fee on the fixed amount'),
            'influencer_fixed_net_amount' => fn (Blueprint $t) => $t->decimal('influencer_fixed_net_amount', 19, 4)->nullable()->comment('Credited to the influencer wallet'),
            'influencer_fixed_wallet_tx_id' => fn (Blueprint $t) => $t->char('influencer_fixed_wallet_tx_id', 36)->nullable(),
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as $name => $define) {
            if (! Schema::hasColumn('marketer_campaign_invitations', $name)) {
                Schema::table('marketer_campaign_invitations', fn (Blueprint $table) => $define($table));
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->columns()) as $name) {
            if (Schema::hasColumn('marketer_campaign_invitations', $name)) {
                Schema::table('marketer_campaign_invitations', fn (Blueprint $table) => $table->dropColumn($name));
            }
        }
    }
};
