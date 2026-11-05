<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\CustomerContract;
use App\Services\ActivityLoggerService;
use App\Services\Vendor\ContractFileService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Signed customer purchase contracts (kind customer_purchase): what customers accepted before buying or renting a
 * classified item. Read-only: signed records are never edited. Protected by the classifieds.view permission.
 */
class CustomerContractController extends Controller
{
    public function __construct(
        private readonly ContractFileService $files,
        private readonly ActivityLoggerService $activity,
    ) {}

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), [CustomerContract::STATUS_ACTIVE, CustomerContract::STATUS_SUPERSEDED, CustomerContract::STATUS_CANCELLED], true) ? $request->query('status') : null;
        $search = trim((string) $request->query('q', ''));
        $countryId = $this->existingId($request->query('country'), Country::class);
        $categoryId = $this->existingId($request->query('category'), ClassifiedCategory::class);
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));

        $contracts = CustomerContract::query()
            ->with(['customer:id,name,email', 'template:id,name', 'classifiedCategory:id,name_en,name_ar', 'country:id,name_en,name_ar,iso_code_2', 'listing:id,listing_number,slug'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($countryId, fn (Builder $query) => $query->where('country_id', $countryId))
            ->when($categoryId, fn (Builder $query) => $query->whereIn('classified_category_id', $this->categoryWithDescendants($categoryId)))
            ->when($from, fn (Builder $query) => $query->where('signed_at', '>=', $from->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('signed_at', '<=', $to->endOfDay()))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $q) => $q
                    ->where('customer_name', 'like', $like)
                    ->orWhere('customer_email', 'like', $like)
                    ->orWhere('signer_name', 'like', $like)
                    ->orWhere('listing_number', 'like', $like)
                    ->orWhere('listing_title', 'like', $like));
            })
            ->orderByDesc('signed_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.contracts.customer.index', [
            'contracts' => $contracts,
            'status' => $status,
            'search' => $search,
            'countryId' => $countryId,
            'categoryId' => $categoryId,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'countries' => Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'iso_code_2']),
            'categories' => ClassifiedCategory::orderBy('sort_order')->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'parent_id']),
        ]);
    }

    public function show(CustomerContract $customerContract): View
    {
        $customerContract->load(['customer:id,name,email', 'template:id,name,version', 'classifiedCategory:id,name_en,name_ar', 'country:id,name_en,name_ar,iso_code_2', 'listing:id,listing_number,slug,title_en', 'inquiry:id,created_at']);

        return view('admin.contracts.customer.show', ['contract' => $customerContract]);
    }

    /**
     * The exact document a customer accepted (uploaded contracts only; text contracts are shown in the page).
     */
    public function file(CustomerContract $customerContract): StreamedResponse
    {
        $this->activity->log(
            description: 'customer_contract.file_downloaded: '.$customerContract->listing_number,
            subject: $customerContract,
            causer: auth('admin')->user(),
            properties: ['customer_id' => $customerContract->customer_id, 'listing_id' => $customerContract->classified_listing_id],
            logName: 'contracts',
            event: 'customer_contract.file_downloaded',
        );

        return $this->files->respondToCustomerSigned($customerContract);
    }

    /**
     * @return list<string>
     */
    private function categoryWithDescendants(string $categoryId): array
    {
        $ids = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== [] && count($ids) < 500) {
            $frontier = ClassifiedCategory::whereIn('parent_id', $frontier)->pluck('id')->diff($ids)->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function existingId(mixed $value, string $model): ?string
    {
        return is_string($value) && $value !== '' && $model::whereKey($value)->exists() ? $value : null;
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::createFromFormat('Y-m-d', $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
