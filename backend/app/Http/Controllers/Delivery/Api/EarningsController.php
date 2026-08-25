<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\Earnings\EarningsIndexRequest;
use App\Http\Resources\Delivery\EarningsResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EarningsController extends Controller
{
    public function index(EarningsIndexRequest $request): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        $currency = $agent->country?->currency_code
            ?? $agent->zone?->country?->currency_code
            ?? 'AED';

        $query = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->orderByDesc('created_at');

        if ($from = $request->date_from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->date_to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $earnings = $query->paginate(30);

        // Today's total across all earning types.
        $todayTotal = DeliveryAgentEarning::where('agent_id', $agent->id)
            ->whereDate('created_at', today())
            ->sum('amount');

        // Group by calendar day for the daily breakdown.
        $byDay = $earnings->getCollection()
            ->groupBy(fn ($e) => $e->created_at->toDateString())
            ->map(fn ($group, $date) => [
                'date'        => $date,
                'total'       => $group->sum('amount'),
                'earnings'    => EarningsResource::collection($group)->resolve(),
            ])
            ->values();

        return ApiResponse::success([
            'currency'          => $currency,
            'today_total'       => (int) $todayTotal,
            'days'              => $byDay,
            'meta'              => [
                'current_page' => $earnings->currentPage(),
                'last_page'    => $earnings->lastPage(),
                'total'        => $earnings->total(),
            ],
        ]);
    }
}
