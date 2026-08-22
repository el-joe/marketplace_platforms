<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelAgency;
use App\Models\TravelAgencyChangeRequest;
use App\Models\TravelAgencySectionLock;
use App\Services\TravelAgencyChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TravelAgencyChangeRequestController extends Controller
{
    private const MASKED_FIELDS = ['iban', 'account_number', 'account_number_encrypted'];

    public function __construct(private readonly TravelAgencyChangeRequestService $changeRequestService) {}

    public function index(Request $request): View
    {
        $query = TravelAgencyChangeRequest::query()
            ->with(['travelAgency', 'requestedBy'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($section = $request->query('section')) {
            $query->where('section', $section);
        }

        if ($travelAgencyId = $request->query('travel_agency_id')) {
            $query->where('travel_agency_id', $travelAgencyId);
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('admin.travel-change-requests.index', [
            'requests' => $requests,
            'sections' => TravelAgencySectionLock::sections(),
            'travelAgencies' => TravelAgency::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(string $id): View
    {
        $changeRequest = TravelAgencyChangeRequest::query()
            ->with(['travelAgency', 'requestedBy', 'reviewedByAdmin'])
            ->findOrFail($id);

        $currentData = $changeRequest->current_data ?? [];
        if ($changeRequest->section === TravelAgencySectionLock::SECTION_BANK_ACCOUNTS) {
            $currentData = $this->maskBankFields($currentData);
        }

        return view('admin.travel-change-requests.show', [
            'changeRequest' => $changeRequest,
            'currentData' => $currentData,
        ]);
    }

    public function approve(Request $request, TravelAgencyChangeRequest $changeRequest): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
        ]);

        $this->changeRequestService->approveRequest($changeRequest, auth('admin')->user(), $data['admin_note'] ?: null);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.travel_agency_change_requests.approved_success')]);
        }

        return redirect()
            ->route('admin.travel.change-requests.show', $changeRequest->id)
            ->with('success', __('admin.travel_agency_change_requests.approved_success'));
    }

    public function reject(Request $request, TravelAgencyChangeRequest $changeRequest): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string'],
        ]);

        $this->changeRequestService->rejectRequest($changeRequest, auth('admin')->user(), $data['admin_note']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.travel_agency_change_requests.rejected_success')]);
        }

        return redirect()
            ->route('admin.travel.change-requests.show', $changeRequest->id)
            ->with('success', __('admin.travel_agency_change_requests.rejected_success'));
    }

    private function maskBankFields(array $data): array
    {
        foreach (self::MASKED_FIELDS as $field) {
            if (!empty($data[$field]) && is_string($data[$field])) {
                $value = $data[$field];
                $data[$field] = str_repeat('•', max(0, strlen($value) - 4)) . substr($value, -4);
            }
        }

        return $data;
    }
}
