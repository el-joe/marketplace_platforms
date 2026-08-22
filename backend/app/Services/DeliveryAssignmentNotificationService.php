<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\ShippingCompanySupervisor;
use App\Notifications\NewAssignmentNotification;

class DeliveryAssignmentNotificationService
{
    public function notifyOnNewAssignment(DeliveryAssignment $assignment): void
    {
        $agent = $assignment->agent;

        // Always notify the assigned agent
        $agent->notify(new NewAssignmentNotification($assignment));

        if (! $agent->shipping_company_id) {
            return;
        }

        $company = $agent->shippingCompany;

        if (! $company || ! $company->can_supervisors_receive_all_notifications) {
            return;
        }

        // Fan-out to all active supervisors who have notifications enabled.
        // Supervisors receive the assignment notification regardless of whether
        // the agent has accepted yet — this prevents companies from missing orders.
        ShippingCompanySupervisor::where('shipping_company_id', $company->id)
            ->receivingNotifications()
            ->each(function (ShippingCompanySupervisor $supervisor) use ($assignment) {
                $supervisor->notify(new NewAssignmentNotification($assignment));
            });
    }
}
