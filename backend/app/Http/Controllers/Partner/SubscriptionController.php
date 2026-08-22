<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\VendorSubscriptionInvoice;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $service)
    {
    }

    // ── Portal: Subscription page ─────────────────────────────────────────────

    public function index(): View
    {
        $vendorAdmin = Auth::guard('vendor')->user();
        $vendor = $vendorAdmin->vendor;

        $activeSub = $this->service->getActiveSubscription($vendor->id);
        $activePlan = $activeSub?->plan;

        $plans = SubscriptionPlan::active()->ordered()->get();

        $invoices = VendorSubscriptionInvoice::where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return view('partner.subscription.index', compact(
            'vendor',
            'activeSub',
            'activePlan',
            'plans',
            'invoices'
        ));
    }

    // ── Portal: Subscribe / upgrade / downgrade ───────────────────────────────

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $vendor = Auth::guard('vendor')->user()->vendor;
        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id']);

        $subscription = $this->service->subscribe($vendor, $plan);

        return response()->json([
            'success' => true,
            'message' => 'تم الاشتراك في خطة ' . $plan->name_ar . ' بنجاح.',
        ]);
    }

    // ── Portal: Cancel subscription ───────────────────────────────────────────

    public function cancel(Request $request): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user()->vendor;
        $sub = $this->service->getActiveSubscription($vendor->id);

        if (!$sub) {
            return response()->json(['success' => false, 'message' => 'لا يوجد اشتراك نشط للإلغاء.'], 422);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->service->cancel($sub, $data['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء اشتراكك. يظل الاشتراك نشطاً حتى نهاية الفترة الحالية.',
        ]);
    }
}
