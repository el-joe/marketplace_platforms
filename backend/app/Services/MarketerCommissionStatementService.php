<?php

namespace App\Services;

use App\Enums\MarketerCommissionDiscountType;
use App\Models\Country;
use App\Models\LedgerEntry;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;

/**
 * Gross commission -> admin cut -> discount -> VAT -> net, in DECIMAL base units (rounded half up to 2 decimals).
 *
 * Order (fixed):
 *   1. cut_base = round_half_up(gross * cut% / 100)           (cut% = marketer_profiles.platform_cut_percentage)
 *   2. discount = the profile's admin-granted commission discount, which discounts THE CUT
 *                 (flat: min(flat, cut_base); percentage: round(cut_base * pct / 100, 2));
 *                 cut = cut_base - discount
 *   3. vat      = round_half_up(cut * countries.vat_rate / 100)   (VAT on the platform's service cut, as for P9 fees)
 *   4. net      = gross - cut - vat                              (credited to the wallet; withdrawals pay this)
 *
 * Percentages are converted to basis points (x100) first, then applied once and rounded to 2 decimals.
 * Example: gross 100, 30% => cut 30, VAT 0 => net 70.
 */
class MarketerCommissionStatementService
{
    /**
     * @return array{gross: float, cut_percentage: float, cut_base: float, discount: float, cut: float, vat_rate: float, vat: float, net: float}
     */
    public function breakdown(int|float|string $gross, float|string $cutPct, ?MarketerProfile $profile = null, float|string $vatRate = 0): array
    {
        $gross = max(0.0, round((float) $gross, 2));
        $cutBp = $this->bp($cutPct, 0, 10000);
        $cutBase = $this->mulBp($gross, $cutBp);

        $discount = 0.0;
        if ($profile && $cutBase > 0) {
            $type = $profile->commission_discount_type;
            if ($type === MarketerCommissionDiscountType::Flat) {
                $discount = min(max(0.0, (float) $profile->commission_discount_flat), $cutBase);
            } elseif ($type === MarketerCommissionDiscountType::Percentage) {
                $discount = $this->mulBp($cutBase, $this->bp($profile->commission_discount_percentage, 0, 10000));
            }
        }

        $cut = round($cutBase - $discount, 2);
        $vatBp = $this->bp($vatRate, 0, 100000);
        $vat = $this->mulBp($cut, $vatBp);

        return [
            'gross' => $gross,
            'cut_percentage' => $cutBp / 100,
            'cut_base' => $cutBase,
            'discount' => $discount,
            'cut' => $cut,
            'vat_rate' => $vatBp / 100,
            'vat' => $vat,
            'net' => round($gross - $cut - $vat, 2),
        ];
    }

    public function vatRateForCountry(?string $countryId): float
    {
        return $countryId ? (float) (Country::whereKey($countryId)->value('vat_rate') ?? 0) : 0.0;
    }

    /** Live breakdown for a conversion that was not approved yet (preview). */
    public function previewFor(MarketerCampaignConversion $conversion): array
    {
        $profile = $conversion->invitation?->marketer?->marketerProfile;
        $countryId = $conversion->campaign?->country_id ?? $conversion->invitation?->marketer?->country_id;

        return $this->breakdown(
            $conversion->total_commission,
            $profile?->platform_cut_percentage ?? 0,
            $profile,
            $this->vatRateForCountry($countryId)
        );
    }

    /** Persisted breakdown when approved, otherwise a preview. */
    public function forConversion(MarketerCampaignConversion $c): array
    {
        if ($c->net_commission_amount !== null) {
            return [
                'gross' => (float) $c->gross_commission_amount,
                'cut_percentage' => (float) $c->platform_cut_percentage,
                'cut_base' => (float) $c->platform_cut_base_amount,
                'discount' => (float) $c->platform_cut_discount_amount,
                'cut' => (float) $c->platform_cut_amount,
                'vat_rate' => (float) $c->platform_cut_vat_rate,
                'vat' => (float) $c->platform_cut_vat_amount,
                'net' => (float) $c->net_commission_amount,
            ];
        }

        return $this->previewFor($c);
    }

    /** Snapshot attributes to store on the conversion at approval. */
    public function snapshotAttributes(MarketerCampaignConversion $c): array
    {
        $b = $this->previewFor($c);

        return [
            'gross_commission_amount' => $b['gross'],
            'platform_cut_percentage' => $b['cut_percentage'],
            'platform_cut_base_amount' => $b['cut_base'],
            'platform_cut_discount_amount' => $b['discount'],
            'platform_cut_amount' => $b['cut'],
            'platform_cut_vat_rate' => $b['vat_rate'],
            'platform_cut_vat_amount' => $b['vat'],
            'net_commission_amount' => $b['net'],
        ];
    }

    /** Amount the wallet holds for this conversion (net; legacy approved rows without snapshot = gross). */
    public static function walletAmount(MarketerCampaignConversion $c): float
    {
        return $c->net_commission_amount !== null ? (float) $c->net_commission_amount : $c->total_commission;
    }

    public const LEDGER_REFERENCE = 'marketer_conversion_statement';

