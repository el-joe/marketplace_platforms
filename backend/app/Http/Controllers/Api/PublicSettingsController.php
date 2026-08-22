<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PublicSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Cache::remember('settings:public', 300, function () {
            return Setting::where('is_public', true)->pluck('value', 'key');
        });

        return response()->json(['success' => true, 'data' => $settings]);
    }
}
