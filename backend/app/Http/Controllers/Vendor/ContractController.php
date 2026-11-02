<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClassifiedContractTemplate;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractFileService;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    private const FILE_ROUTE = 'vendor.legal.template-file';

    public function __construct(
        private readonly CategoryContractService $contracts,
        private readonly SellerAgreementService $agreements,
        private readonly ContractFileService $files,
    ) {}

    private function vendor(): Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    /**
     * Contracts still to sign, for both classified and product categories.
     */
    public function pending(): JsonResponse
    {
        $vendor = $this->vendor();

        $data = $this->contracts->pendingForVendor($vendor)
            ->unique(fn (array $item) => $item['template']->id)
            ->map(fn (array $item) => $this->contracts->present($item['template'], $vendor, $item['category'], self::FILE_ROUTE) + [
                'category_scope' => $item['scope'],
                'category_name' => $item['category']->name_en,
            ])
            ->values();

        return ApiResponse::success($data);
    }

    /**
     * Tells the app whether a category needs a signature before a listing can be created in it.
     */
    public function checkCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => 'required|in:classified,product',
            'category_id' => 'required|uuid',
        ]);

        $vendor = $this->vendor();
        $template = $this->contracts->unsignedTemplateFor($vendor, $validated['scope'], $validated['category_id']);

        if (! $template) {
            return ApiResponse::success(['requires_contract' => false]);
        }

        $category = $this->contracts->categoryFor($validated['scope'], $validated['category_id']);

        return ApiResponse::success(
            ['requires_contract' => true] + $this->contracts->present($template, $vendor, $category, self::FILE_ROUTE),
        );
    }

    public function accept(Request $request, string $templateId): JsonResponse
    {
        $validated = $request->validate([
            'signature_name' => 'required|string|max:150',
            'language' => 'nullable|in:en,ar',
            'agreed' => 'accepted',
        ]);

        $vendorAdmin = Auth::guard('vendor')->user();
        $vendor = $vendorAdmin->vendor;

        $pendingItem = $this->contracts->pendingForVendor($vendor)
            ->first(fn (array $item) => $item['template']->id === $templateId);

        if (! $pendingItem) {
            return ApiResponse::error('This contract is not pending for your account.', [], 422);
        }

        $this->contracts->sign(
            vendor: $vendor,
            signer: $vendorAdmin,
            scope: $pendingItem['scope'],
            categoryId: $pendingItem['category']->id,
            language: $validated['language'] ?? 'en',
            signerName: $validated['signature_name'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent() ?? '',
        );

        $remaining = $this->contracts->pendingForVendor($vendor)->count();

        return ApiResponse::success(
            ['remaining_pending' => $remaining],
            $remaining > 0 ? 'Contract accepted. More contracts pending.' : 'All contracts accepted.',
        );
    }

    /**
     * The Seller Agreement: what is in force, what the vendor signed, what is scheduled, and whether acceptance is pending.
     */
    public function agreement(): JsonResponse
    {
        $vendor = $this->vendor();
        $inForce = $this->agreements->inForce();
        $upcoming = $this->agreements->upcoming();
        $pending = $this->agreements->pendingFor($vendor);
        $signature = $this->agreements->activeSignature($vendor);

        return ApiResponse::success([
            'acceptance_required' => $pending !== null,
            'pending' => $pending ? $this->contracts->present($pending, $vendor, null, self::FILE_ROUTE) : null,
            'in_force' => $inForce ? $this->contracts->present($inForce, $vendor, null, self::FILE_ROUTE) : null,
            'upcoming' => $upcoming ? $this->contracts->present($upcoming, $vendor, null, self::FILE_ROUTE) + [
                'effective_at' => $upcoming->effective_at?->toIso8601String(),
                'change_summary' => $upcoming->change_summary,
            ] : null,
            'signature' => $signature ? $this->signatureData($signature) : null,
        ]);
    }

    public function acceptAgreement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signature_name' => 'required|string|max:150',
            'language' => 'nullable|in:en,ar',
            'agreed' => 'accepted',
        ]);

        $vendorAdmin = Auth::guard('vendor')->user();
        $template = $this->agreements->pendingFor($vendorAdmin->vendor);

        if (! $template) {
            return ApiResponse::error('There is no Seller Agreement waiting for your acceptance.', [], 422);
        }

        $contract = $this->agreements->sign(
            vendor: $vendorAdmin->vendor,
            signer: $vendorAdmin,
            template: $template,
            language: $validated['language'] ?? 'en',
            signerName: $validated['signature_name'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent() ?? '',
        );

        $this->agreements->sendSignedCopy($contract);

        return ApiResponse::success($this->signatureData($contract), 'Seller Agreement accepted.');
    }

    /**
     * Every contract this vendor signed, platform-wide and per category, newest first.
     */
    public function documents(): JsonResponse
    {
        $signed = VendorContract::where('vendor_id', $this->vendor()->id)
            ->with('contractTemplate:id,name')
            ->orderByDesc('signed_at')
            ->get()
            ->map(fn (VendorContract $contract) => $this->signatureData($contract) + [
                'name' => $contract->contractTemplate?->name,
                'category_scope' => $contract->category_scope,
            ]);

        return ApiResponse::success($signed);
    }

    public function templateFile(Request $request, string $template, string $language): StreamedResponse
    {
        $template = ClassifiedContractTemplate::where('is_published', true)->findOrFail($template);
        $file = ($template->files ?? [])[$language] ?? null;
        abort_unless($file, 404);

        return $this->files->respond($file, $request->boolean('download'));
    }

    public function signedFile(string $contract): StreamedResponse
    {
        $contract = VendorContract::where('vendor_id', $this->vendor()->id)->findOrFail($contract);

        return $this->files->respondToSigned($contract);
    }

    /**
     * @return array<string, mixed>
     */
    private function signatureData(VendorContract $contract): array
    {
        return [
            'id' => $contract->id,
            'version' => $contract->template_version,
            'language' => $contract->language_signed,
            'content_mode' => $contract->content_mode,
            'method' => $contract->acceptance_method,
            'status' => $contract->status,
            'signer_name' => $contract->signer_name,
            'signed_at' => $contract->signed_at?->toIso8601String(),
            'signed_file_url' => $contract->hasSignedFile() ? route('vendor.legal.signed-file', $contract->id) : null,
        ];
    }
}
