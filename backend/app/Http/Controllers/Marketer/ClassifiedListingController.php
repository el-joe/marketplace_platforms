<?php

namespace App\Http\Controllers\Marketer;

use App\Enums\ClassifiedInquiryStatus;
use App\Enums\ClassifiedListingStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\ClassifiedListingImage;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerListing;
use App\Models\OpenMarketListingPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Marketer-owned open-market (classified) listings. Each listing also gets a
 * marketer_listings row (listing_category='classified') for referral tracking.
 */
class ClassifiedListingController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    private function owned(ClassifiedListing $listing): ClassifiedListing
    {
        abort_unless(
            $listing->seller_type === Marketer::class && $listing->seller_id === $this->marketer()->id,
            404
        );

        return $listing;
    }

    public function index(Request $request): View
    {
        $marketer = $this->marketer();

        $listings = ClassifiedListing::query()
            ->where('seller_type', Marketer::class)
            ->where('seller_id', $marketer->id)
            ->with(['classifiedCategory', 'images'])
            ->withCount([
                'inquiries',
                'inquiries as pending_inquiries_count' => fn ($q) => $q->where('status', ClassifiedInquiryStatus::New->value),
            ])
            ->latest()
            ->paginate(20);

        return view('marketer.classified-listings.index', compact('listings'));
    }

    public function create(): View
    {
        return view('marketer.classified-listings.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $marketer = $this->marketer();
        [$data, $price] = $this->validated($request);

        $country = Country::findOrFail($data['country_id']);

        $listing = DB::transaction(function () use ($request, $marketer, $data, $price, $country) {
            $listing = ClassifiedListing::create([
                'listing_number'         => 'CL-' . strtoupper(Str::random(8)),
                'seller_type'            => Marketer::class,
                'seller_id'              => $marketer->id,
                'classified_category_id' => $data['classified_category_id'],
                'country_id'             => $data['country_id'],
                'listing_purpose'        => $data['listing_purpose'],
                'title_ar'               => $data['title_ar'],
                'title_en'               => $data['title_en'] ?? $data['title_ar'],
                'description_ar'         => $data['description_ar'] ?? null,
                'description_en'         => $data['description_en'] ?? null,
                'price'                  => $price,
                'currency'               => $country->currency_code,
                'price_negotiable'       => $request->boolean('price_negotiable'),
                'attributes'             => $data['attributes'] ?? null,
                'status'                 => ClassifiedListingStatus::PendingReview,
            ]);

            MarketerListing::create([
                'marketer_id'           => $marketer->id,
                'listing_category'      => 'classified',
                'classified_listing_id' => $listing->id,
                'country_id'            => $data['country_id'],
                'price'                 => $price,
                'currency'              => $country->currency_code,
                'status'                => 'active',
                'referral_code'         => 'CLM-' . strtoupper(Str::random(10)),
            ]);

            $this->storeImages($request, $listing);

            return $listing;
        });

        return redirect()->route('marketer.classified-listings.show', $listing)
            ->with('success', __('marketer.classified_created'));
    }

    public function show(ClassifiedListing $listing): View
    {
        $this->owned($listing);
        $listing->load(['classifiedCategory', 'images', 'country', 'inquiries.customer']);

        return view('marketer.classified-listings.show', compact('listing'));
    }

    public function edit(ClassifiedListing $listing): View
    {
        $this->owned($listing);
        $listing->load('images');

        return view('marketer.classified-listings.create', $this->formData() + ['listing' => $listing]);
    }

    public function update(Request $request, ClassifiedListing $listing): RedirectResponse
    {
        $this->owned($listing);
        [$data, $price] = $this->validated($request);
        $country = Country::findOrFail($data['country_id']);

        DB::transaction(function () use ($request, $listing, $data, $price, $country) {
            $listing->update([
                'classified_category_id' => $data['classified_category_id'],
                'country_id'             => $data['country_id'],
                'listing_purpose'        => $data['listing_purpose'],
                'title_ar'               => $data['title_ar'],
                'title_en'               => $data['title_en'] ?? $data['title_ar'],
                'description_ar'         => $data['description_ar'] ?? null,
                'description_en'         => $data['description_en'] ?? null,
                'price'                  => $price,
                'currency'               => $country->currency_code,
                'price_negotiable'       => $request->boolean('price_negotiable'),
                'attributes'             => $data['attributes'] ?? null,
            ]);

            MarketerListing::where('classified_listing_id', $listing->id)->get()->each->update([
                'price' => $price, 'currency' => $country->currency_code, 'country_id' => $data['country_id'],
            ]);

            $this->storeImages($request, $listing);
        });

        return redirect()->route('marketer.classified-listings.show', $listing)
            ->with('success', __('marketer.classified_updated'));
    }

    /**
     * The classified_listings status enum has no 'archived' value, so the
     * spec's "archive" is implemented as a soft delete (model uses SoftDeletes)
     * and the linked marketer_listing is set to 'archived' and soft-deleted.
     */
    public function destroy(ClassifiedListing $listing): RedirectResponse
    {
        $this->owned($listing);

        DB::transaction(function () use ($listing) {
            MarketerListing::where('classified_listing_id', $listing->id)->get()->each(function ($ml) {
                $ml->update(['status' => 'archived']);
                $ml->delete();
            });
            $listing->delete();
        });

        return redirect()->route('marketer.classified-listings.index')
            ->with('success', __('marketer.classified_deleted'));
    }

    public function toggleStatus(ClassifiedListing $listing): RedirectResponse
    {
        $this->owned($listing);

        $new = match ($listing->status) {
            ClassifiedListingStatus::Active => ClassifiedListingStatus::Paused,
            ClassifiedListingStatus::Paused => ClassifiedListingStatus::Active,
            default => null,
        };

        if (! $new) {
            return back()->withErrors(['status' => __('marketer.classified_cannot_toggle')]);
        }

        $listing->update(['status' => $new]);
        MarketerListing::where('classified_listing_id', $listing->id)
            ->update(['status' => $new === ClassifiedListingStatus::Active ? 'active' : 'paused']);

        return back()->with('success', __('marketer.classified_updated'));
    }

    private function formData(): array
    {
        return [
            'categories'      => ClassifiedCategory::whereNull('parent_id')->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->get(),
            'countries'       => Country::where('is_active', true)->get(),
            'openMarketRules' => OpenMarketListingPrice::all()->keyBy('classified_category_id'),
        ];
    }

    /** @return array{0: array, 1: int} validated data and the effective price */
    private function validated(Request $request): array
    {
        $rules = [
            'classified_category_id' => ['required', 'uuid', 'exists:classified_categories,id'],
            'country_id'             => ['required', 'uuid', 'exists:countries,id'],
            'title_ar'               => ['required', 'string', 'max:255'],
            'title_en'               => ['nullable', 'string', 'max:255'],
            'description_ar'         => ['nullable', 'string'],
            'description_en'         => ['nullable', 'string'],
            'listing_purpose'        => ['required', 'in:sale,rent'],
            'price_negotiable'       => ['nullable', 'boolean'],
            'attributes'             => ['nullable', 'array'],
            'attributes.*'           => ['nullable'],
            'images'                 => ['nullable', 'array', 'max:10'],
            'images.*'               => ['image', 'max:5120'],
            'price'                  => ['nullable', 'integer'],
        ];

        $rule = OpenMarketListingPrice::where('classified_category_id', $request->input('classified_category_id'))->first();
        $forced = null;

        if ($rule && ! $rule->allow_marketer_override) {
            $forced = (int) $rule->base_price;
        } else {
            $rules['price'] = ['required', 'integer', 'min:' . max(0, (int) ($rule?->min_price ?? 0))];
            if ($rule?->max_price) {
                $rules['price'][] = 'max:' . (int) $rule->max_price;
            }
        }

        $data = $request->validate($rules);

        return [$data, $forced ?? (int) $data['price']];
    }

    private function storeImages(Request $request, ClassifiedListing $listing): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $position = (int) $listing->images()->max('position');
        $hasPrimary = $listing->images()->where('is_primary', true)->exists();

        foreach ($request->file('images') as $file) {
            $path = $file->store("classified-listings/{$listing->id}", 'public');
            ClassifiedListingImage::create([
                'classified_listing_id' => $listing->id,
                'file_path'             => $path,
                'position'              => ++$position,
                'is_primary'            => ! $hasPrimary,
            ]);
            $hasPrimary = true;
        }
    }
}
