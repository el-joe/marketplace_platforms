<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ProductRequestStatus;
use App\Http\Requests\ProductRequest\StoreProductRequestSubmission;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketerAdmin;
use App\Models\ProductRequest;
use App\Models\VendorAdmin;
use App\Services\Catalog\ProductRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Shared actions for the partner and marketer "Product Requests" pages:
 * a team member requests a catalog product that is missing when adding a listing.
 *
 * Every lookup goes through teamRequests(), so a request owned by another
 * vendor/marketer is never found (404).
 */
trait HandlesPanelProductRequests
{
    /**
     * The signed-in panel user (VendorAdmin on the partner panel, MarketerAdmin on the marketer panel).
     */
    abstract protected function panelUser(): VendorAdmin|MarketerAdmin;

    /**
     * Product requests owned by the signed-in user's vendor or marketer.
     *
     * @return Builder<ProductRequest>
     */
    abstract protected function teamRequests(): Builder;

    /**
     * Panel key used for route names and view folders: "partner" or "marketer".
     */
    abstract protected function panel(): string;

    /**
     * Country pre-selected as the target market on the create form.
     */
    abstract protected function defaultCountryId(): ?string;

    public function index(Request $request): View
    {
        $statusFilter = ProductRequestStatus::tryFrom((string) $request->query('status'));

        $statusCounts = $this->teamRequests()
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $productRequests = $this->teamRequests()
            ->with('category:id,name_en,name_ar')
            ->when($statusFilter, fn (Builder $query) => $query->where('status', $statusFilter->value))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view($this->panel().'.product-requests.index', [
            'productRequests' => $productRequests,
            'statusCounts' => $statusCounts,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create(Request $request): View
    {
        return view($this->panel().'.product-requests.create', [
            'categories' => $this->categoryOptions(),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar']),
            'countries' => Country::query()
                ->where('is_active', true)
                ->where('is_launched', true)
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar']),
            'defaultCountryId' => $this->defaultCountryId(),
            'searchedText' => mb_substr(trim((string) $request->query('q', '')), 0, 255),
        ]);
    }

    public function store(StoreProductRequestSubmission $request, ProductRequestService $productRequestService): RedirectResponse
    {
        $productRequest = $productRequestService->submit(
            $request->safe()->except('images'),
            $this->panelUser(),
            $request->file('images', []),
        );

        return redirect()
            ->route($this->panel().'.product-requests.show', $productRequest->id)
            ->with('success', __('product_requests.messages.submitted', ['number' => $productRequest->request_number]));
    }

    public function show(string $productRequest): View
    {
        $productRequest = $this->teamRequests()
            ->with([
                'category:id,name_en,name_ar',
                'brand:id,name_en,name_ar',
                'country:id,name_en,name_ar',
                'product:id,name_en,name_ar,gtin',
                'requestedBy',
            ])
            ->findOrFail($productRequest);

        return view($this->panel().'.product-requests.show', ['productRequest' => $productRequest]);
    }

    public function cancel(string $productRequest, ProductRequestService $productRequestService): RedirectResponse
    {
        $productRequest = $this->teamRequests()->findOrFail($productRequest);

        try {
            $productRequestService->cancel($productRequest, $this->panelUser());
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()
            ->route($this->panel().'.product-requests.show', $productRequest->id)
            ->with('success', __('product_requests.messages.cancelled', ['number' => $productRequest->request_number]));
    }

    /**
     * Active categories labelled with their parent ("Parent › Child") in the current locale,
     * sorted by that label so related categories stay together.
     *
     * @return Collection<string, string>
     */
    private function categoryOptions(): Collection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->with('parent:id,name_en,name_ar')
            ->get(['id', 'parent_id', 'name_en', 'name_ar']);

        return $categories
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => $category->parent
                    ? $category->parent->name.' › '.$category->name
                    : $category->name,
            ])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE);
    }
}
