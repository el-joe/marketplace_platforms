<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelAgencyBankAccountVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\TravelAgencyBankAccount;
use App\Models\TravelAgencySectionLock;
use App\Services\TravelAgencyChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    use ResolvesTravelAgency;

    public function __construct(private readonly TravelAgencyChangeRequestService $changeRequests) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function authorise(TravelAgencyBankAccount $account): void
    {
        if ($account->travel_agency_id !== $this->agencyId()) {
            abort(403);
        }
    }

    private function maskIban(string $iban): string
    {
        $clean = preg_replace('/\s+/', '', $iban);
        $len = strlen($clean);
        if ($len <= 4) {
            return $clean;
        }
        return str_repeat('*', $len - 4) . substr($clean, -4);
    }

    private function formatAccount(TravelAgencyBankAccount $account): array
    {
        return [
            'id' => $account->id,
            'account_holder_name' => $account->account_holder_name,
            'bank_name' => $account->bank_name,
            'branch' => $account->branch,
            'iban_masked' => $this->maskIban($account->iban),
            'swift_code' => $account->swift_code,
            'currency' => $account->currency,
            'is_primary' => $account->is_primary,
            'verification_status' => $account->verification_status->value,
            'created_at' => $account->created_at?->format('Y-m-d'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        abort_unless(config('features.travel_agency_bank_accounts', false), 404);
        $this->requireOwner();

        $accounts = TravelAgencyBankAccount::where('travel_agency_id', $this->agencyId())
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        $pendingChangeRequests = $this->member()->travelAgency->changeRequests()
            ->pending()
            ->forSection(TravelAgencySectionLock::SECTION_BANK_ACCOUNTS)
            ->get();

        return view('travel-agency.bank-accounts.index', compact('accounts', 'pendingChangeRequests'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        abort_unless(config('features.travel_agency_bank_accounts', false), 404);
        $this->requireOwner();

        $data = $request->validate([
            'account_holder_name' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'iban' => ['required', 'string', 'max:34'],
            'swift_code' => ['nullable', 'string', 'max:11'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $member = $this->member();
        $travelAgency = $member->travelAgency;

        try {
            $this->changeRequests->submitRequest(
                travelAgency: $travelAgency,
                requestedBy: $member,
                section: TravelAgencySectionLock::SECTION_BANK_ACCOUNTS,
                requestType: 'add',
                currentData: [],
                requestedData: [
                    ...$data,
                    'account_number_encrypted' => Crypt::encryptString($data['iban']),
                    'verification_status' => TravelAgencyBankAccountVerificationStatus::Pending->value,
                    'is_primary' => false,
                ],
                agencyNote: $request->input('note'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'pending_review' => true,
            'message' => __('travel.bank_accounts.add_submitted'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Set Primary
    // ─────────────────────────────────────────────────────────────────────────

    public function setPrimary(TravelAgencyBankAccount $account): JsonResponse
    {
        abort_unless(config('features.travel_agency_bank_accounts', false), 404);
        $this->requireOwner();

        $this->authorise($account);

        if ($account->verification_status !== TravelAgencyBankAccountVerificationStatus::Verified) {
            return response()->json([
                'success' => false,
                'message' => __('travel.bank_accounts.only_verified_can_be_primary'),
            ], 422);
        }

        $member = $this->member();
        $travelAgency = $member->travelAgency;

        try {
            $this->changeRequests->submitRequest(
                travelAgency: $travelAgency,
                requestedBy: $member,
                section: TravelAgencySectionLock::SECTION_BANK_ACCOUNTS,
                requestType: 'edit',
                currentData: $this->formatAccount($account),
                requestedData: ['id' => $account->id, 'is_primary' => true],
                agencyNote: null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'pending_review' => true,
            'message' => __('travel.bank_accounts.set_primary_submitted'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(TravelAgencyBankAccount $account): JsonResponse
    {
        abort_unless(config('features.travel_agency_bank_accounts', false), 404);
        $this->requireOwner();

        $this->authorise($account);

        $member = $this->member();
        $travelAgency = $member->travelAgency;

        try {
            $this->changeRequests->submitRequest(
                travelAgency: $travelAgency,
                requestedBy: $member,
                section: TravelAgencySectionLock::SECTION_BANK_ACCOUNTS,
                requestType: 'delete',
                currentData: $this->formatAccount($account),
                requestedData: ['id' => $account->id],
                agencyNote: null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'pending_review' => true,
            'message' => __('travel.bank_accounts.delete_submitted'),
        ]);
    }
}
