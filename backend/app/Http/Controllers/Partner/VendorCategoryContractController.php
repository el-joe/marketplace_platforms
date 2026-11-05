<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Models\Product;
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
                $item['doc'] = $this->contracts->present($item['template'], $vendor, $item['category']);

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
        $template = ClassifiedContractTemplate::where('is_published', true)->findOrFail($templateId);
        $vendor = $this->vendor();

        $assignment = ContractCountryAssignment::where('contract_template_id', $template->id)->first();

        $category = ClassifiedCategory::where('contract_template_id', $template->id)->first()
            ?? Category::where('contract_template_id', $template->id)->first()
            ?? ($assignment?->classified_category_id ? ClassifiedCategory::find($assignment->classified_category_id) : null)
            ?? ($assignment?->product_category_id ? Category::find($assignment->product_category_id) : null);

        $doc = $this->contracts->present($template, $vendor, $category);

        return view('partner.contracts.preview', compact('template', 'category', 'doc'));
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
     * One document covers every category / product that uses it, so all of the vendor's pending subjects for
     * this template are signed together. The template is the one resolved for the vendor's country, never a
     * client-supplied id.
     */
    public function accept(Request $request, string $templateId): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'signature_name' => 'required|string|max:150',
            'language' => 'required|in:en,ar',
            'agreed' => 'accepted',
            'category_scope' => 'nullable|in:classified,product',
            'category_id' => 'nullable|uuid',
            'product_id' => 'nullable|uuid',
        ]);

        $vendor = $this->vendor();

        // Inline signing from a listing form names the category or product; the vendor may not list there yet.
        if (! empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
            $pending = $product ? $this->contracts->unsignedForProduct($vendor, $product) : null;
            $items = $pending && $pending['template']->id === $templateId
                ? collect([['scope' => $pending['scope'], 'category' => $pending['subject']]])
                : collect();
        } elseif (! empty($validated['category_id'])) {
            $scope = $validated['category_scope'] ?? CategoryContractService::SCOPE_CLASSIFIED;
            $template = $this->contracts->unsignedTemplateFor($vendor, $scope, $validated['category_id']);
            $items = $template && $template->id === $templateId
                ? collect([['scope' => $scope, 'category' => $this->contracts->categoryFor($scope, $validated['category_id'])]])
                : collect();
        } else {
            $items = $this->contracts->pendingForVendor($vendor)
                ->filter(fn (array $item) => $item['template']->id === $templateId)
                ->values();
        }

        if ($items->isEmpty()) {
            return $this->respond($request, false, __('partner.contracts.not_pending'), route('partner.contracts.pending'));
        }

        foreach ($items as $item) {
            $this->contracts->sign(
                vendor: $vendor,
                signer: $this->vendorAdmin(),
                scope: $item['scope'],
                categoryId: $item['category'] instanceof Product ? (string) $item['category']->category_id : $item['category']->id,
                language: $validated['language'],
                signerName: $validated['signature_name'],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent() ?? '',
                productId: $item['category'] instanceof Product ? $item['category']->id : null,
            );
        }

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
            ->with(['contractTemplate', 'classifiedCategory', 'productCategory', 'product:id,name_en'])
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
