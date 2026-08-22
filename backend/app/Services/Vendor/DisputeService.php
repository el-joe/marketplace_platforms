<?php

namespace App\Services\Vendor;

use App\Enums\DisputeMessageSenderRole;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\VendorAdmin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DisputeService
{
    public function addMessage(Dispute $dispute, VendorAdmin $admin, string $message, ?UploadedFile $attachment = null): DisputeMessage
    {
        $attachmentData = null;

        if ($attachment) {
            $path = $attachment->store("disputes/{$dispute->id}/messages", 'private');
            $attachmentData = [
                'path' => $path,
                'name' => $attachment->getClientOriginalName(),
                'mime' => $attachment->getMimeType(),
                'size' => $attachment->getSize(),
            ];
        }

        $msg = DisputeMessage::create([
            'dispute_id'      => $dispute->id,
            'sender_user_id'  => null, // VendorAdmin is not in the users table
            'sender_role'     => DisputeMessageSenderRole::Seller,
            'message'         => $message,
            'is_internal_note' => false,
        ]);

        if ($attachmentData) {
            // Store attachment reference alongside the message if the table supports it,
            // otherwise persist to dispute_evidences as a message attachment.
            $dispute->evidence()->create([
                'dispute_message_id' => $msg->id,
                'file_path'          => $attachmentData['path'],
                'file_name'          => $attachmentData['name'],
                'mime_type'          => $attachmentData['mime'],
                'file_size'          => $attachmentData['size'],
                'uploaded_by_role'   => 'seller',
                'description'        => null,
            ]);
        }

        // Advance dispute status so customer / admin know vendor responded
        if ($dispute->status === \App\Enums\DisputeStatus::Open) {
            $dispute->update(['status' => 'seller_responded']);
        }

        return $msg;
    }

    public function addEvidence(Dispute $dispute, VendorAdmin $admin, UploadedFile $file, string $description): void
    {
        $path = $file->store("disputes/{$dispute->id}/evidence", 'private');

        $dispute->evidence()->create([
            'dispute_message_id' => null,
            'file_path'          => $path,
            'file_name'          => $file->getClientOriginalName(),
            'mime_type'          => $file->getMimeType(),
            'file_size'          => $file->getSize(),
            'uploaded_by_role'   => 'seller',
            'description'        => $description,
        ]);
    }
}
