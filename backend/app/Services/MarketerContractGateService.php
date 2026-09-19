<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\MarketerContract;
use App\Models\MarketerContractAcceptance;
use App\Models\MarketerListing;
use Illuminate\Support\Collection;

class MarketerContractGateService
{
    /**
     * Marketer ids of the cart lines whose marketer contract is required
     * and has a published version.
     *
     * @param  Cart|iterable  $cartOrItems
     * @return array<int, string>
     */
    public function requiredMarketerIds(Cart|iterable $cartOrItems): array
    {
        $items = $cartOrItems instanceof Cart ? $cartOrItems->items : collect($cartOrItems);

        $listingIds = collect($items)->pluck('marketer_listing_id')->filter()->unique()->values();
        if ($listingIds->isEmpty()) {
            return [];
        }

        $marketerIds = MarketerListing::whereIn('id', $listingIds)
            ->pluck('marketer_id')->filter()->unique()->values();
        if ($marketerIds->isEmpty()) {
            return [];
        }

        return MarketerContract::whereIn('marketer_id', $marketerIds)
            ->where('is_required', true)
            ->where('current_version', '>', 0)
            ->whereHas('activeVersion')
            ->pluck('marketer_id')->unique()->values()->all();
    }

    /**
     * Required marketer ids for which the customer holds no unlinked
     * acceptance of the active version.
     *
     * @return array<int, string>
     */
    public function missingAcceptances(Customer $customer, array $marketerIds): array
    {
        return collect($marketerIds)->filter(
            fn ($marketerId) => $this->pendingAcceptance($customer, $marketerId) === null
        )->values()->all();
    }

    public function pendingAcceptance(Customer $customer, string $marketerId, ?Collection $only = null): ?MarketerContractAcceptance
    {
        $contract = MarketerContract::with('activeVersion')->where('marketer_id', $marketerId)->first();
        if (! $contract?->activeVersion) {
            return null;
        }

        return MarketerContractAcceptance::where('customer_id', $customer->id)
            ->where('marketer_contract_version_id', $contract->activeVersion->id)
            ->whereNull('order_id')
            ->when($only, fn ($q) => $q->whereIn('id', $only))
            ->latest('accepted_at')
            ->first();
    }

    /**
     * @return array<int, array{marketer_id: string, is_required: bool, accepted: bool}>
     */
    public function gates(Customer $customer, Cart|iterable $cartOrItems): array
    {
        return collect($this->requiredMarketerIds($cartOrItems))->map(fn ($id) => [
            'marketer_id' => $id,
            'is_required' => true,
            'accepted' => $this->pendingAcceptance($customer, $id) !== null,
        ])->all();
    }
}
