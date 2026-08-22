<?php

namespace App\Http\Controllers\Partner;

use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductCountry;
use App\Models\VendorListing;
use App\Models\VendorProductCertification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductCertificationController extends Controller
{
    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    public function index(): View
    {
        $vendorId = $this->vendorId();

        $pairs = VendorListing::query()
            ->where('vendor_id', $vendorId)
            ->where('status', VendorListingStatus::Active)
            ->with('productVariant:id,product_id')
            ->get(['id', 'product_variant_id', 'country_id'])
            ->map(fn (VendorListing $listing) => [
                'product_id' => $listing->productVariant?->product_id,
                'country_id' => $listing->country_id,
            ])
            ->filter(fn (array $pair) => $pair['product_id'] !== null)
            ->unique(fn (array $pair) => $pair['product_id'].'|'.$pair['country_id']);

        $required = ProductCountry::query()
            ->where('requires_local_cert', true)
            ->whereIn('product_id', $pairs->pluck('product_id')->unique())
            ->whereIn('country_id', $pairs->pluck('country_id')->unique())
            ->with(['product:id,name_en', 'country:id,name_en'])
            ->get()
            ->filter(fn (ProductCountry $pc) => $pairs->contains(
                fn (array $pair) => $pair['product_id'] === $pc->product_id && $pair['country_id'] === $pc->country_id
            ));

        $certs = VendorProductCertification::query()
            ->where('vendor_id', $vendorId)
            ->get()
            ->keyBy(fn (VendorProductCertification $cert) => $cert->product_id.'|'.$cert->country_id);

        $rows = $required->map(function (ProductCountry $pc) use ($certs) {
            $cert = $certs->get($pc->product_id.'|'.$pc->country_id);

            return [
                'product_id'   => $pc->product_id,
                'country_id'   => $pc->country_id,
                'product_name' => $pc->product?->name_en,
                'country_name' => $pc->country?->name_en,
                'status'       => $cert?->status ?? 'missing',
                'cert'         => $cert,
            ];
        })->values();

        $grouped = [
            'missing'  => $rows->where('status', 'missing')->values(),
            'pending'  => $rows->where('status', 'pending')->values(),
            'approved' => $rows->where('status', 'approved')->values(),
            'rejected' => $rows->where('status', 'rejected')->values(),
        ];

        $hasIssues = $grouped['missing']->isNotEmpty() || $grouped['rejected']->isNotEmpty();

        return view('vendor.product_certifications.index', [
            'rows'      => $rows,
            'grouped'   => $grouped,
            'hasIssues' => $hasIssues,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'country_id' => ['required', 'exists:countries,id'],
            'cert_name'  => ['nullable', 'string', 'max:255'],
            'file'       => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $vendorId = $this->vendorId();

        $requirement = ProductCountry::query()
            ->where('product_id', $request->input('product_id'))
            ->where('country_id', $request->input('country_id'))
            ->where('requires_local_cert', true)
            ->first();

        abort_unless($requirement !== null, 403);

        $cert = DB::transaction(function () use ($request, $vendorId) {
            $path = $request->file('file')->store(
                'product-certs/'.$vendorId,
                'local'
            );

            return VendorProductCertification::updateOrCreate(
                [
                    'vendor_id'  => $vendorId,
                    'product_id' => $request->input('product_id'),
                    'country_id' => $request->input('country_id'),
                ],
                [
                    'file_path'             => $path,
                    'original_filename'     => $request->file('file')->getClientOriginalName(),
                    'cert_name'             => $request->input('cert_name'),
                    'status'                => 'pending',
                    'rejection_reason'      => null,
                    'reviewed_by_admin_id'  => null,
                    'reviewed_at'           => null,
                    'expires_at'            => null,
                    'deleted_at'            => null,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => __('partner.product_certifications.messages.uploaded'),
            'data'    => ['id' => $cert->id, 'status' => $cert->status],
        ]);
    }

    public function replace(Request $request, string $id): JsonResponse
    {
        $vendorId = $this->vendorId();

        $existing = VendorProductCertification::query()
            ->where('vendor_id', $vendorId)
            ->findOrFail($id);

        $request->validate([
            'cert_name' => ['nullable', 'string', 'max:255'],
            'file'      => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $requirement = ProductCountry::query()
            ->where('product_id', $existing->product_id)
            ->where('country_id', $existing->country_id)
            ->where('requires_local_cert', true)
            ->first();

        abort_unless($requirement !== null, 403);

        $cert = DB::transaction(function () use ($request, $vendorId, $existing) {
            $path = $request->file('file')->store(
                'product-certs/'.$vendorId,
                'local'
            );

            return VendorProductCertification::updateOrCreate(
                [
                    'vendor_id'  => $vendorId,
                    'product_id' => $existing->product_id,
                    'country_id' => $existing->country_id,
                ],
                [
                    'file_path'             => $path,
                    'original_filename'     => $request->file('file')->getClientOriginalName(),
                    'cert_name'             => $request->input('cert_name'),
                    'status'                => 'pending',
                    'rejection_reason'      => null,
                    'reviewed_by_admin_id'  => null,
                    'reviewed_at'           => null,
                    'expires_at'            => null,
                    'deleted_at'            => null,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => __('partner.product_certifications.messages.uploaded'),
            'data'    => ['id' => $cert->id, 'status' => $cert->status],
        ]);
    }
}
