<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Models\TravelAgencyBankAccount;
use Illuminate\Http\JsonResponse;

class BankAccountController extends Controller
{
    private function agencyId(): string
    {
        return auth()->guard('travel_agencies')->user()->id;
    }

    private function maskIban(string $iban): string
    {
        $clean = preg_replace('/\s+/', '', $iban);
        $len   = strlen($clean);
        return $len <= 4 ? $clean : str_repeat('*', $len - 4) . substr($clean, -4);
    }

    /** GET /api/travel-agency/v1/bank-accounts */
    public function index(): JsonResponse
    {
        $accounts = TravelAgencyBankAccount::where('travel_agency_id', $this->agencyId())
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'bank_accounts' => $accounts->map(fn ($a) => [
                'id'                  => $a->id,
                'account_holder_name' => $a->account_holder_name,
                'bank_name'           => $a->bank_name,
                'branch'              => $a->branch,
                'iban_masked'         => $this->maskIban($a->iban),
                'swift_code'          => $a->swift_code,
                'currency'            => $a->currency,
                'is_primary'          => $a->is_primary,
                'verification_status' => $a->verification_status,
                'created_at'          => $a->created_at?->toDateString(),
            ]),
        ]);
    }
}
