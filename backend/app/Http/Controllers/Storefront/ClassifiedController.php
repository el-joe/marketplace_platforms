<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Services\ClassifiedListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ClassifiedController extends Controller
{
    public function __construct(private ClassifiedListingService $service) {}

    public function index(Request $request, string $country): View
    {
        $countryModel = Country::where('iso_code_2', strtoupper($country))
            ->orWhere('slug', $country)
            ->firstOrFail();

        $query = ClassifiedListing::with(['classifiedCategory', 'images', 'city'])
            ->where('status', 'active')
            ->where('country_id', $countryModel->id)
            ->latest();

        if ($request->filled('category')) {
            $cat = ClassifiedCategory::where('slug', $request->category)->first();
            if ($cat) {
                $query->where('classified_category_id', $cat->id);
            }
        }

        if ($request->filled('purpose')) {
            $query->where('listing_purpose', $request->purpose);
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('title_en', 'like', "%{$term}%")
                  ->orWhere('title_ar', 'like', "%{$term}%");
            });
        }

        $listings   = $query->paginate(12)->withQueryString();
        $categories = ClassifiedCategory::where('is_active', true)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return view('storefront.classifieds.index', compact(
            'listings', 'categories', 'countryModel'
        ));
    }

    public function mapData(Request $request, string $country): JsonResponse
    {
        $countryModel = Country::where('iso_code_2', strtoupper($country))
            ->orWhere('slug', $country)
            ->firstOrFail();

        $listings = ClassifiedListing::select([
                'id', 'listing_number', 'title_en', 'title_ar',
                'price', 'currency', 'latitude', 'longitude',
                'listing_purpose',
            ])
            ->where('status', 'active')
            ->where('country_id', $countryModel->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->filled('bounds'), function ($q) use ($request) {
                $b = $request->bounds;
                $q->whereBetween('latitude', [$b['south'], $b['north']])
                  ->whereBetween('longitude', [$b['west'], $b['east']]);
            })
            ->limit(500)
            ->get()
            ->map(fn ($l) => [
                'id'      => $l->id,
                'number'  => $l->listing_number,
                'title'   => app()->getLocale() === 'ar' ? $l->title_ar : $l->title_en,
                'price'   => number_format($l->price, 0) . ' ' . $l->currency,
                'purpose' => $l->listing_purpose,
                'lat'     => (float) $l->latitude,
                'lng'     => (float) $l->longitude,
                'url'     => route('classifieds.show', [$country, $l->listing_number]),
            ]);

        return response()->json($listings);
    }

    public function show(string $country, string $listingNumber): View
    {
        $listing = ClassifiedListing::with([
                'classifiedCategory', 'seller', 'city', 'country',
                'images', 'attachments',
            ])
            ->where('listing_number', $listingNumber)
            ->where('status', 'active')
            ->firstOrFail();

        $listing->increment('views_count');

        $related = ClassifiedListing::with('images')
            ->where('classified_category_id', $listing->classified_category_id)
            ->where('status', 'active')
            ->where('id', '!=', $listing->id)
            ->limit(4)
            ->get();

        return view('storefront.classifieds.show', compact('listing', 'related', 'country'));
    }

    public function create(string $country): View
    {
        $countryModel = Country::where('iso_code_2', strtoupper($country))
            ->orWhere('slug', $country)
            ->firstOrFail();

        $categories = ClassifiedCategory::where('is_active', true)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return view('storefront.classifieds.create', compact(
            'countryModel', 'categories'
        ));
    }

    public function storeDraft(Request $request, string $country): JsonResponse
    {
        $customer = auth('customer')->user();

        $validated = $request->validate([
            'classified_category_id' => 'required|exists:classified_categories,id',
            'listing_purpose'        => 'required|in:sale,rent',
            'title_en'               => 'required|string|max:255',
            'title_ar'               => 'required|string|max:255',
            'description_en'         => 'nullable|string',
            'description_ar'         => 'nullable|string',
            'price'            => 'required|integer|min:0',
            'currency'               => 'required|string|size:3',
            'price_negotiable'       => 'boolean',
            'attributes'             => 'nullable|array',
            'latitude'               => 'nullable|numeric',
            'longitude'              => 'nullable|numeric',
            'expires_at'             => 'nullable|date|after:today',
        ]);

        $countryModel = Country::where('iso_code_2', strtoupper($country))
            ->orWhere('slug', $country)
            ->firstOrFail();

        $validated['country_id'] = $countryModel->id;

        if ($request->hasFile('sketch_file')) {
            $validated['sketch_file_path'] = $request->file('sketch_file')
                ->store('classified-sketches', 'public');
        }

        $listing = $this->service->createDraft($validated, $customer);

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('classified-images', 'public');
                $listing->images()->create([
                    'file_path'  => $path,
                    'position'   => $index,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        // Handle required attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $type => $file) {
                $path = $file->store('classified-attachments', 'public');
                $this->service->uploadAttachment($listing, $type, $path);
            }
        }

        return response()->json([
            'success'    => true,
            'listing_id' => $listing->id,
            'number'     => $listing->listing_number,
            'next'       => 'contract',
        ]);
    }

    public function signContract(Request $request, string $country, ClassifiedListing $listing): JsonResponse
    {
        $customer = auth('customer')->user();
        if ($listing->seller_type !== \App\Models\Customer::class || $listing->seller_id !== $customer->id) {
            abort(403);
        }

        $request->validate([
            'signature_name' => 'required|string|max:150',
        ]);

        $this->service->attachContract(
            $listing,
            $request->signature_name,
            $request->ip()
        );

        return response()->json(['success' => true, 'status' => 'pending_review']);
    }

    public function inquire(Request $request, string $country, ClassifiedListing $listing): JsonResponse
    {
        $request->validate([
            'message'       => 'nullable|string|max:1000',
            'contact_phone' => 'nullable|string|max:30',
        ]);

        $customer = auth('customer')->user();

        $this->service->recordInquiry($listing, $customer, $request->only(
            'message', 'contact_phone'
        ));

        return response()->json(['success' => true]);
    }
}
