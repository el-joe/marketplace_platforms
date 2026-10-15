{{-- Marketer wallet breakdown: commissions gross -> platform commission fee -> net, and own-listing sales. --}}
<div class="text-xs text-gray-600 space-y-0.5" data-marketer-breakdown>
    <p data-breakdown-commissions>{{ __('admin.wallets.breakdown_commissions', ['gross' => number_format($b['commissions']['gross']), 'fee' => number_format($b['commissions']['fee']), 'net' => number_format($b['commissions']['net'])]) }}</p>
    @if($b['sales']['count'] > 0)
        <p data-breakdown-sales>{{ __('admin.wallets.breakdown_sales', ['gross' => number_format($b['sales']['gross']), 'commission' => number_format($b['sales']['commission']), 'vat' => number_format($b['sales']['vat']), 'peer' => number_format($b['sales']['peer']), 'net' => number_format($b['sales']['net'])]) }}</p>
    @endif
</div>
