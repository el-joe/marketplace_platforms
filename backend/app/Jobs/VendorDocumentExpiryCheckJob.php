<?php

namespace App\Jobs;

use App\Enums\VendorGlobalStatus;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class VendorDocumentExpiryCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Document types whose expiry flags the vendor for under_review status. */
    private const CRITICAL_TYPES = ['business_license', 'tax_certificate', 'vat_registration'];

    public function handle(): void
    {
        $this->notifyExpiringSoon();
        $this->expireOverdueDocuments();
    }

    private function notifyExpiringSoon(): void
    {
        VendorDocument::whereIn('status', ['approved', 'verified'])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->with('vendor')
            ->chunk(100, function ($documents) {
                foreach ($documents as $doc) {
                    $vendor = $doc->vendor;
                    if (!$vendor) {
                        continue;
                    }

                    $daysLeft = now()->diffInDays($doc->expires_at, false);
                    $msg = sprintf(
                        'Your %s document will expire in %d day(s) on %s. Please upload a renewed document.',
                        str_replace('_', ' ', $doc->document_type),
                        $daysLeft,
                        $doc->expires_at->format('d M Y')
                    );

                    // Notify vendor
                    DB::table('notifications')->insert([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'type' => 'App\\Notifications\\Vendor\\DocumentExpiringSoon',
                        'notifiable_type' => Vendor::class,
                        'notifiable_id' => $vendor->id,
                        'data' => json_encode([
                            'message' => $msg,
                            'document_type' => $doc->document_type,
                            'expires_at' => $doc->expires_at->toDateString(),
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Notify all admins
                    DB::table('admins')->pluck('id')->each(function ($adminId) use ($vendor, $doc, $daysLeft) {
                        DB::table('notifications')->insert([
                            'id' => \Illuminate\Support\Str::uuid(),
                            'type' => 'App\\Notifications\\Admin\\VendorDocumentExpiringSoon',
                            'notifiable_type' => \App\Models\Admin::class,
                            'notifiable_id' => $adminId,
                            'data' => json_encode([
                                'message' => "Vendor {$vendor->store_name} has a document ({$doc->document_type}) expiring in {$daysLeft} day(s).",
                                'vendor_id' => $vendor->id,
                                'document_type' => $doc->document_type,
                                'expires_at' => $doc->expires_at->toDateString(),
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    });
                }
            });
    }

    private function expireOverdueDocuments(): void
    {
        VendorDocument::whereIn('status', ['approved', 'verified'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('vendor')
            ->chunk(100, function ($documents) {
                foreach ($documents as $doc) {
                    $doc->update(['status' => 'expired']);

                    if (in_array($doc->document_type, self::CRITICAL_TYPES)) {
                        $vendor = $doc->vendor;
                        if ($vendor && $vendor->global_status === VendorGlobalStatus::Active) {
                            $vendor->update(['global_status' => 'under_review']);

                            DB::table('activity_log')->insert([
                                'log_name' => 'vendor',
                                'description' => 'Vendor flagged for review due to expired critical document',
                                'subject_type' => Vendor::class,
                                'subject_id' => $vendor->id,
                                'causer_type' => null,
                                'causer_id' => null,
                                'properties' => json_encode([
                                    'event' => 'document_expired',
                                    'document_type' => $doc->document_type,
                                ]),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            });
    }
}
