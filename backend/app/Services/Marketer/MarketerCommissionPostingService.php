<?php

namespace App\Services\Marketer;

use App\Models\LedgerEntry;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\LedgerService;
use App\Services\WalletService;
use App\Support\Money;

/**
 * Wallet and ledger postings of marketer commissions (affiliate / broker conversions and influencer fixed amounts).
 *
 * One commission = one balanced ledger group:
 *
 *   Dr seller_payable    (vendor)    gross   what the campaign budget pays (platform_revenue when the platform owns the campaign)
 *   Cr marketer_payable  (marketer)  net     what the marketer wallet now holds
 *   Cr platform_commission           fee     the commission fee, reference_type = marketer_commission_fee
 *
 * gross = net + fee by construction (CommissionFeeService::split), so the group always balances.
 * Wallet movements of a conversion: approval parks the net in pending_balance, release moves it into the spendable
 * balance (and posts the ledger group), reversal takes it back from whichever of the two holds it.
 */
class MarketerCommissionPostingService
{
    public const REF_CONVERSION = 'marketer_conversion';

    public const REF_FEE = 'marketer_commission_fee';

    /** Legacy (pre-v3) statement group, still mirrored when an old conversion is reversed. */
    public const REF_LEGACY_STATEMENT = 'marketer_conversion_statement';

    public const REF_FIXED = 'marketer_campaign_fixed';

    public const WALLET_SOURCE_CONVERSION = 'marketer_conversion';

