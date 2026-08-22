<?php

namespace App\Http\View\Composers;

use App\Enums\SupportTicketRequesterRole;
use App\Enums\SupportTicketStatus;
use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageInquiryStatus;
use App\Models\SupportTicket;
use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use App\Models\TravelBooking;
use App\Models\TravelPackageInquiry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TravelAgencySidebarComposer
{
    public function compose(View $view): void
    {
        $user = Auth::guard('travel_agency')->user();

        if (! $user instanceof TravelAgency && ! $user instanceof TravelAgencyMember) {
            $view->with([
                'taAgency' => null,
                'taIsOwner' => false,
                'pendingBookingsCount' => 0,
                'newInquiriesCount' => 0,
                'openTicketsCount' => 0,
            ]);

            return;
        }

        // The guard resolves either the owner (TravelAgency) or a staff member
        // (TravelAgencyMember) — only the member record has a separate travel_agency_id.
        $agency = $user instanceof TravelAgencyMember ? $user->travelAgency : $user;
        $agencyId = $agency->id;

        $badges = Cache::remember("travel-agency.{$agencyId}.sidebar-badges", 60, function () use ($agencyId) {
            return [
                'pending_bookings' => TravelBooking::query()
                    ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
                    ->where('status', TravelBookingStatus::PendingDocuments)
                    ->count(),
                'new_inquiries' => TravelPackageInquiry::query()
                    ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
                    ->where('status', TravelPackageInquiryStatus::New)
                    ->count(),
                'open_tickets' => SupportTicket::query()
                    ->where('requester_user_id', $agencyId)
                    ->where('requester_role', SupportTicketRequesterRole::TravelAgency)
                    ->whereNotIn('status', [SupportTicketStatus::Resolved, SupportTicketStatus::Closed])
                    ->count(),
            ];
        });

        $view->with([
            'taAgency' => $agency,
            'taIsOwner' => $user->isOwner(),
            'pendingBookingsCount' => $badges['pending_bookings'],
            'newInquiriesCount' => $badges['new_inquiries'],
            'openTicketsCount' => $badges['open_tickets'],
        ]);
    }
}
