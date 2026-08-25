<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\VendorAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private function guard() { return Auth::guard('vendor_api'); }

    /** POST /api/partner/v1/auth/login */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!$token = $this->guard()->attempt($credentials)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        return $this->tokenResponse($token);
    }

    /** POST /api/partner/v1/auth/refresh-token */
    public function refresh(): JsonResponse
    {
        return $this->tokenResponse($this->guard()->refresh());
    }

    /** POST /api/partner/v1/auth/logout */
    public function logout(): JsonResponse
    {
        $this->guard()->logout();
        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }

    /** GET /api/partner/v1/auth/me */
    public function me(): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin  = $this->guard()->user();
        $vendor = $admin->vendor->load('country');

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => [
                    'id'       => $admin->id,
                    'name'     => $admin->name,
                    'email'    => $admin->email,
                    'role'     => $admin->role,
                    'is_owner' => $admin->is_owner,
                ],
                'vendor' => [
                    'id'            => $vendor->id,
                    'store_name'    => $vendor->store_name,
                    'logo_url'      => $vendor->logo_url,
                    'global_status' => $vendor->global_status,
                    'vendor_type'   => $vendor->vendor_type,
                    'marketer_type' => $vendor->marketer_type,
                    'country'       => $vendor->country ? [
                        'name'          => $vendor->country->name_en,
                        'currency_code' => $vendor->country->currency_code,
                        'flag_emoji'    => $vendor->country->flag_emoji ?? null,
                    ] : null,
                    'rating_avg'        => $vendor->rating_avg,
                    'total_sales_count' => $vendor->total_sales_count,
                ],
            ],
        ]);
    }

    /** POST /api/partner/v1/auth/device-token */
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $data  = $request->validate([
            'token'    => ['required', 'string'],
            'platform' => ['required', 'in:android,ios,web'],
        ]);
        $admin = $this->guard()->user();

        DeviceToken::updateOrCreate(
            ['tokenable_type' => VendorAdmin::class, 'tokenable_id' => $admin->id, 'token' => $data['token']],
            ['platform' => $data['platform'], 'last_used_at' => now()]
        );

        return response()->json(['success' => true, 'message' => 'Device token registered.']);
    }

    /** DELETE /api/partner/v1/auth/device-token */
    public function removeDeviceToken(Request $request): JsonResponse
    {
        $data  = $request->validate(['token' => ['required', 'string']]);
        $admin = $this->guard()->user();

        DeviceToken::where('tokenable_type', VendorAdmin::class)
            ->where('tokenable_id', $admin->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['success' => true, 'message' => 'Device token removed.']);
    }

    private function tokenResponse(string $token): JsonResponse
    {
        return response()->json([
            'success'      => true,
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $this->guard()->factory()->getTTL() * 60,
        ]);
    }
}
