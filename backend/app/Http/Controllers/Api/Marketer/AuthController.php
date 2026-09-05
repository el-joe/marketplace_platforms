<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $credentials = [
            'email'     => $request->email,
            'password'  => $request->password,
            'is_active' => 1,
        ];

        if (! $token = Auth::guard('marketer_api')->attempt($credentials)) {
            return response()->json(['success' => false, 'message' => 'بيانات الدخول غير صحيحة.'], 401);
        }

        $marketerAdmin = Auth::guard('marketer_api')->user();
        $marketer      = $marketerAdmin->marketer;

        if (! $marketer->isActive()) {
            Auth::guard('marketer_api')->logout();
            return response()->json(['success' => false, 'message' => 'حسابك غير مفعّل بعد.'], 403);
        }

        $marketerAdmin->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        return response()->json([
            'success' => true,
            'token'   => $token,
            'marketer' => [
                'id'             => $marketer->id,
                'name'           => $marketer->name,
                'email'          => $marketer->email,
                'marketer_type'  => $marketer->marketer_type,
                'global_status'  => (string) $marketer->global_status,
                'country_id'     => $marketer->country_id,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:marketer_admins,email',
            'password'       => 'required|min:8',
            'marketer_type'  => 'required|in:influencer,affiliate',
            'country_id'     => 'required|exists:countries,id',
            'phone'          => 'nullable|string|max:30',
            'whatsapp_for_campaigns' => 'nullable|string|max:30',
        ]);

        $marketer = DB::transaction(function () use ($request) {
            $m = Marketer::create([
                'name'                   => $request->name,
                'email'                  => $request->email,
                'phone'                  => $request->phone,
                'marketer_type'          => $request->marketer_type,
                'whatsapp_for_campaigns' => $request->whatsapp_for_campaigns,
                'country_id'             => $request->country_id,
                'global_status'          => 'pending',
            ]);
            MarketerAdmin::create([
                'marketer_id' => $m->id,
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => $request->password,
                'is_owner'    => true,
                'is_active'   => true,
            ]);
            return $m;
        });

        return response()->json([
            'success' => true,
            'message' => 'تم التسجيل. سيتم مراجعة حسابك والتواصل معك قريباً.',
        ], 201);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('marketer_api')->logout();
        return response()->json(['success' => true, 'message' => 'تم تسجيل الخروج.']);
    }

    public function refresh(): JsonResponse
    {
        $token = Auth::guard('marketer_api')->refresh();
        return response()->json(['success' => true, 'token' => $token]);
    }

    public function me(): JsonResponse
    {
        $marketerAdmin = Auth::guard('marketer_api')->user();
        $marketer = $marketerAdmin->marketer;
        return response()->json([
            'success'  => true,
            'marketer' => [
                'id'             => $marketer->id,
                'name'           => $marketer->name,
                'email'          => $marketer->email,
                'marketer_type'  => $marketer->marketer_type,
                'global_status'  => (string) $marketer->global_status,
            ],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        \Illuminate\Support\Facades\Password::broker('marketer_admins')
            ->sendResetLink($request->only('email'));
        return response()->json(['success' => true, 'message' => 'إذا كان البريد مسجلاً، سيصلك رابط الاستعادة.']);
    }
}
