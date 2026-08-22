<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\CartCardOffer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CartCardOfferService
{
    public function create(array $data, Admin $admin, ?UploadedFile $image): CartCardOffer
    {
        $data = $this->normalize($data);
        $data['created_by_admin_id'] = $admin->id;

        if ($image) {
            $data['card_image_path'] = $image->store('card-offers', 'public');
        }

        $offer = DB::transaction(fn () => CartCardOffer::query()->create(array_merge(
            ['id' => Str::uuid()->toString()],
            $data
        )));

        $this->bustCache($offer->country_id);

        return $offer;
    }

    public function update(CartCardOffer $offer, array $data, ?UploadedFile $image): CartCardOffer
    {
        $data = $this->normalize($data);

        if ($image) {
            if ($offer->card_image_path) {
                Storage::disk('public')->delete($offer->card_image_path);
            }
            $data['card_image_path'] = $image->store('card-offers', 'public');
        }

        $oldCountryId = $offer->country_id;

        DB::transaction(fn () => $offer->update($data));

        $this->bustCache($oldCountryId);
        $this->bustCache($offer->country_id);

        return $offer->refresh();
    }

    public function delete(CartCardOffer $offer): void
    {
        if ($offer->card_image_path) {
            Storage::disk('public')->delete($offer->card_image_path);
        }

        $countryId = $offer->country_id;

        $offer->delete();

        $this->bustCache($countryId);
    }

    public function bustCache(string $countryId): void
    {
        Cache::forget("benefits:card_offers:{$countryId}");
    }

    private function normalize(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['cashback_pct'] = $data['cashback_type'] === 'percentage' ? ($data['cashback_pct'] ?? 0) : 0;
        $data['cashback_fixed_amount'] = $data['cashback_type'] === 'fixed' ? ($data['cashback_fixed_amount'] ?? 0) : 0;
        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
