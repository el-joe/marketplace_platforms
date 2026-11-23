<?php

namespace App\Services;

use App\Jobs\LaunchCountryJob;
use App\Models\Admin;
use App\Models\Country;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CountryService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns an array of error strings that would prevent the country being
     * launched. Empty array means the country is ready to launch.
     */
    public function validateForLaunch(Country $country): array
    {
        $errors = [];

        if (! $country->countryPaymentGateways()->where('is_active', true)->exists()) {
            $errors[] = 'No active payment gateway configured for this country.';
        }

        if (! $country->countryShippingSettings()->where('is_active', true)->exists()) {
            $errors[] = 'No active shipping method configured for this country.';
        }

        if (empty($country->currency_code)) {
            $errors[] = 'No base currency has been assigned.';
        }

        if (empty($country->timezone)) {
            $errors[] = 'No timezone has been set.';
        }

        return $errors;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Launch
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException when validation fails
     */
    public function launch(Country $country, ?string $adminId = null): void
    {
        if ($country->is_launched) {
            return; // idempotent
        }

        $errors = $this->validateForLaunch($country);

        if (! empty($errors)) {
            throw new \RuntimeException(implode(' | ', $errors));
        }

        DB::transaction(function () use ($country, $adminId) {
            $oldStatus = $country->is_launched;

            $country->update([
                'is_launched' => true,
                'is_active' => true,
                'launched_at' => now(),
            ]);

            $this->logActivity(
                country: $country,
                description: 'country_launched',
                event: 'launched',
                adminId: $adminId,
                properties: ['was_launched' => $oldStatus, 'launched_at' => now()->toIso8601String()]
            );

            LaunchCountryJob::dispatch($country->id);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Deactivate / Reactivate
    // ─────────────────────────────────────────────────────────────────────────

    public function deactivate(Country $country, ?string $adminId = null): void
    {
        $country->update(['is_active' => false]);

        $this->logActivity(
            country: $country,
            description: 'country_deactivated',
            event: 'deactivated',
            adminId: $adminId,
            properties: []
        );
    }

    public function reactivate(Country $country, ?string $adminId = null): void
    {
        $country->update(['is_active' => true]);

        $this->logActivity(
            country: $country,
            description: 'country_reactivated',
            event: 'reactivated',
            adminId: $adminId,
            properties: []
        );
    }

    /**
     * Move a launched (live or hidden) country back to Draft: it leaves the
     * storefront and, unless it accepts vendors before launch, every vendor
     * picker. Products, listings and settings are kept so it can be launched
     * again. Returns false (and logs nothing) when it already is a draft.
     */
    public function revertToDraft(Country $country, ?string $adminId = null): bool
    {
        if (! $country->is_launched) {
            return false;
        }

        DB::transaction(function () use ($country, $adminId): void {
            $previous = [
                'was_active' => (bool) $country->is_active,
                'launched_at' => $country->launched_at?->toIso8601String(),
            ];

            $country->update([
                'is_launched' => false,
                'is_active' => true,
                'launched_at' => null,
            ]);

            $this->logActivity(
                country: $country,
                description: 'country_reverted_to_draft',
                event: 'reverted_to_draft',
                adminId: $adminId,
                properties: $previous,
            );
        });

        return true;
    }

    /**
     * Log a change of the "accept vendor registrations / listings before launch" switch.
     */
    public function logVendorAccessChange(Country $country, bool $old, bool $new, ?string $adminId = null): void
    {
        if ($old === $new) {
            return;
        }

        $this->logActivity(
            country: $country,
            description: 'vendor_access_before_launch_changed',
            event: 'updated',
            adminId: $adminId,
            properties: ['old_accepts_vendors_before_launch' => $old, 'new_accepts_vendors_before_launch' => $new],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Deletion guard
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns true if the country can be hard-deleted (no orders placed from it).
     * A country with orders can only be deactivated, not deleted.
     */
    public function canDelete(Country $country): bool
    {
        return ! DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('customers.country_id', $country->id)
            ->whereNull('orders.deleted_at')
            ->exists();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VAT rate change logging
    // ─────────────────────────────────────────────────────────────────────────

    public function logVatRateChange(
        Country $country,
        float $oldRate,
        float $newRate,
        ?string $adminId = null
    ): void {
        if ((float) $oldRate === (float) $newRate) {
            return;
        }

        $this->logActivity(
            country: $country,
            description: 'vat_rate_changed',
            event: 'updated',
            adminId: $adminId,
            properties: ['old_vat_rate' => $oldRate, 'new_vat_rate' => $newRate]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function logActivity(
        Country $country,
        string $description,
        string $event,
        ?string $adminId,
        array $properties
    ): void {
        DB::table('activity_log')->insert([
            'id' => (string) Str::uuid(),
            'log_name' => 'geography',
            'description' => $description,
            'subject_type' => Country::class,
            'subject_id' => $country->id,
            'causer_type' => $adminId ? Admin::class : null,
            'causer_id' => $adminId,
            'properties' => json_encode($properties),
            'event' => $event,
            'created_at' => now(),
        ]);
    }
}
