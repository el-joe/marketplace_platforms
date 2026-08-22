<?php

namespace App\Services\Vendor;

use App\Enums\DisputeMessageSenderRole;
use App\Enums\ReturnRequestStatus;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestMessage;
use App\Models\VendorAdmin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReturnService
{
    // Statuses at which the decision is final and liability/refund are safe to reveal.
    private const FINAL_STATUSES = [ReturnRequestStatus::Completed, ReturnRequestStatus::Cancelled];

    // Statuses at or beyond which inspection data is available.
    private const INSPECTION_STATUSES = [ReturnRequestStatus::Inspecting, ReturnRequestStatus::Completed, ReturnRequestStatus::Cancelled];

    public function getDetail(ReturnRequest $return, string $vendorId): array
    {
        $isFinal     = in_array($return->status, self::FINAL_STATUSES, true);
        $hasInspection = in_array($return->status, self::INSPECTION_STATUSES, true);

        return [
            'return_number'            => $return->return_number,
            'order_number_masked'      => $return->order ? ('****' . substr($return->order->order_number, -4)) : null,
            'sub_order_number'         => $return->subOrder?->sub_order_number,
            'customer_name'            => $this->maskCustomerName($return->customer),
            'reason'                   => $return->reason?->value,
            'reason_description'       => $return->reason_description,
            'return_type'              => $return->return_type?->value,
            'status'                   => $return->status?->value,
            'pickup_scheduled_at'      => $return->pickup_scheduled_at?->toIso8601String(),
            'received_at_warehouse_at' => $return->received_at_warehouse_at?->toIso8601String(),
            'rejection_reason'         => $return->rejection_reason,
            'created_at'               => $return->created_at->toIso8601String(),

            // Inspection data is meaningless before this stage exists in the pipeline.
            'inspection_result' => $hasInspection ? $return->inspection_result?->value : null,
            'inspection_notes'  => $hasInspection ? $return->inspection_notes  : null,

            // Business rule: liability and refund amount are only revealed once the
            // decision is final (completed/cancelled). Showing liability during
            // 'requested'/'approved' would expose an in-progress admin judgment.
            'liability'           => $isFinal ? $return->liability?->value     : null,
            'refund_amount' => $isFinal ? $return->refund_amount  : null,
        ];
    }

    public function addComment(
        ReturnRequest $return,
        VendorAdmin $actor,
        string $message,
        array $attachments
    ): ReturnRequestMessage {
        $msg = ReturnRequestMessage::create([
            'return_request_id' => $return->id,
            'sender_user_id'    => $actor->id,
            'sender_role'       => DisputeMessageSenderRole::Seller,
            'message'           => $message,
            'is_internal_note'  => false,
            'created_at'        => now(),
        ]);

        foreach ($attachments as $file) {
            /** @var UploadedFile $file */
            $path = $file->store("returns/{$return->id}/messages", 'private');

            $msg->attachments()->create([
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'created_at' => now(),
            ]);
        }

        return $msg->load('attachments');
    }

    private function maskCustomerName(?object $customer): ?string
    {
        if (! $customer) {
            return null;
        }

        $first = $customer->first_name ?? '';
        $last  = $customer->last_name  ?? '';

        return trim($first . ' ' . ($last ? strtoupper(substr($last, 0, 1)) . '.' : ''));
    }
}