    public const WALLET_SOURCE_FIXED = 'marketer_campaign_fixed';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly WalletService $wallets,
        private readonly MarketerEarningsService $earnings,
    ) {}

    /** Approval: the conversion's net becomes visible to the marketer as pending balance. */
    public function creditPending(MarketerCampaignConversion $conversion, string $marketerId): float
    {
        $net = $this->earnings->walletAmount($conversion);
        $wallet = $this->wallets->getOrCreateWallet('marketer', $marketerId, $conversion->currency);
        Wallet::where('id', $wallet->id)->increment('pending_balance', $net);

        return $net;
    }

    /**
     * Release: pending -> spendable balance, wallet history line and the ledger group (idempotent per conversion).
     * The commission fee is part of the snapshot taken when the sale was recorded.
     */
    public function release(MarketerCampaignConversion $conversion, string $marketerId): void
    {
        $net = $this->earnings->walletAmount($conversion);
        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $marketerId)
            ->where('currency', $conversion->currency)->lockForUpdate()->first();

        if ($wallet) {
            Wallet::where('id', $wallet->id)->decrement('pending_balance', $net);
            Wallet::where('id', $wallet->id)->increment('balance', $net);
            $this->recordWalletTransaction($wallet, 'credit', $net, (float) $wallet->fresh()->balance, $conversion);
        }

        $breakdown = $this->earnings->breakdown($conversion);
        $campaign = $conversion->campaign ?? MarketerCampaign::withTrashed()->find($conversion->campaign_id);
        $this->postLedger(
            currency: (string) $conversion->currency,
            vendorId: $campaign?->vendor_id,
            marketerId: $marketerId,
            gross: $breakdown['gross'],
            fee: $breakdown['fee'],
            net: $breakdown['net'],
            referenceId: (string) $conversion->id,
            label: 'conversion '.$conversion->id,
            conversionLedger: true,
        );
    }

    /**
     * Credit an influencer's fixed amount: net straight to the spendable balance (the campaign is over, nothing is
     * left to clear), commission fee to the platform.
     *
     * @return array{wallet_tx_id: string, fee: float, net: float}
     */
    public function payFixed(string $invitationId, string $marketerId, ?string $vendorId, string $currency, float $gross, float $fee, float $net, string $description): array
    {
        $wallet = $this->wallets->getOrCreateWallet('marketer', $marketerId, $currency);
        $tx = $net > 0
            ? $this->wallets->credit($wallet, $net, self::WALLET_SOURCE_FIXED, $invitationId, mb_substr($description, 0, 255))
            : null;

        $this->postLedger($currency, $vendorId, $marketerId, $gross, $fee, $net, $invitationId, 'influencer fixed amount '.$invitationId, false);

        return ['wallet_tx_id' => (string) ($tx?->id ?? ''), 'fee' => $fee, 'net' => $net];
    }

    /**
     * Reverse everything posted for a conversion: wallet (pending or released) and the mirrored ledger groups.
     * Safe to call on a conversion that was never approved (nothing was posted yet).
     */
    public function reverse(MarketerCampaignConversion $conversion, ?string $marketerId = null): void
    {
        $net = $this->earnings->walletAmount($conversion);

        if ($conversion->wallet_credited_at && $marketerId) {
            $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $marketerId)
                ->where('currency', $conversion->currency)->lockForUpdate()->first();

            if ($wallet) {
                if ($conversion->wallet_released_at) {
                    Wallet::where('id', $wallet->id)->decrement('balance', $net);
                    $this->recordWalletTransaction(
                        $wallet, 'debit', $net, (float) $wallet->fresh()->balance, $conversion,
                        __('marketer.finance.tx_commission_reversed', ['order' => $this->reference($conversion)])
                    );
                } else {
                    Wallet::where('id', $wallet->id)->decrement('pending_balance', $net);
                }
            }
        }

        if ($conversion->wallet_released_at || $conversion->wallet_credited_at) {
            $this->mirrorLedger((string) $conversion->id);
        }
    }

    /** Mirror image of every posting of the reference (debit/credit swapped) under a new group; idempotent. */
    public function mirrorLedger(string $referenceId): void
    {
        $types = [self::REF_CONVERSION, self::REF_FEE, self::REF_LEGACY_STATEMENT];
        $original = LedgerEntry::whereIn('reference_type', $types)->where('reference_id', $referenceId)->get();
        $reversalTypes = array_map(fn (string $t) => $t.'_reversal', $types);

        if ($original->isEmpty() || LedgerEntry::whereIn('reference_type', $reversalTypes)->where('reference_id', $referenceId)->exists()) {
            return;
        }

        $this->ledger->record($this->ledger->newGroupId(), $original->map(fn (LedgerEntry $e) => [
            'account_type' => $e->account_type,
            'account_holder_type' => $e->account_holder_type,
            'account_holder_id' => $e->account_holder_id,
            'debit' => Money::number($e->credit),
            'credit' => Money::number($e->debit),
            'currency' => $e->currency,
            'reference_type' => $e->reference_type.'_reversal',
            'reference_id' => $referenceId,
            'description' => "Reversal of marketer commission {$referenceId}",
        ])->all());
    }

    /** One wallet history line per wallet movement of a conversion. */
    public function recordWalletTransaction(Wallet $wallet, string $direction, int|float|string $amount, int|float|string $balanceAfter, MarketerCampaignConversion $conversion, ?string $description = null): ?WalletTransaction
    {
        if (abs((float) $amount) <= 0) {
            return null;
        }

        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => $direction,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'currency_code' => $wallet->currency,
            'source_type' => self::WALLET_SOURCE_CONVERSION,
            'source_id' => (string) $conversion->id,
            'description' => mb_substr($description ?? $this->earnings->walletDescription($conversion), 0, 255),
            'created_at' => now(),
        ]);
    }

    private function reference(MarketerCampaignConversion $conversion): string
    {
        return $conversion->order?->order_number
            ?? '#'.substr((string) ($conversion->order_id ?? $conversion->classified_listing_id ?? $conversion->travel_booking_id ?? $conversion->id), 0, 8);
    }

    private function postLedger(string $currency, ?string $vendorId, string $marketerId, float $gross, float $fee, float $net, string $referenceId, string $label, bool $conversionLedger): void
    {
        if ($gross <= 0) {
            return;
        }

        $referenceType = $conversionLedger ? self::REF_CONVERSION : self::REF_FIXED;
        if (LedgerEntry::whereIn('reference_type', [$referenceType, self::REF_FEE, self::REF_LEGACY_STATEMENT])->where('reference_id', $referenceId)->exists()) {
            return;
        }

        $row = fn (string $type, ?string $holderType, ?string $holderId, float $debit, float $credit, string $reference, string $text) => [
            'account_type' => $type,
            'account_holder_type' => $holderType,
            'account_holder_id' => $holderId,
            'debit' => round($debit, 4),
            'credit' => round($credit, 4),
            'currency' => $currency,
            'reference_type' => $reference,
            'reference_id' => $referenceId,
            'description' => "{$text} ({$label})",
        ];

        $entries = [
            $vendorId
                ? $row('seller_payable', 'vendor', $vendorId, $gross, 0.0, $referenceType, 'Commission paid from the campaign budget')
                : $row('platform_revenue', null, null, $gross, 0.0, $referenceType, 'Commission paid by the platform'),
        ];
        if ($net > 0) {
            $entries[] = $row('marketer_payable', 'marketer', $marketerId, 0.0, $net, $referenceType, 'Net commission payable to marketer');
        }
        if ($fee > 0) {
            $entries[] = $row('platform_commission', null, null, 0.0, $fee, self::REF_FEE, 'Commission fee');
        }

        $this->ledger->record($this->ledger->newGroupId(), $entries);
    }
}
