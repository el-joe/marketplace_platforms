<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\FooterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FooterController extends Controller
{
    /**
     * GET /api/public/v1/footer (global static pages only) or
     * GET /api/public/v1/{country}/footer (static pages for that country).
     */
    public function index(Request $request, FooterService $footerService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $footerService->getFooterData($request->attributes->get('country')),
        ]);
    }
}
