<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\PageResource;
use App\Http\Responses\ApiResponse;
use App\Models\BlockClickEvent;
use App\Models\PageBlock;
use App\Services\Customer\PageRendererService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function __construct(
        private readonly PageRendererService $renderer,
    ) {}

    public function show(string $type, Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');
        $slug      = $request->query('slug');
        $sessionId = $request->header('X-Session-Id') ?? $request->cookie('session_id') ?? session()->getId();
        $customer  = $request->user('customer');

        $result = $this->renderer->render($type, $slug ?: null, $country, $customer, (string) $sessionId);

        if (empty($result)) {
            return ApiResponse::error(__('common.exceptions.page.not_found'), [], 404);
        }

        return ApiResponse::success(new PageResource($result));
    }

    /**
     * POST /api/customer/v1/{country}/blocks/{id}/click
     * No auth required — guests can click. Rate-limited via throttle middleware
     * on the route (see routes/api_customer.php).
     */
    public function click(Request $request, $country, string $id): JsonResponse
    {
        $resolvedCountry = $request->attributes->get('country');

        $block = PageBlock::find($id);
        if (!$block) {
            return response()->json(['success' => false, 'message' => __('common.exceptions.page.block_not_found')], 404);
        }

        $validated = $request->validate([
            'click_target' => ['required', 'string', 'max:500'],
            'click_target_type' => ['required', 'string', 'in:product,category,url,cta'],
            'session_id' => ['required', 'string', 'max:100'],
            'device_type' => ['nullable', 'string', 'in:desktop,mobile,app'],
        ]);

        $customer = $request->user('customer');

        BlockClickEvent::create([
            'page_block_id' => $block->id,
            'user_id' => $customer?->id,
            'session_id' => $validated['session_id'],
            'click_target' => $validated['click_target'],
            'click_target_type' => $validated['click_target_type'],
            'device_type' => $validated['device_type'] ?? 'desktop',
            'country_id' => $resolvedCountry->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true]);
    }
}
