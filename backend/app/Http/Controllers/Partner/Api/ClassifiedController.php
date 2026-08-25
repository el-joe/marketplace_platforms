<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClassifiedListing;
use App\Models\ClassifiedInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassifiedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $listings = ClassifiedListing::where('vendor_id', $vendorId)
            ->with(['category', 'images'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate(20);

        return ApiResponse::success([
            'data' => $listings->map(fn ($l) => [
                'id'              => $l->id,
                'title'           => $l->title,
                'status'          => $l->status,
                'price'           => $l->price,
                'currency'        => $l->currency,
                'listing_purpose' => $l->listing_purpose,
                'category'        => $l->category?->name,
                'views_count'     => $l->views_count,
                'inquiries_count' => $l->inquiries_count,
                'created_at'      => $l->created_at?->toIso8601String(),
                'thumbnail'       => $l->images->first()?->image_url,
            ]),
            'meta' => ['current_page' => $listings->currentPage(), 'last_page' => $listings->lastPage(), 'total' => $listings->total()],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $listing = ClassifiedListing::where('id', $id)
            ->where('vendor_id', $vendorId)
            ->with(['category', 'images', 'attachments'])
            ->firstOrFail();

        return ApiResponse::success([
            'id'              => $listing->id,
            'title'           => $listing->title,
            'description'     => $listing->description,
            'status'          => $listing->status,
            'price'           => $listing->price,
            'currency'        => $listing->currency,
            'listing_purpose' => $listing->listing_purpose,
            'seller_type'     => $listing->seller_type,
            'category'        => ['id' => $listing->category?->id, 'name' => $listing->category?->name],
            'images'          => $listing->images->map(fn ($img) => ['url' => $img->image_url, 'order' => $img->sort_order]),
            'views_count'     => $listing->views_count,
            'inquiries_count' => $listing->inquiries_count,
            'created_at'      => $listing->created_at?->toIso8601String(),
        ]);
    }

    public function inquiries(string $id): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $listing = ClassifiedListing::where('id', $id)->where('vendor_id', $vendorId)->firstOrFail();

        $inquiries = ClassifiedInquiry::where('classified_listing_id', $listing->id)
            ->with(['customer:id,first_name,last_name'])
            ->latest()->paginate(20);

        return ApiResponse::success([
            'data' => $inquiries->map(fn ($i) => [
                'id'         => $i->id,
                'message'    => $i->message,
                'status'     => $i->status,
                'customer'   => $i->customer ? $i->customer->first_name . ' ' . $i->customer->last_name : null,
                'created_at' => $i->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $inquiries->currentPage(), 'last_page' => $inquiries->lastPage()],
        ]);
    }
}
