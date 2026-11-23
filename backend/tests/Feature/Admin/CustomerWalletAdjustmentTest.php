<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PDOException;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Admin → Customers → Wallet & balances → manual adjustment (client item #40): the adjustment moves the
 * customer's spendable wallet, writes a ledger row with a source, and either fully succeeds or leaves nothing.
 */
class CustomerWalletAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();

        foreach (['customers.view', 'wallet.manual_adjust', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['customers.view', 'wallet.manual_adjust']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function adjust(array $payload): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.customers.wallet.adjust', $this->scenario->customer), $payload + ['note' => 'Goodwill credit']);
    }

    public function test_a_manual_credit_records_a_sourced_transaction_and_raises_the_balance(): void
    {
        $wallet = $this->scenario->customerWallet;
        $before = (float) $wallet->balance;

        $this->adjust(['direction' => 'credit', 'amount' => '78.75'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('admin.customers_section.wallet_adjusted'));

        $this->assertEqualsWithDelta($before + 78.75, (float) $wallet->fresh()->balance, 0.0001);

        $transaction = WalletTransaction::where('wallet_id', $wallet->id)->sole();
        $this->assertSame('admin_adjustment', $transaction->source_type);
        $this->assertSame('credit', $transaction->direction);
        $this->assertSame($this->scenario->customer->id, $transaction->customer_id);
        $this->assertSame($this->admin->id, $transaction->performed_by_admin_id);
        $this->assertEqualsWithDelta(78.75, (float) $transaction->amount, 0.0001);
    }

    public function test_a_manual_debit_records_a_sourced_transaction_and_lowers_the_balance(): void
    {
        $wallet = $this->scenario->customerWallet;
        $before = (float) $wallet->balance;

        $this->adjust(['direction' => 'debit', 'amount' => '20.50'])->assertOk();

        $this->assertEqualsWithDelta($before - 20.50, (float) $wallet->fresh()->balance, 0.0001);

        $transaction = WalletTransaction::where('wallet_id', $wallet->id)->sole();
        $this->assertSame('admin_adjustment', $transaction->source_type);
        $this->assertSame('debit', $transaction->direction);
    }

    public function test_a_debit_above_the_balance_is_refused_without_writing_anything(): void
    {
        $wallet = $this->scenario->customerWallet;
        $before = (float) $wallet->balance;

        $this->adjust(['direction' => 'debit', 'amount' => (string) ($before + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => __('common.exceptions.wallet.insufficient_balance')]);

        $this->assertEqualsWithDelta($before, (float) $wallet->fresh()->balance, 0.0001);
        $this->assertSame(0, WalletTransaction::where('wallet_id', $wallet->id)->count());
    }

    public function test_a_database_failure_rolls_back_the_adjustment_and_hides_the_sql(): void
    {
        $wallet = $this->scenario->customerWallet;
        $before = (float) $wallet->balance;

        Activity::creating(function (): void {
            throw new QueryException('mysql', 'insert into `activity_log` (`secret_column`) values (?)', ['secret-value'], new PDOException('SQLSTATE[HY000]: boom'));
        });

        $response = $this->adjust(['direction' => 'credit', 'amount' => '78.75'])
            ->assertInternalServerError()
            ->assertExactJson(['success' => false, 'message' => __('common.exceptions.server_error')]);

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertEqualsWithDelta($before, (float) $wallet->fresh()->balance, 0.0001);
        $this->assertSame(0, WalletTransaction::where('wallet_id', $wallet->id)->count());
    }
}
