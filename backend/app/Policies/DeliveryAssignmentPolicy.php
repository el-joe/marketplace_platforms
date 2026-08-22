<?php

namespace App\Policies;

use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;

class DeliveryAssignmentPolicy
{
    public function view(DeliveryAgent $agent, DeliveryAssignment $assignment): bool
    {
        return $assignment->agent_id === $agent->id;
    }

    public function act(DeliveryAgent $agent, DeliveryAssignment $assignment): bool
    {
        return $assignment->agent_id === $agent->id;
    }
}
