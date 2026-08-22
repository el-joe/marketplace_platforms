<?php

namespace App\Services\Carrier;

use App\Enums\DeliveryAgentType;
use App\Mail\Carrier\AgentWelcomeMail;
use App\Models\DeliveryAgent;
use App\Models\DeliveryZone;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AgentRosterService
{
    /**
     * Create a third-party delivery agent scoped to the supervisor's company.
     *
     * New agents are created with status='inactive'. They require admin document
     * verification before they can be set to 'active' and begin receiving assignments.
     * The supervisor's panel can activate them via PUT /agents/{id} once documents
     * are verified, but the initial state is intentionally inactive.
     */
    public function createAgent(
        ShippingCompany $company,
        ShippingCompanySupervisor $supervisor,
        array $data,
    ): DeliveryAgent {
        if (!empty($data['zone_id'])) {
            $zone = DeliveryZone::find($data['zone_id']);

            if ($zone && $zone->isAtCapacity()) {
                throw ValidationException::withMessages([
                    'zone_id' => "Zone \"{$zone->name}\" is at full capacity. Choose a different zone.",
                ]);
            }
        }

        $agent = DeliveryAgent::create([
            'shipping_company_id'      => $company->id,
            'added_by_supervisor_id'   => $supervisor->id,
            'agent_type'               => DeliveryAgentType::ThirdParty->value,
            'status'                   => 'inactive',
            'country_id'               => $data['country_id'],
            'name'                     => $data['name'],
            'email'                    => $data['email'],
            'phone'                    => $data['phone'],
            'password'                 => $data['password'],
            'vehicle_type'             => $data['vehicle_type'],
            'vehicle_plate'            => $data['license_plate'],
            'zone_id'                  => $data['zone_id'] ?? null,
            'is_available'             => false,
        ]);

        Mail::to($agent->email)
            ->queue(new AgentWelcomeMail($agent, $company, $data['password']));

        return $agent;
    }
}
