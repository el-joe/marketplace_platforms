<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeveloperController extends Controller
{
    /** GET /api/partner/v1/developer/tokens */
    public function index(Request $request): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth()->guard('vendor_api')->user();

        $tokens = $admin->apiTokens()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'token_prefix', 'last_used_at', 'created_at']);

        return response()->json(['success' => true, 'data' => $tokens]);
    }

    /** POST /api/partner/v1/developer/tokens */
    public function store(Request $request): JsonResponse
    {
        $this->denyTokenAuth($request);

        /** @var VendorAdmin $admin */
        $admin = auth()->guard('vendor_api')->user();

        $this->authorizeOwnerOrManager($admin);

        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $plainToken = 'vnd_'.Str::random(64);
        $hashed = hash('sha256', $plainToken);
        $prefix = substr($plainToken, 0, 12);

        $token = $admin->apiTokens()->create([
            'name' => $data['name'],
            'token' => $hashed,
            'token_prefix' => $prefix,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token created. Copy it now — it will not be shown again.',
            'data' => [
                'id' => $token->id,
                'name' => $token->name,
                'token_prefix' => $token->token_prefix,
                'plain_token' => $plainToken,
                'created_at' => $token->created_at,
            ],
        ], 201);
    }

    /** DELETE /api/partner/v1/developer/tokens/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->denyTokenAuth($request);

        /** @var VendorAdmin $admin */
        $admin = auth()->guard('vendor_api')->user();

        $this->authorizeOwnerOrManager($admin);

        $token = $admin->apiTokens()->findOrFail($id);
        $token->delete();

        return response()->json(['success' => true, 'message' => 'Token revoked.']);
    }

    private function denyTokenAuth(Request $request): void
    {
        if ($request->attributes->get('api_auth_method') === 'token') {
            abort(403, 'Token management requires JWT authentication.');
        }
    }

    private function authorizeOwnerOrManager(VendorAdmin $admin): void
    {
        if (! $admin->is_owner && ! $admin->isManager()) {
            abort(403, 'Only owners and managers may manage API tokens.');
        }
    }
}
