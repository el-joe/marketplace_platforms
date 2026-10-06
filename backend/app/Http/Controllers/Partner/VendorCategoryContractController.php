<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorContract;
use App\Services\Vendor\CategoryContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VendorCategoryContractController extends Controller
{
    public function __construct(private readonly CategoryContractService $contracts) {}

    private function vendorAdmin(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    private function vendor(): Vendor
    {
        return $this->vendorAdmin()->vendor;
    }

    /**
     * Every contract still to sign for this vendor, one card per template, with both languages rendered.
     */
    public function pending(): View
    {
        $vendor = $this->vendor();

        $pending = $this->contracts->pendingForVendor($vendor)
            ->unique(fn (array $item) => $item['template']->id)
            ->map(function (array $item) use ($vendor) {
                $variables = $this->contracts->variablesFor($vendor, $item['category'], $item['template']);
                $item['resolved_content_en'] = $this->contracts->renderContent($item['template']->content_en, $variables);
                $item['resolved_content_ar'] = $this->contracts->renderContent($item['template']->content_ar, $variables);

                return $item;
            })
            ->values();

        return view('partner.contracts.pending', compact('pending'));
    }

    /**
     * Shows a template with every variable filled in for this vendor. The category is the one the template is assigned to.
     */
    public function preview(string $templateId): View
    {
        $template = ClassifiedContractTemplate::findOrFail($templateId);
        $vendor = $this->vendor();

        $category = ClassifiedCategory::where('contract_template_id', $template->id)->first()
            ?? Category::where('contract_template_id', $template->id)->first();

        $variables = $this->contracts->variablesFor($vendor, $category, $template, $this->vendorAdmin()->name);

        $resolvedEn = $this->contracts->renderContent($template->content_en, $variables);
        $resolvedAr = $this->contracts->renderContent($template->content_ar, $variables);

        return view('partner.contracts.preview', compact('template', 'category', 'resolvedEn', 'resolvedAr'));
    }

    /**
     * The exact text this vendor signed, frozen at signing time.
     */
    public function signed(VendorContract $contract): View
    {
        abort_unless($contract->vendor_id === $this->vendor()->id, 404);

        return view('partner.contracts.signed', compact('contract'));
    }

    /**
     * Signs the pending contract the vendor is reading. The signed language and typed name are frozen with it.
     */
    public function accept(Request $request, string $templateId): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'signature_name' => 'required|string|max:150',
            'language' => 'required|in:en,ar',
            'agreed' => 'accepted',
            'category_scope' => 'nullable|in:classified,product',
            'category_id' => 'nullable|uuid',
        ]);

        $vendor = $this->vendor();

        // Inline signing from a listing form names the category; the vendor may not list there yet.
        if (! empty($validated['category_id'])) {
            $scope = $validated['category_scope'] ?? CategoryContractService::SCOPE_CLASSIFIED;
            $template = $this->contracts->unsignedTemplateFor($vendor, $scope, $validated['category_id']);
            $pendingItem = $template && $template->id === $templateId
                ? ['scope' => $scope, 'category' => $this->contracts->categoryFor($scope, $validated['category_id'])]
                : null;
        } else {
            $pendingItem = $this->contracts->pendingForVendor($vendor)
                ->first(fn (array $item) => $item['template']->id === $templateId);
        }

        if (! $pendingItem) {
            return $this->respond($request, false, __('partner.contracts.not_pending'), route('partner.contracts.pending'));
        }

        $this->contracts->sign(
            vendor: $vendor,
            signer: $this->vendorAdmin(),
            scope: $pendingItem['scope'],
            categoryId: $pendingItem['category']->id,
            language: $validated['language'],
            signerName: $validated['signature_name'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent() ?? '',
        );

        $remaining = $this->contracts->pendingForVendor($vendor)->count();

        if ($remaining > 0) {
            return $this->respond($request, true, __('partner.contracts.accepted_one_more_pending'), route('partner.contracts.pending'));
        }

        return $this->respond(
            $request,
            true,
            __('partner.contracts.all_accepted'),
            session()->pull('url.intended', route('partner.dashboard')),
        );
    }

    public function history(): View
    {
        $contracts = VendorContract::where('vendor_id', $this->vendor()->id)
            ->with(['contractTemplate', 'classifiedCategory', 'productCategory'])
            ->orderByDesc('signed_at')
            ->paginate(20);

        return view('partner.contracts.history', compact('contracts'));
    }

    private function respond(Request $request, bool $success, string $message, string $redirectUrl): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => $success, 'message' => $message, 'redirect' => $redirectUrl], $success ? 200 : 422);
        }

        return redirect($redirectUrl)->with($success ? 'success' : 'error', $message);
    }
}
