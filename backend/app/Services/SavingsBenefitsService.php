<?php

namespace App\Services;

use App\Models\CartCardOffer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SavingsBenefitsService
{
    public const CACHE_VERSION_KEY = 'savings_benefits_cache_version';

    /**
     * @param  int    $orderTotal BIGINT base currency (no /100, no *100 — ever)
     * @param  string $countryId  countries.id UUID
     * @param  string $currency   3-char currency code matching the order
     */
    public function get(int $orderTotal, string $countryId, string $currency): array
    {
        $installments = $this->bnplInstallments($orderTotal, $countryId, $currency)
            ->merge($this->bankInstallments($countryId))
            ->sortBy('sort_order')
            ->values();

        return [
            'installments' => $installments->toArray(),
            'card_offers'  => $this->cardOffers($orderTotal, $countryId)->toArray(),
        ];
    }

    private function bnplInstallments(int $orderTotal, string $countryId, string $currency): Collection
    {
        // BNPL gateways not yet configured in the new payment_gateways schema.
        // Return empty until a BNPL gateway (e.g. Tabby) is added to payment_gateways seeder.
        return collect();
    }

    private function bankInstallments(string $countryId): Collection
    {
        // Bank installment plans not yet configured in the new payment_gateways schema.
        return collect();
    }

    private function cardOffers(int $orderTotal, string $countryId): Collection
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        $offers = Cache::remember("benefits:card_offers_v{$version}:{$countryId}", 300, fn () =>
            CartCardOffer::where('country_id', $countryId)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get()
                ->toArray()
        );

        $now = now();

        return collect($offers)
            ->filter(fn (array $offer) =>
                (!$offer['valid_from'] || \Carbon\Carbon::parse($offer['valid_from'])->lte($now))
                && (!$offer['valid_until'] || \Carbon\Carbon::parse($offer['valid_until'])->gte($now))
                && (!$offer['min_order_amount'] || $offer['min_order_amount'] <= $orderTotal))
            ->map(function (array $offer) use ($orderTotal) {
                $cashback = $offer['cashback_type'] === 'percentage'
                    ? (int) floor($orderTotal * (float) $offer['cashback_pct'] / 100)
                    : $offer['cashback_fixed_amount'];

                if ($offer['max_cashback_amount'] && $cashback > $offer['max_cashback_amount']) {
                    $cashback = $offer['max_cashback_amount'];
                }

                $labelEn = str_replace(
                    ['{amount}', '{card_name}'],
                    [$cashback, $offer['card_name_en']],
                    $offer['label_template_en']
                );
                $labelAr = $offer['label_template_ar']
                    ? str_replace(
                        ['{amount}', '{card_name}'],
                        [$cashback, $offer['card_name_ar']],
                        $offer['label_template_ar']
                    )
                    : null;

                return [
                    'type'            => 'card_cashback',
                    'card_name_en'    => $offer['card_name_en'],
                    'card_name_ar'    => $offer['card_name_ar'],
                    'bank_name_en'    => $offer['bank_name_en'],
                    'bank_name_ar'    => $offer['bank_name_ar'],
                    'card_image_url'  => $this->resolveLogoUrl($offer['card_image_path']),
                    'cashback_type'   => $offer['cashback_type'],
                    'cashback_pct'    => (string) $offer['cashback_pct'],
                    'cashback_amount' => $cashback,
                    'label_en'        => $labelEn,
                    'label_ar'        => $labelAr,
                    'apply_url'       => $offer['apply_url'],
                    'apply_label_en'  => $offer['apply_label_en'],
                    'apply_label_ar'  => $offer['apply_label_ar'],
                    'sort_order'      => $offer['sort_order'],
                ];
            })
            ->values();
    }

    public static function flushCache(): void
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::put(self::CACHE_VERSION_KEY, $version + 1);
    }

    private function resolveInstallmentLabel(string $template, int $n, int $amount): string
    {
        return str_replace(['{n}', '{amount}'], [$n, $amount], $template);
    }

    private function resolveLogoUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return config('app.url') . $path;
        }

        return Storage::disk('public')->url($path);
    }
}
