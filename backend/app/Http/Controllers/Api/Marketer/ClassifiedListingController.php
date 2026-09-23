<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Enums\ClassifiedInquiryStatus;
use App\Enums\ClassifiedListingStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassifiedListing;
use App\Models\ClassifiedListingImage;
use App\Models\Country;
use App\Models\ExclusiveContract;
use App\Models\Marketer;
use App\Models\MarketerListing;
use App\Models\OpenMarketListingPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClassifiedListingController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    private function owned(string $id): ClassifiedListing
    {
        return ClassifiedListing::where('seller_type', Marketer::class)
            ->where('seller_id', $this->marketer()->id)->findOrFail($id);
    }

    private function contractFor(ClassifiedListing $l, Marketer $m): ?array
    {
        $c = ExclusiveContract::active()->where('marketer_id', $m->id)
            ->where(fn ($q) => $q->where('classified_listing_id', $l->id)
                ->orWhere('classified_category_id', $l->classified_category_id))->first();

        return $c ? ['marketer_name' => $m->name ?? null, 'expires_at' => $c->ends_at] : null;
    }

    private function present(ClassifiedListing $l, bool $full = false): array
    {
        $out = [
            'id'             => $l->id,
            'listing_number' => $l->listing_number,
            'title_ar'       => $l->title_ar,
            'price'          => (int) $l->price,
            'currency'       => $l->currency,
            'status'         => $l->status instanceof \BackedEnum ? $l->status->value : $l->status,
            'views_count'    => (int) ($l->views_count ?? 0),
            'category'       => $l->classifiedCategory ? ['id' => $l->classifiedCategory->id, 'name_ar' => $l->classifiedCategory->name_ar] : null,
            'images'         => $l->images->map(fn ($i) => ['id' => $i->id, 'url' => Storage::url($i->file_path), 'is_primary' => (bool) $i->is_primary])->values(),
            'exclusive_contract' => $this->contractFor($l, $this->marketer()),
        ];
        if ($full) {
            $out += [
                'title_en' => $l->title_en, 'description_ar' => $l->description_ar, 'description_en' => $l->description_en,
                'listing_purpose' => $l->listing_purpose, 'price_negotiable' => (bool) $l->price_negotiable,
                'attributes' => $l->attributes, 'country' => $l->country ? ['id' => $l->country->id, 'name_ar' => $l->country->name_ar] : null,
                'classified_inquiries_count' => $l->inquiries()->count(),
            ];
        }

        return $out;
    }

    public function index(Request $request): JsonResponse
    {
        $page = ClassifiedListing::where('seller_type', Marketer::class)->where('seller_id', $this->marketer()->id)
            ->with(['classifiedCategory', 'images'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(20);
        $page->getCollection()->transform(fn ($l) => $this->present($l));

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function show(string $id): JsonResponse
    {
        $l = $this->owned($id)->load(['classifiedCategory', 'images', 'country']);

        return response()->json(['success' => true, 'data' => $this->present($l, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $marketer = $this->marketer();
        [$data, $price] = $this->validated($request);
        $country = Country::findOrFail($data['country_id']);

        $listing = DB::transaction(function () use ($request, $marketer, $data, $price, $country) {
            $listing = ClassifiedListing::create([
                'listing_number' => 'CL-' . strtoupper(Str::random(8)),
                'seller_type' => Marketer::class, 'seller_id' => $marketer->id,
                'classified_category_id' => $data['classified_category_id'], 'country_id' => $data['country_id'],
                'listing_purpose' => $data['listing_purpose'],
                'title_ar' => $data['title_ar'], 'title_en' => $data['title_en'] ?? $data['title_ar'],
                'description_ar' => $data['description_ar'] ?? null, 'description_en' => $data['description_en'] ?? null,
                'price' => $price, 'currency' => $country->currency_code,
                'price_negotiable' => $request->boolean('price_negotiable'),
                'attributes' => $data['attributes'] ?? null,
                'status' => ClassifiedListingStatus::PendingReview,
            ]);
            MarketerListing::create([
                'marketer_id' => $marketer->id, 'listing_category' => 'classified', 'classified_listing_id' => $listing->id,
                'country_id' => $data['country_id'], 'price' => $price, 'currency' => $country->currency_code,
                'status' => 'active', 'referral_code' => 'CLM-' . strtoupper(Str::random(10)),
            ]);
            $this->storeImages($request, $listing);

            return $listing;
        });

        return response()->json(['success' => true, 'data' => $this->present($listing->load(['classifiedCategory', 'images', 'country']), true)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $listing = $this->owned($id);
        [$data, $price] = $this->validated($request);
        $country = Country::findOrFail($data['country_id']);

        DB::transaction(function () use ($request, $listing, $data, $price, $country) {
            $listing->update([
                'classified_category_id' => $data['classified_category_id'], 'country_id' => $data['country_id'],
                'listing_purpose' => $data['listing_purpose'],
                'title_ar' => $data['title_ar'], 'title_en' => $data['title_en'] ?? $data['title_ar'],
                'description_ar' => $data['description_ar'] ?? null, 'description_en' => $data['description_en'] ?? null,
                'price' => $price, 'currency' => $country->currency_code,
                'price_negotiable' => $request->boolean('price_negotiable'),
                'attributes' => $data['attributes'] ?? null,
            ]);
            MarketerListing::where('classified_listing_id', $listing->id)->get()->each->update([
                'price' => $price, 'currency' => $country->currency_code, 'country_id' => $data['country_id'],
            ]);
            $this->storeImages($request, $listing);
        });

        return response()->json(['success' => true, 'data' => $this->present($listing->fresh()->load(['classifiedCategory', 'images', 'country']), true)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $listing = $this->owned($id);
        DB::transaction(function () use ($listing) {
            MarketerListing::where('classified_listing_id', $listing->id)->get()->each(function ($ml) {
                $ml->update(['status' => 'archived']);
                $ml->delete();
            });
            $listing->delete();
        });

        return response()->json(['success' => true, 'data' => ['id' => $id]]);
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $listing = $this->owned($id);
        $new = match ($listing->status) {
            ClassifiedListingStatus::Active => ClassifiedListingStatus::Paused,
            ClassifiedListingStatus::Paused => ClassifiedListingStatus::Active,
            default => null,
        };
        abort_if(! $new, 422, __('marketer.classified_cannot_toggle'));

        $listing->update(['status' => $new]);
        MarketerListing::where('classified_listing_id', $listing->id)
            ->update(['status' => $new === ClassifiedListingStatus::Active ? 'active' : 'paused']);

        return response()->json(['success' => true, 'data' => ['id' => $listing->id, 'status' => $new->value]]);
    }

    private function validated(Request $request): array
    {
        $rules = [
            'classified_category_id' => ['required', 'uuid', 'exists:classified_categories,id'],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'listing_purpose' => ['required', 'in:sale,rent'],
            'price_negotiable' => ['nullable', 'boolean'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:5120'],
            'price' => ['nullable', 'integer'],
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
            ClassifiedListingImage::create([
                'classified_listing_id' => $listing->id,
                'file_path' => $file->store("classified-listings/{$listing->id}", 'public'),
                'position' => ++$position,
                'is_primary' => ! $hasPrimary,
            ]);
            $hasPrimary = true;
        }
    }
}
