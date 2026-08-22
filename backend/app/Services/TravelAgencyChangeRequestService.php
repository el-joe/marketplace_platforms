<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\TravelAgency;
use App\Models\TravelAgencyBankAccount;
use App\Models\TravelAgencyChangeRequest;
use App\Models\TravelAgencyMember;
use App\Notifications\Admin\TravelAgencyChangeRequestSubmitted;
use App\Notifications\TravelAgency\ChangeRequestApproved;
use App\Notifications\TravelAgency\ChangeRequestRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TravelAgencyChangeRequestService
{
    public function __construct(private readonly ActivityLoggerService $activityLogger) {}

    public function submitRequest(
        TravelAgency $travelAgency,
        TravelAgencyMember $requestedBy,
        string $section,
        string $requestType,
        array $currentData,
        array $requestedData,
        ?string $agencyNote
    ): TravelAgencyChangeRequest {
        $hasPending = TravelAgencyChangeRequest::query()
            ->forTravelAgency($travelAgency->id)
            ->forSection($section)
            ->pending()
            ->exists();

        if ($hasPending) {
            throw new \RuntimeException(__('common.exceptions.change_request.pending_exists'));
        }

        $request = TravelAgencyChangeRequest::query()->create([
            'travel_agency_id' => $travelAgency->id,
            'requested_by_travel_agency_member_id' => $requestedBy->id,
            'section' => $section,
            'request_type' => $requestType,
            'current_data' => $currentData,
            'requested_data' => $requestedData,
            'agency_note' => $agencyNote,
            'status' => 'pending',
        ]);

        Notification::send(
            Admin::permission('travel_agency_change_requests.view')->get(),
            new TravelAgencyChangeRequestSubmitted($request)
        );

        $this->activityLogger->log(
            description: "Change request submitted: {$section}",
            subject: $travelAgency,
            causer: $requestedBy,
            properties: ['section' => $section, 'request_type' => $requestType],
            logName: 'travel_agency_profile',
            event: 'change_request_submitted',
        );

        return $request;
    }

    public function approveRequest(TravelAgencyChangeRequest $request, Admin $admin, ?string $note): void
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
            subject: $request->travelAgency,
            causer: $admin,
            properties: ['section' => $request->section],
            logName: 'travel_agency_profile',
            event: 'change_request_approved',
        );

        Notification::send($request->travelAgency->travelAgencyMembers, new ChangeRequestApproved($request));
    }

    public function rejectRequest(TravelAgencyChangeRequest $request, Admin $admin, string $note): void
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
            subject: $request->travelAgency,
            causer: $admin,
            properties: ['section' => $request->section, 'reason' => $note],
            logName: 'travel_agency_profile',
            event: 'change_request_rejected',
        );

        Notification::send($request->travelAgency->travelAgencyMembers, new ChangeRequestRejected($request, $note));
    }

    public function cancelRequest(TravelAgencyChangeRequest $request, TravelAgencyMember $cancelledBy): void
    {
        if (! $request->isPending() || $request->travel_agency_id !== $cancelledBy->travel_agency_id) {
            throw new \RuntimeException(__('common.exceptions.change_request.cannot_be_cancelled'));
        }

        $request->update(['status' => 'cancelled']);
    }

    private function applyChanges(TravelAgencyChangeRequest $request): void
    {
        $data = $request->requested_data;

        match ($request->section) {
            'bank_accounts' => $this->applyBankAccountChange($request, $data),
            default => throw new \RuntimeException("Unknown section: {$request->section}"),
        };
    }

    private function applyBankAccountChange(TravelAgencyChangeRequest $request, array $data): void
    {
        match ($request->request_type) {
            'add' => TravelAgencyBankAccount::query()->create(array_merge(
                array_intersect_key($data, array_flip([
                    'account_holder_name', 'bank_name', 'branch', 'iban',
                    'account_number_encrypted', 'swift_code', 'currency', 'is_primary',
                ])),
                ['travel_agency_id' => $request->travel_agency_id]
            )),
            'edit' => TravelAgencyBankAccount::query()
                ->where('travel_agency_id', $request->travel_agency_id)
                ->findOrFail($data['id'])
                ->update(array_intersect_key($data, array_flip([
                    'account_holder_name', 'bank_name', 'branch', 'iban',
                    'account_number_encrypted', 'swift_code', 'currency', 'is_primary',
                ]))),
            'delete' => TravelAgencyBankAccount::query()
                ->where('travel_agency_id', $request->travel_agency_id)
                ->findOrFail($data['id'])
                ->delete(),
            default => throw new \RuntimeException("Unknown request type: {$request->request_type}"),
        };
    }
}
