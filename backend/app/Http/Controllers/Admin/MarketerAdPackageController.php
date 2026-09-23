<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketerAdPackage;
use App\Models\MarketerAdPackageSubscription;
use App\Services\Marketer\AdPackageService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MarketerAdPackageController extends Controller
{
    public function __construct(private readonly AdPackageService $service) {}

    public function index(): View
    {
        $packages = MarketerAdPackage::ordered()->get();
        $pending = MarketerAdPackageSubscription::where('status', 'pending')->with(['marketer', 'package'])->latest()->get();
        $recent = MarketerAdPackageSubscription::where('status', '!=', 'pending')->with(['marketer', 'package'])->latest()->limit(30)->get();

        return view('admin.marketer-ad-packages.index', compact('packages', 'pending', 'recent'));
    }

    public function create(): View
    {
        return view('admin.marketer-ad-packages.form', ['package' => new MarketerAdPackage(['vat_pct' => 15, 'is_active' => true])]);
    }

    private function rules(): array
    {
        return [
            'name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string', 'price' => 'required|integer|min:0',
            'currency' => 'required|string|size:3', 'vat_pct' => 'required|integer|between:0,100',
            'target_type' => 'required|in:influencer,affiliate,broker,all', 'duration_days' => 'required|integer|min:1',
            'features_text' => 'nullable|string', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0',
        ];
    }

    private function payload(Request $request): array
    {
        $d = $request->validate($this->rules());
        $d['features'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $d['features_text'] ?? ''))));
        unset($d['features_text']);
        $d['currency'] = strtoupper($d['currency']);
        $d['is_active'] = $request->boolean('is_active');
        $d['sort_order'] = $d['sort_order'] ?? 0;

        return $d;
    }

    public function store(Request $request): RedirectResponse
    {
        MarketerAdPackage::create($this->payload($request));

        return redirect()->route('admin.marketer-ad-packages.index')->with('success', __('ad_packages.saved'));
    }

    public function edit(MarketerAdPackage $marketerAdPackage): View
    {
        return view('admin.marketer-ad-packages.form', ['package' => $marketerAdPackage]);
    }

    public function update(Request $request, MarketerAdPackage $marketerAdPackage): RedirectResponse
    {
        $marketerAdPackage->update($this->payload($request));

        return redirect()->route('admin.marketer-ad-packages.index')->with('success', __('ad_packages.saved'));
    }

    public function destroy(MarketerAdPackage $marketerAdPackage): RedirectResponse
    {
        if ($marketerAdPackage->subscriptions()->exists()) {
            $marketerAdPackage->update(['is_active' => false]);

            return back()->with('success', __('ad_packages.deactivated_has_subs'));
        }
        $marketerAdPackage->delete();

        return back()->with('success', __('ad_packages.deleted'));
    }

    public function approve(MarketerAdPackageSubscription $subscription): RedirectResponse
    {
        try {
            $this->service->approve($subscription);
        } catch (DomainException $e) {
            return back()->with('error', __('ad_packages.err_'.$e->getMessage()));
        }

        return back()->with('success', __('ad_packages.approved'));
    }

    public function reject(MarketerAdPackageSubscription $subscription): RedirectResponse
    {
        try {
            $this->service->reject($subscription, Auth::guard('admin')->id());
        } catch (DomainException $e) {
            return back()->with('error', __('ad_packages.err_'.$e->getMessage()));
        }

        return back()->with('success', __('ad_packages.rejected'));
    }

    public function proof(MarketerAdPackageSubscription $subscription)
    {
        abort_unless($subscription->payment_proof_path && Storage::disk('private')->exists($subscription->payment_proof_path), 404);

        return Storage::disk('private')->download($subscription->payment_proof_path);
    }
}
