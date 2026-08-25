<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TravelAgencyPortal\Package\StorePackageRequest;
use App\Http\Requests\Api\TravelAgencyPortal\Package\UpdatePackageRequest;
use App\Http\Resources\Api\TravelAgencyPortal\TravelPackageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Currency;
use App\Models\TravelCity;
use App\Models\TravelCountry;
use App\Models\TravelPackage;
use App\Models\TravelPackageMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageController extends Controller
{
    private function agencyId(): string
    {
        return Auth::guard('travel_agencies')->id();
    }

    private function authorise(TravelPackage $package): void
    {
        abort_if($package->travel_agency_id !== $this->agencyId(), 403);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = TravelPackage::where('travel_agency_id', $this->agencyId())
            ->with(['media', 'destinationCountry', 'destinationCity', 'pricingTiers'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', TravelPackageStatus::from($status));
        }

        $paginator = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::paginated($paginator, TravelPackageResource::class);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(StorePackageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $priceTiers = $data['price_tiers'] ?? [];
        unset($data['price_tiers']);

        $inclusionIds = $data['inclusion_ids'] ?? [];
        unset($data['inclusion_ids']);

        $package = TravelPackage::create([
            ...$data,
            'travel_agency_id' => $this->agencyId(),
            'status' => TravelPackageStatus::Draft,
        ]);

        $package->syncPricingTiers($priceTiers);
        $package->inclusions()->sync($inclusionIds);
        $this->storeContractFile($request, $package);
        $this->handleMediaUploads($request, $package);

        $package->load(['media', 'destinationCountry', 'destinationCity', 'pricingTiers', 'inclusions']);

        return ApiResponse::success(new TravelPackageResource($package), 'Package saved as draft.', 201);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(TravelPackage $package): JsonResponse
    {
        $this->authorise($package);
        $package->load(['media', 'destinationCountry', 'destinationCity', 'pricingTiers']);

        return ApiResponse::success(new TravelPackageResource($package));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(UpdatePackageRequest $request, TravelPackage $package): JsonResponse
    {
        $this->authorise($package);

        if (! in_array($package->status, [TravelPackageStatus::Draft, TravelPackageStatus::PendingReview])) {
            return ApiResponse::error('Active packages cannot be edited. Contact support.', [], 422);
        }

        $data = $request->validated();
        $priceTiers = $data['price_tiers'] ?? [];
        unset($data['price_tiers']);

        $inclusionIds = $data['inclusion_ids'] ?? [];
        unset($data['inclusion_ids']);

        $package->update($data);
        $package->syncPricingTiers($priceTiers);
        $package->inclusions()->sync($inclusionIds);

        if ($request->hasFile('contract_file')) {
            if ($package->contract_file_path) {
                Storage::disk('local')->delete($package->contract_file_path);
            }
            $this->storeContractFile($request, $package);
        }

        $this->handleMediaUploads($request, $package);

        $package->load(['media', 'destinationCountry', 'destinationCity', 'pricingTiers', 'inclusions']);

        return ApiResponse::success(new TravelPackageResource($package), 'Package updated.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(TravelPackage $package): JsonResponse
    {
        $this->authorise($package);

        if ($package->status !== TravelPackageStatus::Draft) {
            return ApiResponse::error('Only draft packages can be deleted.', [], 422);
        }

        if ($package->contract_file_path) {
            Storage::disk('local')->delete($package->contract_file_path);
        }
        foreach ($package->media as $media) {
            Storage::disk('public')->delete($media->file_path);
        }

        $package->delete();

        return ApiResponse::success(null, 'Package deleted.');
    }

    // ── Countries / currencies (reference data for the form) ────────────────

    public function countries(): JsonResponse
    {
        $countries = TravelCountry::where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar']);

        return ApiResponse::success($countries);
    }

    public function currencies(): JsonResponse
    {
        $currencies = Currency::where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);

        return ApiResponse::success($currencies);
    }

    // ── Cities for country ───────────────────────────────────────────────────

    public function citiesForCountry(string $travelCountryId): JsonResponse
    {
        $cities = TravelCity::where('travel_country_id', $travelCountryId)
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar']);

        return ApiResponse::success($cities);
    }

    // ── Submit for review ─────────────────────────────────────────────────────

    public function submitForReview(TravelPackage $package): JsonResponse
    {
        $this->authorise($package);

        if ($package->status !== TravelPackageStatus::Draft) {
            return ApiResponse::error('Only draft packages can be submitted for review.', [], 422);
        }

        $errors = $package->reviewReadinessErrors();
        if (! empty($errors)) {
            return ApiResponse::error('Package is not ready for review.', ['submission' => $errors], 422);
        }

        $package->update(['status' => TravelPackageStatus::PendingReview]);

        $package->load(['media', 'destinationCountry', 'destinationCity']);

        return ApiResponse::success(new TravelPackageResource($package), 'Package submitted for admin review.');
    }

    // ── Delete media ──────────────────────────────────────────────────────────

    public function destroyMedia(TravelPackage $package, TravelPackageMedia $media): JsonResponse
    {
        $this->authorise($package);
        abort_if($media->travel_package_id !== $package->id, 404);

        Storage::disk('public')->delete($media->file_path);
        $media->delete();

        return ApiResponse::success(null, 'Media removed.');
    }

    // ── Download contract ─────────────────────────────────────────────────────

    public function downloadContract(TravelPackage $package): StreamedResponse
    {
        $this->authorise($package);

        abort_unless($package->contract_file_path && Storage::disk('local')->exists($package->contract_file_path), 404);

        return Storage::disk('local')->download(
            $package->contract_file_path,
            $package->contract_file_original_name ?? 'contract.pdf'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function storeContractFile(Request $request, TravelPackage $package): void
    {
        if (! $request->hasFile('contract_file')) {
            return;
        }

        $file = $request->file('contract_file');
        $path = $file->store("travel-packages/{$package->id}/contracts", 'local');

        $package->update([
            'contract_file_path' => $path,
            'contract_file_original_name' => $file->getClientOriginalName(),
            'contract_uploaded_at' => now(),
        ]);
    }

    private function handleMediaUploads(Request $request, TravelPackage $package): void
    {
        if (! $request->hasFile('media')) {
            return;
        }

        $position = $package->media()->max('position') ?? 0;

        foreach ($request->file('media') as $file) {
            $ext = $file->getClientOriginalExtension();
            $type = in_array(strtolower($ext), ['mp4', 'mov']) ? 'video' : 'image';
            $path = $file->store("travel-packages/{$package->id}", 'public');

            TravelPackageMedia::create([
                'travel_package_id' => $package->id,
                'media_type' => $type,
                'file_path' => $path,
                'position' => ++$position,
            ]);
        }
    }
}
