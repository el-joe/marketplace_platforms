<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCommissionRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommissionRuleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => self::rulesFor(Auth::guard('marketer_api')->user()->marketer)]);
    }

    /** Only the given marketer's own rules (never platform defaults). */
    public static function rulesFor(Marketer $marketer): array
    {
        $currency = $marketer->country?->currency_code ?? '';

        return MarketerCommissionRule::with('category')
            ->where('marketer_id', $marketer->id)
            ->orderBy('scope')
            ->get()
            ->map(fn ($r) => [
                'scope' => $r->scope,
                'category' => $r->category ? [
                    'id' => $r->category->getKey(),
                    'name' => $r->category->name ?? $r->category->name_en ?? null,
                ] : null,
                'excluded_categories' => $r->category_id === null && $r->excluded_category_ids
                    ? $r->categoryClassFor($r->scope)::whereIn('id', $r->excluded_category_ids)->get()
                        ->map(fn ($c) => ['id' => $c->getKey(), 'name' => $c->name ?? $c->name_en ?? null])->values()->all()
                    : [],
                'commission_mode' => $r->commission_mode,
                'commission_rate' => $r->commission_rate,
                'commission_flat_amount' => $r->commission_flat_amount,
                'currency' => $currency,
            ])->values()->all();
    }
}