    /**
     * Ledger postings for one approved conversion (idempotent per conversion):
     *   Dr marketer_commission_payable  gross                     (the commission the platform owed through the order)
     *   Cr marketer_payable             net   (holder: marketer)   (what the marketer wallet now holds; withdrawals debit it)
     *   Cr platform_commission          cut                        (platform income)
     *   Cr tax_payable                  VAT on the cut             (owed to the tax authority)
     * gross = net + cut + vat by construction (see breakdown()), so the group always balances.
     */
    public function postApprovalLedger(MarketerCampaignConversion $c, ?LedgerService $ledger = null): void
    {
        $ledger ??= app(LedgerService::class);
        $gross = (float) $c->gross_commission_amount;
        if ($c->net_commission_amount === null || $gross <= 0) {
            return;
        }

        if (LedgerEntry::where('reference_type', self::LEDGER_REFERENCE)->where('reference_id', (string) $c->id)->exists()) {
            return;
        }

        $marketerId = $c->invitation?->marketer_id;
        $row = fn (string $type, ?string $holderType, ?string $holderId, float $debit, float $credit, string $label) => [
            'account_type' => $type,
            'account_holder_type' => $holderType,
            'account_holder_id' => $holderId,
            'debit' => round($debit, 4),
            'credit' => round($credit, 4),
            'currency' => $c->currency,
            'reference_type' => self::LEDGER_REFERENCE,
            'reference_id' => (string) $c->id,
            'description' => "{$label} for conversion {$c->id}",
        ];

        $entries = [$row('marketer_commission_payable', null, null, $gross, 0.0, 'Gross commission')];
        foreach ([
            ['marketer_payable', 'marketer', $marketerId, (float) $c->net_commission_amount, 'Net commission payable to marketer'],
            ['platform_commission', null, null, (float) $c->platform_cut_amount, 'Platform cut'],
            ['tax_payable', null, null, (float) $c->platform_cut_vat_amount, 'VAT on platform cut'],
        ] as [$type, $holderType, $holderId, $credit, $label]) {
            if (abs($credit) > 0) {
                $entries[] = $row($type, $holderType, $holderId, 0.0, $credit, $label);
            }
        }

        $ledger->record($ledger->newGroupId(), $entries);
    }

    /** Mirror image of postApprovalLedger() when the conversion is reversed (idempotent). */
    public function reverseApprovalLedger(MarketerCampaignConversion $c, ?LedgerService $ledger = null): void
    {
        $ledger ??= app(LedgerService::class);
        $original = LedgerEntry::where('reference_type', self::LEDGER_REFERENCE)->where('reference_id', (string) $c->id)->get();
        if ($original->isEmpty()
            || LedgerEntry::where('reference_type', self::LEDGER_REFERENCE.'_reversal')->where('reference_id', (string) $c->id)->exists()) {
            return;
        }

        $ledger->record($ledger->newGroupId(), $original->map(fn (LedgerEntry $e) => [
            'account_type' => $e->account_type,
            'account_holder_type' => $e->account_holder_type,
            'account_holder_id' => $e->account_holder_id,
            'debit' => (float) $e->credit,
            'credit' => (float) $e->debit,
            'currency' => $e->currency,
            'reference_type' => self::LEDGER_REFERENCE.'_reversal',
            'reference_id' => (string) $c->id,
            'description' => "Reversal of commission statement for conversion {$c->id}",
        ])->all());
    }

    /** Human line for the wallet transaction written when the conversion's net becomes spendable. */
    public function walletDescription(MarketerCampaignConversion $c): string
    {
        $b = $this->forConversion($c);
        $order = $c->order?->order_number ?? '#'.substr((string) $c->order_id, 0, 8);

        return mb_substr(__('marketer.finance.tx_commission', [
            'order' => $order, 'gross' => Money::format($b['gross']), 'cut' => Money::format($b['cut']),
            'vat' => Money::format($b['vat']), 'net' => Money::format($b['net']),
        ]), 0, 255);
    }

    /**
     * One wallet transaction per wallet movement of a conversion. `$direction` = credit when its net is released
     * to the spendable balance, debit when a released conversion is reversed. $balanceAfter is the wallet balance after the move.
     */
    public function recordWalletTransaction(Wallet $wallet, string $direction, int|float|string $amount, int|float|string $balanceAfter, MarketerCampaignConversion $c, ?string $description = null): ?WalletTransaction
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
            'source_type' => 'marketer_conversion',
            'source_id' => (string) $c->id,
            'description' => $description ?? $this->walletDescription($c),
            'created_at' => now(),
        ]);
    }

    /**
     * Lifetime commission statement of one marketer over approved (default) conversions: gross / cut / VAT / net,
     * plus how many conversions. Used by the admin withdrawal + wallet screens.
     *
     * @return array{gross: float, cut: float, vat: float, net: float, count: int}
     */
    public function totalsForMarketer(Marketer|string $marketer, array $statuses = ['approved']): array
    {
        $marketerId = $marketer instanceof Marketer ? $marketer->id : $marketer;
        $totals = ['gross' => 0.0, 'cut' => 0.0, 'vat' => 0.0, 'net' => 0.0, 'count' => 0];

        MarketerCampaignConversion::query()
            ->whereIn('status', $statuses)
            ->whereIn('invitation_id', MarketerCampaignInvitation::where('marketer_id', $marketerId)->select('id'))
            ->with(['campaign', 'invitation.marketer.marketerProfile'])
            ->each(function (MarketerCampaignConversion $c) use (&$totals) {
                $b = $this->forConversion($c);
                foreach (['gross', 'cut', 'vat', 'net'] as $k) {
                    $totals[$k] = round($totals[$k] + $b[$k], 2);
                }
                $totals['count']++;
            });

        return $totals;
    }

    private function bp(float|string|int|null $pct, int $min, int $max): int
    {
        return max($min, min($max, (int) round(((float) $pct) * 100)));
    }

    private function mulBp(float $amount, int $bp): float
    {
        return round($amount * $bp / 10000, 2);
    }
}
