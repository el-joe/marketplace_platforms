<?php

namespace App\Services\Carrier;

use App\Models\City;
use App\Models\Country;
use App\Models\ShippingCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyService
{
    /**
     * Update only the self-serviceable contact fields.
     * Legal identity (name, legal_name, country_id) requires admin involvement.
     */
    public function updateContact(ShippingCompany $company, array $data, ?UploadedFile $logo): ShippingCompany
    {
        $payload = [
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'] ?? $company->contact_phone,
        ];

        if ($logo) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $payload['logo_path'] = $logo->store('carrier/logos', 'public');
        }

        $company->update($payload);

        return $company->fresh();
    }

    /**
     * Replace served_countries and served_cities.
     *
     * // VERIFY: This is currently a direct self-service write.
     * The schema has no explicit permission for service-area edits, and these
     * changes affect fallback routing / SLA commitments platform-wide.
     * Consider gating behind an admin-approval workflow before going to production.
     */
    public function updateServedAreas(ShippingCompany $company, array $countryIds, array $cityIds): ShippingCompany
    {
        $company->update([
            'served_countries' => array_values(array_unique($countryIds)),
            'served_cities'    => array_values(array_unique($cityIds)),
        ]);

        return $company->fresh();
    }

    /**
     * Resolve served_countries and served_cities JSON arrays to named objects.
     */
    public function resolveServedAreas(ShippingCompany $company): array
    {
        $countryIds = $company->served_countries ?? [];
        $cityIds    = $company->served_cities    ?? [];

        $countries = Country::whereIn('id', $countryIds)
            ->get(['id', 'name_en', 'name_ar'])
            ->keyBy('id');

        $cities = City::whereIn('id', $cityIds)
            ->get(['id', 'name_en', 'name_ar', 'country_id'])
            ->keyBy('id');

        return [
            'countries' => $countries->map(fn ($c) => [
                'id'      => $c->id,
                'name_en' => $c->name_en,
                'name_ar' => $c->name_ar,
            ])->values(),
            'cities' => $cities->map(fn ($c) => [
                'id'         => $c->id,
                'name_en'    => $c->name_en,
                'name_ar'    => $c->name_ar,
                'country_id' => $c->country_id,
            ])->values(),
        ];
    }
}
