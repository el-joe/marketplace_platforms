<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CustomerSpecialRequest;
use App\Services\SpecialRequestRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialRequestController extends Controller
{
    public function __construct(
        private readonly SpecialRequestRoutingService $routing,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $requests = CustomerSpecialRequest::with(['category:id,name_en,name_ar', 'city:id,name_en,name_ar'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return ApiResponse::success($requests);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id'     => ['required', 'uuid', 'exists:categories,id'],
            'city_id'         => ['nullable', 'uuid', 'exists:cities,id'],
            'title_en'        => ['required', 'string', 'max:255'],
            'title_ar'        => ['nullable', 'string', 'max:255'],
            'description_en'  => ['required', 'string'],
            'description_ar'  => ['nullable', 'string'],
            'budget'          => ['nullable', 'integer', 'min:0'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $data['customer_id'] = auth('customer')->id();

        $specialRequest = CustomerSpecialRequest::create($data);

        $notified = $this->routing->notifyMatchingBrokers($specialRequest);

        return ApiResponse::success([
            'request_id'       => $specialRequest->id,
            'brokers_notified' => $notified,
        ], 'Request posted. Matching brokers have been notified.', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $customer = auth('customer')->user();

        $specialRequest = CustomerSpecialRequest::with(['category:id,name_en,name_ar', 'city:id,name_en,name_ar'])
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->firstOrFail();

        return ApiResponse::success($specialRequest);
    }

    public function close(Request $request, string $id): JsonResponse
    {
        $customer = auth('customer')->user();

        $specialRequest = CustomerSpecialRequest::where('customer_id', $customer->id)
            ->where('id', $id)
            ->firstOrFail();

        $specialRequest->update(['status' => 'closed']);

        return ApiResponse::success($specialRequest);
    }
}
