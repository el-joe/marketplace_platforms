<?php

namespace App\Services\Vendor;

use App\Enums\WarehouseType;
use App\Models\Address;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnboardingService
{
    private const STEPS = [
        'store_identity',
        'business_details',
        'documents',
        'bank_account',
        'shipping_settings',
    ];

    public function getStatus(Vendor $vendor): array
    {
        $docs = $vendor->documents()->whereIn('status', ['pending', 'approved', 'verified'])->exists();

        $completed = [
            'store_identity'     => filled($vendor->store_name) && filled($vendor->store_slug),
            'business_details'   => filled($vendor->business_name) && filled($vendor->tax_id),
            'documents'          => $docs,
            'bank_account'       => $vendor->bankAccounts()->exists(),
            'shipping_settings'  => true, // always satisfiable (FBN-only vendors skip warehouse)
        ];

        $allDone = !in_array(false, $completed, true);

        return [
            'steps'              => $completed,
            'all_complete'       => $allDone,
            'onboarding_done_at' => $vendor->onboarding_completed_at?->toISOString(),
        ];
    }

    public function saveStoreIdentity(Vendor $vendor, array $data): void
    {
        $fields = [
            'store_name'        => $data['store_name'],
            'store_slug'        => $data['store_slug'],
            'store_description' => $data['store_description'] ?? null,
            'contact_email'     => $data['contact_email'] ?? null,
            'contact_phone'     => $data['contact_phone'] ?? null,
            'whatsapp_number'   => $data['whatsapp_number'] ?? null,
        ];

        if (!empty($data['store_logo'])) {
            $fields['avatar'] = $data['store_logo']->store('vendors/logos', 'public');
        }

        $vendor->update($fields);
    }

    public function saveBusinessDetails(Vendor $vendor, array $data): void
    {
        $vendor->update([
            'business_name'                => $data['business_name'],
            'business_type'                => $data['business_type'],
            'business_registration_number' => $data['business_registration_number'] ?? null,
            'tax_id'                       => $data['tax_id'],
        ]);
    }

    public function saveShippingSettings(Vendor $vendor, array $data): void
    {
        // If a warehouse address was supplied, create/update a vendor-owned warehouse.
        if (!empty($data['warehouse_address'])) {
            $addr = $data['warehouse_address'];

            $address = Address::create([
                'addressable_type' => Vendor::class,
                'addressable_id'   => $vendor->id,
                'label'            => $addr['label'] ?? 'Warehouse',
                'address_line_1'   => $addr['address_line_1'],
                'address_line_2'   => $addr['address_line_2'] ?? null,
                'city'             => $addr['city'] ?? null,
                'state'            => $addr['state'] ?? null,
                'country_id'       => $vendor->country_id,
                'postal_code'      => $addr['postal_code'] ?? null,
                'phone'            => $addr['phone'] ?? null,
            ]);

            $warehouse = $vendor->warehouses()->where('type', WarehouseType::SellerOwned->value)->first();

            if ($warehouse) {
                $warehouse->update(['address_id' => $address->id, 'is_active' => true]);
            } else {
                Warehouse::create([
                    'country_id'      => $vendor->country_id,
                    'name'            => ($vendor->store_name ?? 'Vendor') . ' Warehouse',
                    'code'            => strtoupper(Str::random(6)),
                    'type'            => WarehouseType::SellerOwned->value,
                    'owner_vendor_id' => $vendor->id,
                    'address_id'      => $address->id,
                    'storage_currency' => 'USD',
                    'is_active'       => true,
                ]);
            }
        }
    }

    public function markComplete(Vendor $vendor): void
    {
        $status = $this->getStatus($vendor);

        if (!$status['all_complete']) {
            throw new \RuntimeException(__('common.exceptions.onboarding.incomplete'));
        }

        $vendor->update(['onboarding_completed_at' => now()]);
    }
}
