<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Models\VendorChangeRequest;
use App\Notifications\Admin\VendorChangeRequestSubmitted;
use App\Notifications\Vendor\ChangeRequestApproved;
use App\Notifications\Vendor\ChangeRequestRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class VendorChangeRequestService
{
    public function __construct(private readonly ActivityLoggerService $activityLogger) {}

    public function submitRequest(
        Vendor $vendor,
        VendorAdmin $requestedBy,
        string $section,
        string $requestType,
        array $currentData,
        array $requestedData,
        ?string $vendorNote
    ): VendorChangeRequest {
        $hasPending = VendorChangeRequest::query()
            ->forVendor($vendor->id)
            ->forSection($section)
            ->pending()
            ->exists();

        if ($hasPending) {
            throw new \RuntimeException(__('common.exceptions.change_request.pending_exists'));
        }

        $request = VendorChangeRequest::query()->create([
            'vendor_id' => $vendor->id,
            'requested_by_vendor_admin_id' => $requestedBy->id,
            'section' => $section,
            'request_type' => $requestType,
            'current_data' => $currentData,
            'requested_data' => $requestedData,
            'vendor_note' => $vendorNote,
            'status' => 'pending',
        ]);

        Notification::send(Admin::permission('vendor_change_requests.view')->get(), new VendorChangeRequestSubmitted($request));

        $this->activityLogger->log(
            description: "Change request submitted: {$section}",
            subject: $vendor,
            causer: $requestedBy,
            properties: ['section' => $section, 'request_type' => $requestType],
            logName: 'vendor_profile',
            event: 'change_request_submitted',
        );

        return $request;
    }

    public function approveRequest(VendorChangeRequest $request, Admin $admin, ?string $note): void
    {
        DB::transaction(function () use ($request, $admin, $note) {
            $request->update([
                'status' => 'approved',
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            $this->applyChanges($request);

            $request->update(['applied_at' => now()]);
        });

        $this->activityLogger->log(
            description: "Change request approved: {$request->section}",
            subject: $request->vendor,
            causer: $admin,
            properties: ['section' => $request->section],
            logName: 'vendor_profile',
            event: 'change_request_approved',
        );

        Notification::send($request->vendor->vendorAdmins, new ChangeRequestApproved($request));
    }

    public function rejectRequest(VendorChangeRequest $request, Admin $admin, string $note): void
    {
        DB::transaction(function () use ($request, $admin, $note) {
            $request->update([
                'status' => 'rejected',
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);
        });

        $this->activityLogger->log(
            description: "Change request rejected: {$request->section}",
            subject: $request->vendor,
            causer: $admin,
            properties: ['section' => $request->section, 'reason' => $note],
            logName: 'vendor_profile',
            event: 'change_request_rejected',
        );

        Notification::send($request->vendor->vendorAdmins, new ChangeRequestRejected($request, $note));
    }

    public function cancelRequest(VendorChangeRequest $request, VendorAdmin $cancelledBy): void
    {
        if (! $request->isPending() || $request->vendor_id !== $cancelledBy->vendor_id) {
            throw new \RuntimeException(__('common.exceptions.change_request.cannot_be_cancelled'));
        }

        $request->update(['status' => 'cancelled']);
    }

    private function applyChanges(VendorChangeRequest $request): void
    {
        $data = $request->requested_data;

        match ($request->section) {
            'store_profile' => $request->vendor->update(array_intersect_key(
                $data,
                array_flip(['store_name', 'store_description', 'store_slug'])
            )),
            'business_info' => $request->vendor->update(array_intersect_key(
                $data,
                array_flip(['business_name', 'business_type', 'business_registration_number', 'tax_id'])
            )),
            'contact_info' => $request->vendor->update(array_intersect_key(
                $data,
                array_flip(['contact_email', 'contact_phone', 'whatsapp_number'])
            )),
            'documents' => null,
            'bank_accounts' => $this->applyBankAccountChange($request, $data),
            default => throw new \RuntimeException("Unknown section: {$request->section}"),
        };
    }

    private function applyBankAccountChange(VendorChangeRequest $request, array $data): void
    {
        match ($request->request_type) {
            'add' => VendorBankAccount::query()->create(array_merge(
                array_intersect_key($data, array_flip([
                    'account_holder_name', 'bank_name', 'branch', 'iban',
                    'account_number_encrypted', 'swift_code', 'currency', 'is_primary',
                ])),
                ['vendor_id' => $request->vendor_id]
            )),
            'edit' => VendorBankAccount::query()
                ->where('vendor_id', $request->vendor_id)
                ->findOrFail($data['id'])
                ->update(array_intersect_key($data, array_flip([
                    'account_holder_name', 'bank_name', 'branch', 'iban',
                    'account_number_encrypted', 'swift_code', 'currency', 'is_primary',
                ]))),
            'delete' => VendorBankAccount::query()
                ->where('vendor_id', $request->vendor_id)
                ->findOrFail($data['id'])
                ->delete(),
            default => throw new \RuntimeException("Unknown request type: {$request->request_type}"),
        };
    }
}
