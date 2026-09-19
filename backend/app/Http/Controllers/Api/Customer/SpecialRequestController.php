<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreSpecialRequestRequest;
use App\Models\Country;
use App\Http\Responses\ApiResponse;
use App\Models\CustomerSpecialRequest;
use App\Services\SpecialRequestRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SpecialRequestController extends Controller
{
    private const STATUSES = ['open', 'in_progress', 'closed'];

    public function __construct(
        private readonly SpecialRequestRoutingService $routing,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $requests = CustomerSpecialRequest::with(['category:id,name_en,name_ar', 'city:id,name_en,name_ar'])
            ->where('customer_id', $customer->id)
            ->when(
                in_array($request->query('status'), self::STATUSES, true),
                fn ($q) => $q->where('status', $request->query('status'))
            )
            ->latest()
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success($requests);
    }

    public function store(StoreSpecialRequestRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $key = 'special-request:' . $customer->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return ApiResponse::error('Too many requests. Please try again later.', [], 429);
        }
        RateLimiter::hit($key, 3600);

        $data = $request->validated();
        $data['customer_id'] = $customer->id;

        if (empty($data['budget_currency'])) {
            $country = $request->attributes->get('country');
            $country = $country instanceof Country ? $country : $customer->country;
            $data['budget_currency'] = $country?->currency_code;
        } else {
            $data['budget_currency'] = strtoupper($data['budget_currency']);
        }

        $specialRequest = CustomerSpecialRequest::create($data);

        $notified = $this->routing->notifyMatchingBrokers($specialRequest);

        return ApiResponse::success([
            'request_id'       => $specialRequest->id,
            'brokers_notified' => $notified,
            'status'           => $specialRequest->status,
        ], 'Request posted. Matching brokers have been notified.', 201);
    }

    public function show(Request $request, string $country, string $id): JsonResponse
    {
        $customer = auth('customer')->user();

        $specialRequest = CustomerSpecialRequest::with(['category:id,name_en,name_ar', 'city:id,name_en,name_ar'])
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->firstOrFail();

        return ApiResponse::success($specialRequest);
    }

    public function close(Request $request, string $country, string $id): JsonResponse
    {
        $customer = auth('customer')->user();

        $specialRequest = CustomerSpecialRequest::where('customer_id', $customer->id)
            ->where('id', $id)
            ->firstOrFail();

        if (! in_array($specialRequest->status, ['open', 'in_progress'], true)) {
            return ApiResponse::error('Only open or in-progress requests can be closed.', [], 422);
        }

        $specialRequest->update(['status' => 'closed']);

        return ApiResponse::success($specialRequest);
    }
}
