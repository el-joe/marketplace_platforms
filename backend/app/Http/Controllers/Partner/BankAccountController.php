<?php

namespace App\Http\Controllers\Partner;

use App\Enums\VendorBankAccountVerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Models\VendorSectionLock;
use App\Services\VendorChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function __construct(private readonly VendorChangeRequestService $changeRequests) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function vendorAdmin(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    private function vendorId(): string
    {
        return $this->vendorAdmin()->vendor_id;
    }

    private function authorise(VendorBankAccount $account): void
    {
        if ($account->vendor_id !== $this->vendorId()) {
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

    private function formatAccount(VendorBankAccount $account): array
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
        $accounts = VendorBankAccount::where('vendor_id', $this->vendorId())
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        $pendingChangeRequests = $this->vendorAdmin()->vendor->changeRequests()
            ->pending()
            ->forSection(VendorSectionLock::SECTION_BANK_ACCOUNTS)
            ->get();

        return view('partner.bank-accounts.index', compact('accounts', 'pendingChangeRequests'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_holder_name' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'iban' => ['required', 'string', 'max:34'],
            'swift_code' => ['nullable', 'string', 'max:11'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $admin  = $this->vendorAdmin();
        $vendor = $admin->vendor;

        try {
            $this->changeRequests->submitRequest(
                vendor: $vendor,
                requestedBy: $admin,
                section: VendorSectionLock::SECTION_BANK_ACCOUNTS,
                requestType: 'add',
                currentData: [],
                requestedData: [
                    ...$data,
                    'account_number_encrypted' => Crypt::encryptString($data['iban']),
                    'verification_status' => VendorBankAccountVerificationStatus::Pending->value,
                    'is_primary' => false,
                ],
                vendorNote: $request->input('note'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'pending_review' => true,
            'message' => 'تم إرسال طلب إضافة الحساب البنكي للمراجعة من قبل الإدارة.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Set Primary
    // ─────────────────────────────────────────────────────────────────────────

    public function setPrimary(VendorBankAccount $account): JsonResponse
    {
        $this->authorise($account);

        if ($account->verification_status !== VendorBankAccountVerificationStatus::Verified) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن تعيين الحسابات المعتمدة فقط كحسابات رئيسية.',
            ], 422);
        }

        $admin  = $this->vendorAdmin();
        $vendor = $admin->vendor;

        try {
            $this->changeRequests->submitRequest(
                vendor: $vendor,
                requestedBy: $admin,
                section: VendorSectionLock::SECTION_BANK_ACCOUNTS,
                requestType: 'edit',
                currentData: $this->formatAccount($account),
                requestedData: ['id' => $account->id, 'is_primary' => true],
                vendorNote: null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'pending_review' => true,
            'message' => 'تم إرسال طلب تعيينه كحساب رئيسي للمراجعة من قبل الإدارة.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(VendorBankAccount $account): JsonResponse
    {
        $this->authorise($account);

        if ($account->is_primary && $account->verification_status === VendorBankAccountVerificationStatus::Verified) {
            $hasPendingPayouts = Payout::where('vendor_id', $this->vendorId())
                ->where('bank_account_id', $account->id)
                ->whereIn('status', ['pending', 'approved', 'processing'])
                ->exists();

            if ($hasPendingPayouts) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف الحساب الرئيسي المعتمد أثناء وجود مدفوعات معلقة.',
                ], 422);
            }
        }

        $admin  = $this->vendorAdmin();
        $vendor = $admin->vendor;

        try {
            $this->changeRequests->submitRequest(
                vendor: $vendor,
                requestedBy: $admin,
                section: VendorSectionLock::SECTION_BANK_ACCOUNTS,
                requestType: 'delete',
                currentData: $this->formatAccount($account),
                requestedData: ['id' => $account->id],
                vendorNote: null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'pending_review' => true,
            'message' => 'تم إرسال طلب حذف الحساب البنكي للمراجعة من قبل الإدارة.',
        ]);
    }
}
