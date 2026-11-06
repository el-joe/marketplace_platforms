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
    public function index($country): JsonResponse
    {
        // Cache plain arrays: cache.serializable_classes=false turns cached
        // Eloquent objects into __PHP_Incomplete_Class on read.
        $rows = SafeCache::remember('currencies:active:v2', 3600, function () {
            return Currency::where('is_active', true)->orderBy('code')->get()
                ->map(fn ($c) => $c->getAttributes())->all();
        });

        $currencies = Currency::hydrate($rows);

        return response()->json([
            'success' => true,
            'data' => CurrencyResource::collection($currencies),
        ]);
    }
}
