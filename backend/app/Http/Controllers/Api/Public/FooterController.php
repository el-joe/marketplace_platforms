<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\FooterService;
use Illuminate\Http\JsonResponse;

class FooterController extends Controller
{
    public function index(FooterService $footerService): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $footerService->getFooterData()]);
    }
}
