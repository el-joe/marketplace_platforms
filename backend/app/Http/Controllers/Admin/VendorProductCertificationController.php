<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorProductCertification;
use App\Notifications\Vendor\ProductCertificationApproved;
use App\Notifications\Vendor\ProductCertificationRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class VendorProductCertificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = VendorProductCertification::query()
            ->with(['vendor', 'product', 'country', 'reviewedByAdmin']);

        $status = $request->get('status', 'pending');
        if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected', 'expired'], true)) {
            $query->where('status', $status);
        }

        if ($countryId = $request->get('country_id')) {
            $query->where('country_id', $countryId);
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('vendor', fn($v) => $v->where('store_name', 'like', "%{$search}%"))
                    ->orWhereHas('product', fn($p) => $p->where('name_en', 'like', "%{$search}%"));
            });
        }

        $certifications = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.vendor_product_certifications.index', [
            'certifications' => $certifications,
            'pendingCount' => VendorProductCertification::where('status', 'pending')->count(),
            'countries' => \App\Models\Country::orderBy('name_en')->get(['id', 'name_en']),
        ]);
    }

    public function show(string $id): View
    {
        $certification = VendorProductCertification::query()
            ->with(['vendor', 'product', 'country', 'reviewedByAdmin'])
            ->findOrFail($id);

        $downloadUrl = URL::temporarySignedRoute(
            'admin.vendor-product-certifications.download',
            now()->addMinutes(15),
            ['id' => $certification->id]
        );

        return view('admin.vendor_product_certifications.show', [
            'certification' => $certification,
            'downloadUrl' => $downloadUrl,
        ]);
    }

    public function download(Request $request, string $id)
    {
        abort_unless($request->hasValidSignature(), 403);

        $certification = VendorProductCertification::findOrFail($id);

        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($certification->file_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $certification->file_path,
            $certification->original_filename ?? basename($certification->file_path)
        );
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $certification = VendorProductCertification::findOrFail($id);

        $data = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $certification->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'expires_at' => $data['expires_at'] ?? null,
            'reviewed_by_admin_id' => auth('admin')->id(),
            'reviewed_at' => now(),
        ]);

        $certification->vendor?->notify(new ProductCertificationApproved($certification));

        return redirect()
            ->route('admin.vendor-product-certifications.show', $certification->id)
            ->with('success', __('admin.vendor_product_certifications.approved_success'));
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $certification = VendorProductCertification::findOrFail($id);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $certification->update([
            'status' => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'expires_at' => null,
            'reviewed_by_admin_id' => auth('admin')->id(),
            'reviewed_at' => now(),
        ]);

        $certification->vendor?->notify(new ProductCertificationRejected($certification));

        return redirect()
            ->route('admin.vendor-product-certifications.show', $certification->id)
            ->with('success', __('admin.vendor_product_certifications.rejected_success'));
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $certifications = VendorProductCertification::query()
            ->whereIn('id', $data['ids'])
            ->where('status', 'pending')
            ->get();

        foreach ($certifications as $certification) {
            $certification->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by_admin_id' => auth('admin')->id(),
                'reviewed_at' => now(),
            ]);

            $certification->vendor?->notify(new ProductCertificationApproved($certification));
        }

        return redirect()
            ->route('admin.vendor-product-certifications.index')
            ->with('success', __('admin.vendor_product_certifications.bulk_approved_success', ['count' => $certifications->count()]));
    }
}
