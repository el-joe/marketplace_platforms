<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SampleController extends Controller
{
    private function marketer(): \App\Models\Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    /**
     * All samples allocated to this marketer across all campaigns.
     */
    public function index(): View
    {
        $marketer = $this->marketer();

        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->pluck('id');

        $samples = MarketerCampaignSample::whereIn('invitation_id', $invitationIds)
            ->where('sample_owner', 'marketer')
            ->with([
                'campaign.vendorListing.productVariant.product',
                'campaign.adminListing.productVariant.product',
                'invitation',
            ])
            ->latest()
            ->paginate(20);

        $statusCounts = [
            'pending'    => $samples->where('status', 'pending')->count(),
            'dispatched' => $samples->where('status', 'dispatched')->count(),
            'delivered'  => $samples->where('status', 'delivered')->count(),
        ];

        return view('marketer.samples.index', compact('marketer', 'samples', 'statusCounts'));
    }

    /**
     * Submit or update the delivery address for a sample.
     * The marketer provides their shipping address so admin can dispatch the product sample.
     */
    public function submitAddress(Request $request, MarketerCampaignSample $sample): RedirectResponse
    {
        $marketer = $this->marketer();

        abort_unless(
            MarketerCampaignInvitation::where('id', $sample->invitation_id)
                ->where('marketer_id', $marketer->id)
                ->exists(),
            403
        );

        abort_unless($sample->status === 'pending', 422, 'العينة غير قابلة للتعديل.');

        $request->validate([
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:100'],
            'country'        => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', 'max:30'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $sample->update([
            'delivery_address_snapshot' => $request->only([
                'address_line_1', 'address_line_2', 'city', 'country', 'phone', 'notes',
            ]),
        ]);

        return back()->with('success', 'تم حفظ عنوان التوصيل. سيتواصل معك الفريق لإرسال العينة.');
    }
}
