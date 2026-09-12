<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CurrencyResource;
use App\Models\Currency;
use App\Support\SafeCache;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    /**
     * GET /currencies
     * Active currencies with their display symbol (text or image). Cached 1h.
     */
    public function index(): JsonResponse
    {
        $currencies = SafeCache::remember('currencies:active', 3600, function () {
            return Currency::where('is_active', true)->orderBy('code')->get();
        });

        return response()->json([
            'success' => true,
            'data' => CurrencyResource::collection($currencies),
        ]);
    }
}
