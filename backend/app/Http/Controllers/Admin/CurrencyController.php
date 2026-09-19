<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CurrencySymbolType;
use App\Http\Controllers\Controller;
use App\Jobs\UpdateExchangeRatesJob;
use App\Models\Currency;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $currencies = Currency::orderByDesc('is_active')->orderBy('code')->get();

        return view('admin.currencies.index', [
            'currencies' => $currencies,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Currencies'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $code): View
    {
        $currency = Currency::findOrFail(strtoupper($code));

        return view('admin.currencies.edit', [
            'currency' => $currency,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Currencies', 'url' => route('admin.currencies.index')],
                ['label' => $currency->code . ' — ' . $currency->name],
            ],
        ]);
    }

    public function update(Request $request, string $code): RedirectResponse
    {
        $currency = Currency::findOrFail(strtoupper($code));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
            'symbol_type' => ['required', Rule::enum(CurrencySymbolType::class)],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'exchange_rate_to_base' => ['required', 'numeric', 'min:0.000001'],
            'base_currency_code' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'is_manually_overridden' => ['boolean'],
        ]);

        // If the rate was explicitly changed, flag it as manually overridden
        if ((float) $data['exchange_rate_to_base'] !== (float) $currency->exchange_rate_to_base) {
            $data['is_manually_overridden'] = true;
            $data['rate_updated_at'] = now();
        }

        $currency->update($data);

        return back()->with('success', "{$currency->code} updated successfully.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Manual rate sync trigger
    // ─────────────────────────────────────────────────────────────────────────

    public function dispatchUpdate(): JsonResponse
    {
        UpdateExchangeRatesJob::dispatch();

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate update has been queued.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: Update a single currency rate
    // ─────────────────────────────────────────────────────────────────────────

    public function updateRate(Request $request, string $code): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.edit'), 403);

        $data = $request->validate([
            'rate' => ['required', 'numeric', 'min:0.000001'],
        ]);

        $currency = Currency::findOrFail(strtoupper($code));
        $currency->update([
            'exchange_rate_to_base' => (float) $data['rate'],
            'is_manually_overridden' => true,
            'rate_updated_at' => now(),
        ]);

        return response()->json([
            'message' => "{$currency->code} rate updated to {$data['rate']}.",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: Upload / remove the currency's image symbol
    // ─────────────────────────────────────────────────────────────────────────

    public function uploadSymbolImage(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'symbol_image' => ['required', 'file', 'max:512', 'mimes:png,jpg,jpeg,svg,webp', 'mimetypes:image/png,image/jpeg,image/svg+xml,image/webp,text/xml,text/plain'],
        ]);

        $currency = Currency::findOrFail(strtoupper($code));

        if ($currency->symbol_image) {
            Storage::disk('public')->delete($currency->symbol_image);
        }

        $file = $request->file('symbol_image');
        if (strtolower($file->getClientOriginalExtension()) === 'svg') {
            $svg = self::sanitizeSvg((string) file_get_contents($file->getRealPath()));
            if ($svg === null) {
                return response()->json(['success' => false, 'message' => 'Invalid SVG file.'], 422);
            }
            $path = 'currencies/' . \Illuminate\Support\Str::uuid() . '.svg';
            Storage::disk('public')->put($path, $svg);
        } else {
            $path = $file->store('currencies', 'public');
        }

        $currency->update(['symbol_image' => $path]);

        return response()->json([
            'success' => true,
            'symbol_image_url' => Storage::disk('public')->url($path),
            'message' => "{$currency->code} symbol image updated.",
        ]);
    }

    public static function sanitizeSvg(string $svg): ?string
    {
        if (!preg_match('/<svg[\s>]/i', $svg)) {
            return null;
        }
        $svg = preg_replace('#<script\b.*?</script\s*>#is', '', $svg);
        $svg = preg_replace('#<(foreignObject|iframe|object|embed)\b.*?</\1\s*>#is', '', $svg);
        $svg = preg_replace('#</?(script|foreignObject|iframe|object|embed)\b[^>]*>#i', '', $svg);
        $svg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
        $svg = preg_replace('/\s(?:xlink:)?href\s*=\s*("\s*(?!#)[^"]*"|\'\s*(?!#)[^\']*\')/i', '', $svg);
        $svg = preg_replace('/javascript:/i', '', $svg);
        $svg = preg_replace('#<!DOCTYPE[^>]*(\[.*?\])?>#is', '', $svg);
        return $svg;
    }

    public function deleteSymbolImage(string $code): JsonResponse
    {
        $currency = Currency::findOrFail(strtoupper($code));

        if ($currency->symbol_image) {
            Storage::disk('public')->delete($currency->symbol_image);
            $currency->update(['symbol_image' => null]);
        }

        return response()->json(['success' => true, 'message' => 'Symbol image removed.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: Dispatch job to refresh all rates from API
    // ─────────────────────────────────────────────────────────────────────────

    public function refreshRates(): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('settings.edit'), 403);

        UpdateExchangeRatesJob::dispatch();

        return response()->json(['message' => 'Exchange rate refresh queued successfully.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: Return rendered rates table tbody
    // ─────────────────────────────────────────────────────────────────────────

    public function ratesTable(): \Illuminate\View\View
    {
        $currencies = Currency::where('is_active', 1)->orderBy('code')->get();

        return view('admin.settings.partials._rates_table', compact('currencies'));
    }
}

