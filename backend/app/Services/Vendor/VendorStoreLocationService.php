<?php

namespace App\Services\Vendor;

use App\Enums\AddressType;
use App\Models\Address;
use App\Models\Vendor;
use App\Services\ActivityLoggerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Vendor store location = the vendor's business address (vendors.business_address_id → addresses), whose
 * latitude/longitude are the last-resort origin for distance-based delivery pricing (client item #19).
 */
class VendorStoreLocationService
{
    public function __construct(private readonly ActivityLoggerService $logger) {}

    /**
     * @return array{store_address: ?string, latitude: ?float, longitude: ?float, has_coordinates: bool}
     */
    public function present(Vendor $vendor): array
    {
        $address = $vendor->businessAddress;
        $latitude = $address?->latitude;
        $longitude = $address?->longitude;

        return [
            'store_address' => $address?->street_address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'has_coordinates' => $latitude !== null && $longitude !== null && ! ($latitude == 0 && $longitude == 0),
        ];
    }

    /**
     * Create or update the business address. Coordinates are both-or-none (validated upstream); null clears them.
     *
     * @param  array{store_address?: ?string, latitude?: mixed, longitude?: mixed}  $data
     * @return array{store_address: ?string, latitude: ?float, longitude: ?float, has_coordinates: bool}
     */
    public function update(Vendor $vendor, array $data, Model $causer): array
    {
        $before = $this->present($vendor);

        DB::transaction(function () use ($vendor, $data): void {
            $address = $vendor->businessAddress ?? new Address([
                'addressable_type' => Vendor::class,
                'addressable_id' => $vendor->id,
                'country_id' => $vendor->country_id,
                'address_type' => AddressType::Both,
                'is_default' => false,
            ]);

            $hasCoordinates = ($data['latitude'] ?? null) !== null && ($data['longitude'] ?? null) !== null;

            $address->fill([
                'street_address' => array_key_exists('store_address', $data) ? $data['store_address'] : $address->street_address,
                'latitude' => $hasCoordinates ? round((float) $data['latitude'], 7) : null,
                'longitude' => $hasCoordinates ? round((float) $data['longitude'], 7) : null,
            ])->save();

            if ((string) $vendor->business_address_id !== (string) $address->id) {
                $vendor->forceFill(['business_address_id' => $address->id])->save();
            }

            $vendor->setRelation('businessAddress', $address);
        });

        $after = $this->present($vendor);

        if ($before !== $after) {
            $this->logger->log(
                description: 'store_location_updated',
                subject: $vendor,
                causer: $causer,
                properties: ['old' => $before, 'attributes' => $after],
                logName: 'vendor',
                event: 'store_location_updated',
            );
        }

        return $after;
    }
}
