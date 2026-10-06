<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Vendor;
use App\Services\Vendor\CategoryContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContractController extends Controller
{
    public function __construct(private readonly CategoryContractService $contracts) {}

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
            ->map(function (array $item) use ($vendor) {
                $variables = $this->contracts->variablesFor($vendor, $item['category'], $item['template']);

                return [
                    'template_id' => $item['template']->id,
                    'name' => $item['template']->name,
                    'version' => $item['template']->version,
                    'category_scope' => $item['scope'],
                    'category_name' => $item['category']->name_en,
                    'content_en' => $this->contracts->render($item['template']->content_en, $variables),
                    'content_ar' => $this->contracts->render($item['template']->content_ar, $variables),
                ];
            })
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
        $variables = $this->contracts->variablesFor($vendor, $category, $template);

        return ApiResponse::success([
            'requires_contract' => true,
            'template_id' => $template->id,
            'name' => $template->name,
            'version' => $template->version,
            'content_en' => $this->contracts->render($template->content_en, $variables),
            'content_ar' => $this->contracts->render($template->content_ar, $variables),
        ]);
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
}
