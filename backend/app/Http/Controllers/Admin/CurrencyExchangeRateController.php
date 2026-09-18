<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurrencyExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * docs/plans/international_product_shipping.md Phase 5.
 *
 * currency_exchange_rates is append-only (design decision #3 / Phase 1
 * migration: no updated_at column, never mutated in place). This controller
 * therefore only ever INSERTs a new row (store()) — there is deliberately no
 * update()/edit()/destroy() here, only index() which shows the full rate
 * history per currency pair, most recent first.
 */
class CurrencyExchangeRateController extends Controller
{
    public function index(): View
    {
        $rates = CurrencyExchangeRate::query()
            ->orderBy('from_currency_code')
            ->orderBy('to_currency_code')
            ->orderByDesc('effective_at')
            ->get()
            ->groupBy(fn (CurrencyExchangeRate $rate) => $rate->from_currency_code . ' → ' . $rate->to_currency_code);

        return view('admin.currency-exchange-rates.index', [
            'ratesByPair' => $rates,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Currency Exchange Rates'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_currency_code' => ['required', 'string', 'size:3'],
            'to_currency_code' => ['required', 'string', 'size:3', 'different:from_currency_code'],
            'rate_numerator' => ['required', 'integer', 'min:1'],
            'rate_denominator' => ['required', 'integer', 'min:1'],
            'effective_at' => ['required', 'date'],
        ]);

        $data['from_currency_code'] = strtoupper($data['from_currency_code']);
        $data['to_currency_code'] = strtoupper($data['to_currency_code']);
        $data['rate_numerator'] = (int) $data['rate_numerator'];
        $data['rate_denominator'] = (int) $data['rate_denominator'];

        // Insert-only: a new snapshot row, never an update of an existing one.
        CurrencyExchangeRate::create($data);

        return redirect()->route('admin.currency-exchange-rates.index')
            ->with('success', 'Exchange rate snapshot added.');
    }
}
