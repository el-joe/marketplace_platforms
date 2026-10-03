<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\VendorAdmin;
use App\Models\VendorApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    private function admin(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    /** GET /partner/developer */
    public function index(): View
    {
        $admin = $this->admin();
        $tokens = $admin->apiTokens()->orderByDesc('created_at')->get();
        $docSections = $this->buildDocSections();

        return view('partner.developer.index', compact('tokens', 'docSections'));
    }

    private function buildDocSections(): array
    {
        return [
            ['title' => 'Authentication', 'endpoints' => [
                ['method' => 'POST', 'path' => '/auth/login',          'description' => 'Obtain a JWT token with email + password (mobile app)'],
                ['method' => 'POST', 'path' => '/auth/refresh-token',  'description' => 'Refresh an expiring JWT token'],
                ['method' => 'POST', 'path' => '/auth/logout',         'description' => 'Invalidate the current JWT token'],
                ['method' => 'GET',  'path' => '/auth/me',             'description' => 'Retrieve the authenticated admin and vendor profile'],
                ['method' => 'POST', 'path' => '/auth/device-token',   'description' => 'Register a push notification device token'],
                ['method' => 'DELETE', 'path' => '/auth/device-token', 'description' => 'Remove a push notification device token'],
            ]],
            ['title' => 'Orders', 'endpoints' => [
                ['method' => 'GET',  'path' => '/orders',                             'description' => 'List sub-orders (filterable by status, date)'],
                ['method' => 'GET',  'path' => '/orders/{subOrderNumber}',            'description' => 'Get full order detail'],
                ['method' => 'POST', 'path' => '/orders/{subOrderNumber}/confirm',    'description' => 'Confirm a placed order'],
                ['method' => 'POST', 'path' => '/orders/{subOrderNumber}/ship',       'description' => 'Mark order as shipped (requires tracking_number)'],
                ['method' => 'POST', 'path' => '/orders/{subOrderNumber}/out-for-delivery', 'description' => 'Mark order as out for delivery'],
                ['method' => 'POST', 'path' => '/orders/{subOrderNumber}/deliver',    'description' => 'Mark order as delivered'],
                ['method' => 'POST', 'path' => '/orders/{subOrderNumber}/cancel',     'description' => 'Cancel an order (requires reason)'],
            ]],
            ['title' => 'Returns', 'endpoints' => [
                ['method' => 'GET',  'path' => '/returns',                      'description' => 'List return requests'],
                ['method' => 'GET',  'path' => '/returns/{returnNumber}',       'description' => 'Get return detail'],
                ['method' => 'POST', 'path' => '/returns/{returnNumber}/approve', 'description' => 'Approve a return request'],
                ['method' => 'POST', 'path' => '/returns/{returnNumber}/reject',  'description' => 'Reject a return request (requires rejection_reason)'],
            ]],
            ['title' => 'Listings', 'endpoints' => [
                ['method' => 'GET',  'path' => '/listings',                     'description' => 'List your product listings'],
                ['method' => 'GET',  'path' => '/listings/{id}',                'description' => 'Get listing detail'],
                ['method' => 'GET',  'path' => '/listings/{id}/promo-badges',   'description' => 'Get promo badges for a listing'],
                ['method' => 'POST', 'path' => '/listings/{id}/toggle-status',  'description' => 'Toggle listing between active and paused'],
                ['method' => 'POST', 'path' => '/listings/{id}/update-price',   'description' => 'Update listing price (integer, smallest currency unit)'],
                ['method' => 'POST', 'path' => '/listings/{id}/adjust-stock',   'description' => 'Adjust stock quantity for a warehouse inventory record'],
            ]],
            ['title' => 'Inventory', 'endpoints' => [
                ['method' => 'GET', 'path' => '/inventory',                        'description' => 'List inventory records across warehouses'],
                ['method' => 'GET', 'path' => '/inventory/{id}/movements',         'description' => 'Get movement history for an inventory record'],
                ['method' => 'GET', 'path' => '/inventory/transfers',              'description' => 'List stock transfer requests'],
                ['method' => 'GET', 'path' => '/inventory/transfers/{transferNumber}', 'description' => 'Get transfer detail'],
            ]],
            ['title' => 'Coupons', 'endpoints' => [
                ['method' => 'GET',    'path' => '/coupons',                    'description' => 'List your coupons'],
                ['method' => 'POST',   'path' => '/coupons',                    'description' => 'Create a new coupon'],
                ['method' => 'GET',    'path' => '/coupons/{id}',               'description' => 'Get coupon detail'],
                ['method' => 'PUT',    'path' => '/coupons/{id}',               'description' => 'Update a coupon'],
                ['method' => 'POST',   'path' => '/coupons/{id}/toggle-status', 'description' => 'Toggle coupon active/inactive'],
                ['method' => 'DELETE', 'path' => '/coupons/{id}',               'description' => 'Delete a coupon (only if never used)'],
            ]],
            ['title' => 'Finance', 'endpoints' => [
                ['method' => 'GET', 'path' => '/finance/summary',          'description' => 'Financial summary (balance, pending, total earned)'],
                ['method' => 'GET', 'path' => '/finance/transactions',     'description' => 'Transaction history'],
                ['method' => 'GET', 'path' => '/finance/ledger',           'description' => 'Detailed ledger entries'],
                ['method' => 'GET', 'path' => '/finance/commission-rates', 'description' => 'Your commission rate structure'],
                ['method' => 'GET', 'path' => '/finance/sales-report',     'description' => 'Sales report with revenue breakdown'],
                ['method' => 'GET', 'path' => '/finance/payouts',          'description' => 'Payout history'],
                ['method' => 'GET', 'path' => '/finance/payouts/{id}',     'description' => 'Payout detail'],
            ]],
            ['title' => 'Performance', 'endpoints' => [
                ['method' => 'GET', 'path' => '/performance',         'description' => 'Performance metrics (fulfillment rate, SLA score)'],
                ['method' => 'GET', 'path' => '/performance/reviews', 'description' => 'Customer reviews for your products'],
            ]],
            ['title' => 'Support Tickets', 'endpoints' => [
                ['method' => 'GET',  'path' => '/support-tickets',                      'description' => 'List your support tickets'],
                ['method' => 'POST', 'path' => '/support-tickets',                      'description' => 'Open a new support ticket'],
                ['method' => 'GET',  'path' => '/support-tickets/{ticketNumber}',       'description' => 'Get ticket detail with messages'],
                ['method' => 'POST', 'path' => '/support-tickets/{ticketNumber}/replies', 'description' => 'Reply to an open ticket'],
            ]],
            ['title' => 'Team', 'endpoints' => [
                ['method' => 'GET',    'path' => '/team',              'description' => 'List team members'],
                ['method' => 'POST',   'path' => '/team',              'description' => 'Invite a new team member (owner only)'],
                ['method' => 'PUT',    'path' => '/team/{memberId}',   'description' => 'Update member role (owner only)'],
                ['method' => 'DELETE', 'path' => '/team/{memberId}',   'description' => 'Remove a team member (owner only)'],
            ]],
            ['title' => 'Profile', 'endpoints' => [
                ['method' => 'GET', 'path' => '/profile',           'description' => 'Get store profile'],
                ['method' => 'GET', 'path' => '/profile/documents', 'description' => 'Get uploaded compliance documents'],
            ]],
            ['title' => 'Notifications', 'endpoints' => [
                ['method' => 'GET', 'path' => '/notifications',              'description' => 'List notifications'],
                ['method' => 'GET', 'path' => '/notifications/unread-count', 'description' => 'Get unread count'],
                ['method' => 'PUT', 'path' => '/notifications/read-all',     'description' => 'Mark all as read'],
                ['method' => 'PUT', 'path' => '/notifications/{id}/read',    'description' => 'Mark one as read'],
            ]],
            ['title' => 'Developer — Token Management', 'endpoints' => [
                ['method' => 'GET',    'path' => '/developer/tokens',      'description' => 'List API tokens (JWT auth required)'],
                ['method' => 'POST',   'path' => '/developer/tokens',      'description' => 'Generate a new permanent API token (JWT auth required)'],
                ['method' => 'DELETE', 'path' => '/developer/tokens/{id}', 'description' => 'Revoke an API token (JWT auth required)'],
            ]],
        ];
    }

    /** POST /partner/developer/tokens */
    public function storeToken(Request $request): RedirectResponse
    {
        $admin = $this->admin();

        if (! $admin->is_owner && ! $admin->isManager()) {
            abort(403, 'Only owners and managers may manage API tokens.');
        }

        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $plainToken = 'vnd_'.Str::random(64);
        $hashed = hash('sha256', $plainToken);
        $prefix = substr($plainToken, 0, 12);

        $admin->apiTokens()->create([
            'name' => $data['name'],
            'token' => $hashed,
            'token_prefix' => $prefix,
        ]);

        return redirect()
            ->route('partner.developer.index')
            ->with('new_token', $plainToken)
            ->with('success', 'API token generated. Copy it now — it will not be shown again.');
    }

    /** DELETE /partner/developer/tokens/{id} */
    public function destroyToken(Request $request, int $id): RedirectResponse
    {
        $admin = $this->admin();

        if (! $admin->is_owner && ! $admin->isManager()) {
            abort(403, 'Only owners and managers may manage API tokens.');
        }

        $token = VendorApiToken::where('vendor_admin_id', $admin->id)->findOrFail($id);
        $token->delete();

        return redirect()
            ->route('partner.developer.index')
            ->with('success', 'API token revoked.');
    }
}
