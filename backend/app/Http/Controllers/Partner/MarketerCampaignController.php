<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaign;
use App\Models\VendorListing;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketerCampaignController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly MarketerCampaignService $marketerCampaignService)
    {
    }

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    private function vendor(): \App\Models\Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    private function hasActiveCampaign(string $vendorListingId): bool
    {
        return MarketerCampaign::where('vendor_listing_id', $vendorListingId)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->exists();
    }

    public function create(VendorListing $vendorListing)
    {
        abort_unless($vendorListing->vendor_id === $this->vendorId(), 403);
        abort_unless($vendorListing->fulfillment_model === 'fbn', 403, 'حملات الماركتر متاحة فقط لقوائم FBN.');

        $existing = MarketerCampaign::where('vendor_listing_id', $vendorListing->id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->first();

        if ($existing) {
            return redirect()
                ->route('partner.marketer-campaigns.show', $existing)
                ->with('info', 'هذه القائمة لديها حملة نشطة بالفعل. يمكنك دعوة المزيد من الماركترز من هنا.');
        }

        $vendorListing->load('productVariant.product');

        $marketerVendors = \App\Models\Marketer::where('global_status', 'active')
            ->where('country_id', $vendorListing->country_id)
            ->orderBy('name')
            ->get(['id', 'name', 'marketer_type']);

        return view('partner.marketer_campaigns.create', compact('vendorListing', 'marketerVendors'));
    }

    public function store(Request $request)
    {
        $vendor = $this->vendor();

        $request->validate([
            'vendor_listing_id'     => ['required', 'uuid', 'exists:vendor_listings,id'],
            'commission_type'       => ['required', 'in:fixed,tiered,last_click'],
            'max_commission_budget' => ['nullable', 'numeric', 'min:0'],
            'marketer_ids'          => ['required', 'array', 'min:1'],
            'marketer_ids.*'        => ['uuid', 'distinct', 'exists:marketers,id'],
            'tiered_rules'          => ['nullable', 'array'],
        ]);

        $listing = VendorListing::where('id', $request->vendor_listing_id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        abort_unless($listing->fulfillment_model === 'fbn', 403, 'حملات الماركتر متاحة فقط لقوائم FBN.');
        abort_if($this->hasActiveCampaign($listing->id), 403, 'هذه القائمة لديها حملة نشطة أو قيد المراجعة بالفعل.');

        try {
            $this->marketerCampaignService->createCampaign($vendor, array_merge(
                $request->only(['commission_type', 'max_commission_budget']),
                [
                    'vendor_listing_id' => $listing->id,
                    'country_id'        => $listing->country_id,
                    'currency'          => $listing->currency,
                    'marketer_ids'      => $request->input('marketer_ids', []),
                    'tiered_rules'      => $request->input('tiered_rules', []),
                ]
            ));
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'تعذر إنشاء حملة الماركتر: ' . $e->getMessage());
        }

        return redirect()
            ->route('partner.marketer-campaigns.index')
            ->with('success', 'تم إنشاء حملة الماركتر بنجاح.');
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

    public function inviteMarketers(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.create'),
            403
        );
        abort_unless($marketerCampaign->vendor_id === $this->vendorId(), 403);

        $request->validate([
            'marketer_ids'   => ['required', 'array', 'min:1'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
        ]);

        $result = $this->marketerCampaignService->inviteMarketers(
            $marketerCampaign, $request->input('marketer_ids', [])
        );

        $message = count($result['invited']) . ' ماركتر تمت دعوته.';
        if (!empty($result['skipped'])) {
            $message .= ' تم تجاهل ' . count($result['skipped']) . ' (مدعو بالفعل أو غير نشط).';
        }

        return back()->with('success', $message);
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
