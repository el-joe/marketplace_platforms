<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaign;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketerCampaignController extends Controller
{
    use AuthorizesRequests;

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    public function index()
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.view'),
            403
        );

        $campaigns = MarketerCampaign::where('vendor_id', $this->vendorId())
            ->with([
                'country',
                'vendorListing.productVariant.product',
                'adminListing.productVariant.product',
                'invitations.marketer',
                'tieredRules',
            ])
            ->withCount('invitations')
            ->latest()
            ->paginate(15);

        return view('partner.marketer_campaigns.index', compact('campaigns'));
    }

    public function show(MarketerCampaign $marketerCampaign)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.view'),
            403
        );
        abort_unless($marketerCampaign->vendor_id === $this->vendorId(), 403);

        $marketerCampaign->load([
            'country',
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
            'invitations.marketer.marketerProfile',
            'tieredRules',
            'conversions.order',
            'samples.invitation.marketer',
        ]);

        return view('partner.marketer_campaigns.show', compact('marketerCampaign'));
    }

    public function cancel(MarketerCampaign $marketerCampaign)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.cancel'),
            403
        );
        abort_unless($marketerCampaign->vendor_id === $this->vendorId(), 403);
        abort_unless($marketerCampaign->status === 'pending_admin', 403);

        $marketerCampaign->update(['status' => 'cancelled']);

        return back()->with('success', __('partner.marketer_campaigns_my.cancel_success'));
    }

    public function searchMarketers(Request $request): JsonResponse
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.view'),
            403
        );

        $vendor    = Auth::guard('vendor')->user()->vendor;
        $countryId = $request->input('country_id', $vendor->country_id);
        $search    = $request->input('q', '');
        $type      = $request->input('type'); // 'influencer' | 'affiliate' | null = all

        $marketers = \App\Models\Marketer::where('global_status', 'active')
            ->where('country_id', $countryId)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($type, fn ($q) => $q->where('marketer_type', $type))
            ->limit(20)
            ->get(['id', 'name', 'email', 'marketer_type']);

        return response()->json($marketers->map(fn ($m) => [
            'id'            => $m->id,
            'name'          => $m->name,
            'email'         => $m->email,
            'marketer_type' => $m->marketer_type,
            'type_label'    => $m->marketer_type === 'influencer' ? 'مؤثر' : 'أفيليت',
        ]));
    }
}
