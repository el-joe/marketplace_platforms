<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Classified\SignCustomerContractRequest;
use App\Http\Resources\Customer\CustomerContractResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedListing;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Services\Customer\ClassifiedDetailService;
use App\Services\Customer\CustomerContractService;
use App\Services\Vendor\ContractFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customer purchase contracts (kind customer_purchase): the contract of a classified advertisement the customer has to
 * review and accept before inquiring (`listings/classified/{slug}/customer-contract...`) and the customer's own accepted
 * contracts (`account/contracts`). Everything is scoped to the authenticated customer.
 */
class CustomerContractController extends Controller
{
    public function __construct(
        private readonly CustomerContractService $contracts,
        private readonly ClassifiedDetailService $classifiedDetail,
        private readonly ContractFileService $files,
    ) {}

    /**
     * The contract an advertisement requires from this customer, or `required: false`.
     */
    public function show(Request $request, $country, string $slug): JsonResponse
    {
        $listing = $this->listing($request, $slug);
        $customer = $this->customer();
        $status = $this->contracts->statusFor($customer, $listing);

        return ApiResponse::success($this->statusPayload($status, $listing, $customer));
    }

    /**
     * Records the customer's acceptance (typed name + explicit acceptance). Repeating it for the same version returns
     * the existing acceptance.
     */
    public function sign(SignCustomerContractRequest $request, $country, string $slug): JsonResponse
    {
        $listing = $this->listing($request, $slug);
        $customer = $this->customer();
        $data = $request->validated();

        $template = $this->contracts->templateFor($listing, $customer);

        if ($template && ($template->id !== $data['template_id'] || $template->version !== (int) $data['version'])) {
            return response()->json([
                'success' => false,
                'message' => __('contracts.customer.changed'),
                'code' => 'customer_contract_changed',
                'data' => ['contract' => $this->contracts->present($template, $listing, $customer)],
            ], 422);
        }

        $result = $this->contracts->accept(
            $customer,
            $listing,
            $data['signer_name'],
            $data['language'] ?? app()->getLocale(),
            $request->ip(),
            $request->userAgent(),
        );

        $status = $this->contracts->statusFor($customer, $listing);

        return ApiResponse::success(
            $this->statusPayload($status, $listing, $customer),
            $result['created'] ? __('contracts.customer.recorded') : __('contracts.customer.already_recorded'),
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * The uploaded document of the contract that applies to this advertisement (file-mode templates).
     */
    public function templateFile(Request $request, $country, string $slug, string $language): StreamedResponse
    {
        abort_unless(in_array($language, ['ar', 'en'], true), 404);

        $listing = $this->listing($request, $slug);
        $template = $this->contracts->templateFor($listing, $this->customer());
        $file = $template ? ($template->files ?? [])[$language] ?? null : null;

        abort_unless($file, 404);

        return $this->files->respond($file, $request->boolean('download'));
    }

    // ── Account: my contracts ─────────────────────────────────────────────────

    public function index(Request $request, $country): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:active,superseded,cancelled'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->customer()->customerContracts()
            ->with(['template:id,name', 'classifiedCategory:id,name_en,name_ar', 'country:id,name_en,name_ar,iso_code_2', 'listing:id,slug'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('signed_at')
            ->paginate(15);

        return ApiResponse::paginated($paginator, CustomerContractResource::class);
    }

    public function detail(Request $request, $country, string $id): JsonResponse
    {
        $contract = $this->ownContract($id);

        $resource = new CustomerContractResource($contract);
        $resource->detailed = true;

        return ApiResponse::success($resource);
    }

    public function download(Request $request, $country, string $id): StreamedResponse
    {
        return $this->files->respondToCustomerSigned($this->ownContract($id));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function customer(): Customer
    {
        /** @var Customer */
        return auth('customer')->user();
    }

    private function listing(Request $request, string $slug): ClassifiedListing
    {
        $listing = $this->classifiedDetail->findActive($slug, $request->attributes->get('country'));

        abort_if(! $listing, 404, __('common.exceptions.listing.not_found'));

        return $listing;
    }

    private function ownContract(string $id): CustomerContract
    {
        abort_unless(Str::isUuid($id), 404);

        return $this->customer()->customerContracts()
            ->with(['template:id,name', 'classifiedCategory:id,name_en,name_ar', 'country:id,name_en,name_ar,iso_code_2', 'listing:id,slug'])
            ->findOrFail($id);
    }

    /**
     * @param  array{required: bool, template: ?ClassifiedContractTemplate, acceptance: ?CustomerContract}  $status
     * @return array<string, mixed>
     */
    private function statusPayload(array $status, ClassifiedListing $listing, Customer $customer): array
    {
        $acceptance = $status['acceptance'];

        return [
            'required' => $status['required'],
            'accepted' => $status['acceptance'] !== null,
            'can_proceed' => ! $status['required'] || $status['acceptance'] !== null,
            'contract' => $status['template'] ? $this->contracts->present($status['template'], $listing, $customer) : null,
            'acceptance' => $acceptance ? [
                'id' => $acceptance->id,
                'signed_at' => $acceptance->signed_at?->toIso8601String(),
                'signer_name' => $acceptance->signer_name,
                'template_version' => $acceptance->template_version,
            ] : null,
        ];
    }
}
