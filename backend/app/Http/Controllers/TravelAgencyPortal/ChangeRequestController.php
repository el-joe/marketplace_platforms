<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\TravelAgencyChangeRequest;
use App\Services\TravelAgencyChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ChangeRequestController extends Controller
{
    use ResolvesTravelAgency;

    public function __construct(private readonly TravelAgencyChangeRequestService $changeRequests) {}

    public function index(): View
    {
        abort_unless(config('features.travel_agency_bank_accounts', false), 404);

        $requests = $this->member()->travelAgency->changeRequests()
            ->latest()
            ->paginate(20);

        return view('travel-agency.change-requests.index', compact('requests'));
    }

    public function cancel(TravelAgencyChangeRequest $changeRequest): JsonResponse
    {
        abort_unless(config('features.travel_agency_bank_accounts', false), 404);

        $member = $this->member();

        try {
            $this->changeRequests->cancelRequest($changeRequest, $member);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('travel.change_requests.cancelled'),
        ]);
    }
}
