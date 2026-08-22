<?php

namespace App\Services\Vendor;

use App\Enums\FlashSaleSubmissionStatus;
use App\Models\FlashSale;
use App\Models\FlashSaleSubmission;
use App\Models\Vendor;
use App\Models\VendorListing;
use Illuminate\Support\Carbon;

class FlashSaleSubmissionService
{
    public function submit(Vendor $vendor, FlashSale $sale, array $data): FlashSaleSubmission
    {
        $this->assertWindowOpen($sale);

        $listing = VendorListing::where('id', $data['vendor_listing_id'])
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $flashPrice    = (int) $data['flash_price'];
        $originalPrice = (int) $listing->price;

        if ($flashPrice >= $originalPrice) {
            abort(422, 'flash_price must be strictly less than the current listing price.');
        }

        $discountPct = round(($originalPrice - $flashPrice) / $originalPrice * 100, 2);

        if ($discountPct < (float) $sale->min_discount_pct) {
            abort(422, "Discount of {$discountPct}% does not meet the minimum required {$sale->min_discount_pct}%.");
        }

        return FlashSaleSubmission::create([
            'flash_sale_id'          => $sale->id,
            'vendor_id'              => $vendor->id,
            'vendor_listing_id'      => $listing->id,
            'status'                 => 'submitted',
            'flash_price'            => $flashPrice,
            'original_price'         => $originalPrice,
            'calculated_discount_pct' => $discountPct,
            'flash_price_currency'   => $listing->currency ?? $data['currency'] ?? 'USD',
            'max_quantity_total'     => (int) $data['quantity_committed'],
            'max_quantity_per_customer' => (int) ($data['max_quantity_per_customer'] ?? 1),
            'vendor_notes'           => $data['vendor_notes'] ?? null,
            'submitted_at'           => now(),
        ]);
    }

    public function update(FlashSale $sale, FlashSaleSubmission $submission, array $data): FlashSaleSubmission
    {
        $this->assertWindowOpen($sale);
        $this->assertEditable($submission);

        $updates = [];

        if (isset($data['flash_price'])) {
            $flashPrice    = (int) $data['flash_price'];
            $originalPrice = (int) $submission->original_price;

            if ($flashPrice >= $originalPrice) {
                abort(422, 'flash_price must be strictly less than the original listing price.');
            }

            $discountPct = round(($originalPrice - $flashPrice) / $originalPrice * 100, 2);

            if ($discountPct < (float) $sale->min_discount_pct) {
                abort(422, "Discount of {$discountPct}% does not meet the minimum required {$sale->min_discount_pct}%.");
            }

            $updates['flash_price']             = $flashPrice;
            $updates['calculated_discount_pct'] = $discountPct;
        }

        if (isset($data['quantity_committed'])) {
            $updates['max_quantity_total'] = (int) $data['quantity_committed'];
        }

        if (array_key_exists('vendor_notes', $data)) {
            $updates['vendor_notes'] = $data['vendor_notes'];
        }

        $submission->update($updates);

        return $submission->fresh();
    }

    public function withdraw(FlashSale $sale, FlashSaleSubmission $submission): void
    {
        $this->assertWindowOpen($sale);
        $this->assertEditable($submission);

        $submission->update(['status' => 'withdrawn']);
    }

    private function assertWindowOpen(FlashSale $sale): void
    {
        $now = now();

        if ($now->lt($sale->submission_opens_at) || $now->gt($sale->submission_closes_at)) {
            abort(422, 'The submission window for this flash sale is not currently open.');
        }
    }

    private function assertEditable(FlashSaleSubmission $submission): void
    {
        if ($submission->status !== FlashSaleSubmissionStatus::Submitted) {
            abort(422, "Submission cannot be modified in its current status '{$submission->status->value}'. Only 'submitted' submissions are editable.");
        }
    }
}
