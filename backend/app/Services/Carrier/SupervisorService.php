<?php

namespace App\Services\Carrier;

use App\Mail\Carrier\SupervisorInviteMail;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SupervisorService
{
    /**
     * Create a new supervisor with a random temp password and queue an invite email.
     */
    public function invite(ShippingCompany $company, array $data): ShippingCompanySupervisor
    {
        $tempPassword = Str::password(12, letters: true, numbers: true, symbols: false);

        $supervisor = ShippingCompanySupervisor::create([
            'shipping_company_id' => $company->id,
            'country_id'          => $data['country_id'] ?? null,
            'name'                => $data['name'],
            'email'               => $data['email'],
            'phone'               => $data['phone'] ?? null,
            'password'            => $tempPassword,   // cast hashes automatically
            'permissions'         => $data['permissions'] ?? [],
            'is_active'           => true,
            'receives_all_notifications' => false,
        ]);

        Mail::to($supervisor->email)
            ->queue(new SupervisorInviteMail($supervisor, $company, $tempPassword));

        return $supervisor;
    }

    /**
     * Whether this company allows supervisors to receive all notifications.
     * The individual flag can only be set true when the company-level gate is on.
     */
    public function canReceiveAllNotifications(ShippingCompany $company): bool
    {
        return (bool) $company->can_supervisors_receive_all_notifications;
    }
}
