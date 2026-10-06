<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
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
                $item['resolved_content_en'] = $this->contracts->render($item['template']->content_en, $variables);
                $item['resolved_content_ar'] = $this->contracts->render($item['template']->content_ar, $variables);

                return $item;
            })
            ->values();

        return view('partner.contracts.pending', compact('pending'));
    }

    public function preview(string $templateId): View
    {
        $template = ClassifiedContractTemplate::findOrFail($templateId);
        $vendor = $this->vendor();
        $category = $template->category_scope === CategoryContractService::SCOPE_PRODUCT
            ? $template->productCategory
            : $template->category;

        $variables = $category
            ? $this->contracts->variablesFor($vendor, $category, $template)
            : [];

        $resolvedEn = $this->contracts->render($template->content_en, $variables);
        $resolvedAr = $this->contracts->render($template->content_ar, $variables);

        return view('partner.contracts.preview', compact('template', 'category', 'resolvedEn', 'resolvedAr'));
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
        ]);

        $vendor = $this->vendor();

        $pendingItem = $this->contracts->pendingForVendor($vendor)
            ->first(fn (array $item) => $item['template']->id === $templateId);

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
            session()->pull('url.intended', route('partner.classifieds.index')),
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
