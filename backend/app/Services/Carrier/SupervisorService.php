<?php

namespace App\Services\Carrier;

use App\Mail\Carrier\SupervisorInviteMail;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use App\Services\Auth\CredentialDeliveryResult;
use App\Services\Auth\CredentialDeliveryService;
use Illuminate\Support\Str;

class SupervisorService
{
    /**
     * Create a new supervisor with a random temp password and email the invite synchronously,
     * so a failure is logged and returned to the caller (the account is kept).
     *
     * @return array{0: ShippingCompanySupervisor, 1: CredentialDeliveryResult}
     */
    public function invite(ShippingCompany $company, array $data): array
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

        $delivery = app(CredentialDeliveryService::class)
            ->deliver($supervisor->email, new SupervisorInviteMail($supervisor, $company, $tempPassword), 'carrier_supervisor_invite');

        return [$supervisor, $delivery];
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
