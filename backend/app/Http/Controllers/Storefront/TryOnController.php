<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVirtualTryOnJob;
use App\Models\ProductImage;
use App\Models\VendorListing;
use App\Models\VirtualTryonSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TryOnController extends Controller
{
    public function create(VendorListing $listing): View
    {
        // Resolve the category through the product variant chain
        $category = $listing->productVariant?->product?->category;

        abort_unless($category?->supports_virtual_tryon, 404);

        $productImage = $listing->productVariant?->images()->first()
            ?? $listing->productVariant?->product?->images()->first();

        return view('storefront.tryon.create', compact('listing', 'productImage'));
    }

    public function process(Request $request, VendorListing $listing): JsonResponse
    {
        $category = $listing->productVariant?->product?->category;
        abort_unless($category?->supports_virtual_tryon, 404);

        $request->validate([
            'photo' => ['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $photoPath = $request->file('photo')->store('tryon/uploads', 'private');

        $productImage = $listing->productVariant?->images()->first()
            ?? $listing->productVariant?->product?->images()->first();

        $session = VirtualTryonSession::create([
            'customer_id'         => Auth::guard('customer')->id(),
            'vendor_listing_id'   => $listing->id,
            'customer_photo_path' => $photoPath,
            'status'              => 'queued',
        ]);

        ProcessVirtualTryOnJob::dispatch($session, $productImage?->path ?? '')->onQueue('ai');

        return response()->json([
            'session_id' => $session->id,
            'status'     => 'queued',
        ]);
    }

    public function status(string $sessionId): JsonResponse
    {
        // Allow polling without auth (guest try-on); scope by session id only
        $session = VirtualTryonSession::findOrFail($sessionId);

        return response()->json([
            'status'     => $session->status?->value,
            'result_url' => $session->result_image_path
                ? asset('storage/' . $session->result_image_path)
                : null,
            'error'      => $session->error_message,
        ]);
    }
}
