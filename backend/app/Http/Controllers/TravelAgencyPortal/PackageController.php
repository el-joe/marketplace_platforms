<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\Currency;
use App\Models\TravelCity;
use App\Models\TravelCountry;
use App\Models\TravelPackage;
use App\Models\TravelPackageMedia;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageController extends Controller
{
    use ResolvesTravelAgency;
    use HasExport;

    private function authorise(TravelPackage $package): void
    {
        if ($package->travel_agency_id !== $this->agencyId()) {
            abort(403);
        }
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    private function filteredPackagesQuery(Request $request)
    {
        $query = TravelPackage::where('travel_agency_id', $this->agencyId());

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', TravelPackageStatus::from($status));
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    public function index(Request $request): View
    {
        $packages = $this->filteredPackagesQuery($request)
            ->withCount('bookings')
            ->with(['media', 'destinationCountry', 'destinationCity'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('travel-agency.packages.index', compact('packages'));
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $packages = $this->filteredPackagesQuery($request)
            ->withCount('bookings')
            ->with(['destinationCountry', 'destinationCity'])
            ->latest()
            ->get();

        $headers = [
            __('travel.packages.export.package'),
            __('travel.packages.export.destination'),
            __('travel.packages.export.price'),
            __('travel.packages.export.currency'),
            __('travel.packages.export.status'),
            __('travel.packages.export.bookings'),
            __('travel.packages.export.date'),
        ];

        $rows = $packages->map(fn (TravelPackage $package) => [
            $package->title_en,
            trim(($package->destinationCity->name_en ?? '') . ' ' . ($package->destinationCountry->name_en ?? '')),
            $package->price,
            $package->currency,
            $package->status->value ?? $package->status,
            $package->bookings_count,
            $package->created_at?->toDateString(),
        ]);

        $filename = 'packages-' . now()->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word'  => $this->exportWord($filename, __('travel.packages.export.sheet_title'), $rows),
            'csv'   => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    private function formData(): array
    {
        return [
            'travelCountries'  => TravelCountry::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'flag_emoji']),
            'currencies'       => Currency::where('is_active', true)->orderBy('code')->get(['code', 'name', 'symbol']),
            'travelCategories' => \App\Models\TravelCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar', 'icon', 'parent_id']),
        ];
    }

    public function create(): View
    {
        return view('travel-agency.packages.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_en'                      => ['required', 'string', 'max:255'],
            'title_ar'                      => ['required', 'string', 'max:255'],
            'description_en'                => ['nullable', 'string'],
            'description_ar'                => ['nullable', 'string'],
            'destination_travel_country_id' => ['required', 'uuid', 'exists:travel_countries,id'],
            'destination_travel_city_id'    => ['nullable', 'uuid', 'exists:travel_cities,id'],
            'price'                   => ['required', 'integer', 'min:1'],
            'currency'                      => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'pricing_tiers_enabled'         => ['nullable', 'boolean'],
            'show_pricing_tiers_to_customer' => ['nullable', 'boolean'],
            'price_tiers'                   => ['nullable', 'array'],
            'price_tiers.*.travelers_count' => ['required_with:price_tiers', 'integer', 'min:1'],
            'price_tiers.*.price'     => ['required_with:price_tiers', 'integer', 'min:1'],
            'duration_days'                 => ['required', 'integer', 'min:1'],
            'duration_nights'               => ['required', 'integer', 'min:0'],
            'departure_date'                => ['required', 'date', 'after:today'],
            'return_date'                   => ['required', 'date', 'after:departure_date'],
            'available_seats'               => ['nullable', 'integer', 'min:1'],
            'inclusion_ids'                 => ['nullable', 'array'],
            'inclusion_ids.*'               => ['uuid', 'exists:travel_inclusions,id'],
            'category_ids'                  => ['nullable', 'array'],
            'category_ids.*'                => ['uuid', 'exists:travel_categories,id'],
            'media'                         => ['nullable', 'array', 'max:10'],
            'media.*'                       => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:51200'],
            'contract_file'                 => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $priceTiers = $data['price_tiers'] ?? [];
        unset($data['price_tiers']);

        $inclusionIds = $data['inclusion_ids'] ?? [];
        unset($data['inclusion_ids']);

        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $package = TravelPackage::create([
            ...$data,
            'travel_agency_id' => $this->agencyId(),
            'status' => TravelPackageStatus::Draft,
        ]);

        $package->syncPricingTiers($priceTiers);
        $package->inclusions()->sync($inclusionIds);
        $package->categories()->sync($categoryIds);
        $this->storeContractFile($request, $package);
        $this->handleMediaUploads($request, $package);

        return redirect()->route('travel-agency.packages.show', $package)
            ->with('success', __('travel.packages.draft_saved'));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(TravelPackage $package): View
    {
        $this->authorise($package);
        $package->load(['media', 'bookings.customer', 'inclusions', 'pricingTiers']);
        return view('travel-agency.packages.show', compact('package'));
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(TravelPackage $package): View
    {
        $this->authorise($package);
        $package->load(['media', 'pricingTiers', 'inclusions', 'categories']);

        return view('travel-agency.packages.edit', ['package' => $package, ...$this->formData()]);
    }

    public function update(Request $request, TravelPackage $package): RedirectResponse
    {
        $this->authorise($package);

        if (!in_array($package->status, [TravelPackageStatus::Draft, TravelPackageStatus::PendingReview])) {
            return back()->withErrors(['status' => __('travel.packages.active_edit_forbidden')]);
        }

        $data = $request->validate([
            'title_en'                      => ['required', 'string', 'max:255'],
            'title_ar'                      => ['required', 'string', 'max:255'],
            'description_en'                => ['nullable', 'string'],
            'description_ar'                => ['nullable', 'string'],
            'destination_travel_country_id' => ['required', 'uuid', 'exists:travel_countries,id'],
            'destination_travel_city_id'    => ['nullable', 'uuid', 'exists:travel_cities,id'],
            'price'                   => ['required', 'integer', 'min:1'],
            'currency'                      => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'pricing_tiers_enabled'         => ['nullable', 'boolean'],
            'show_pricing_tiers_to_customer' => ['nullable', 'boolean'],
            'price_tiers'                   => ['nullable', 'array'],
            'price_tiers.*.travelers_count' => ['required_with:price_tiers', 'integer', 'min:1'],
            'price_tiers.*.price'     => ['required_with:price_tiers', 'integer', 'min:1'],
            'duration_days'                 => ['required', 'integer', 'min:1'],
            'duration_nights'               => ['required', 'integer', 'min:0'],
            'departure_date'                => ['required', 'date'],
            'return_date'                   => ['required', 'date', 'after:departure_date'],
            'available_seats'               => ['nullable', 'integer', 'min:1'],
            'inclusion_ids'                 => ['nullable', 'array'],
            'inclusion_ids.*'               => ['uuid', 'exists:travel_inclusions,id'],
            'category_ids'                  => ['nullable', 'array'],
            'category_ids.*'                => ['uuid', 'exists:travel_categories,id'],
            'media'                         => ['nullable', 'array', 'max:10'],
            'media.*'                       => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:51200'],
            'contract_file'                 => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $priceTiers = $data['price_tiers'] ?? [];
        unset($data['price_tiers']);

        $inclusionIds = $data['inclusion_ids'] ?? [];
        unset($data['inclusion_ids']);

        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $package->update($data);
        $package->syncPricingTiers($priceTiers);
        $package->inclusions()->sync($inclusionIds);
        $package->categories()->sync($categoryIds);

        if ($request->hasFile('contract_file')) {
            if ($package->contract_file_path) {
                Storage::disk('local')->delete($package->contract_file_path);
            }
            $this->storeContractFile($request, $package);
        }

        $this->handleMediaUploads($request, $package);

        return redirect()->route('travel-agency.packages.show', $package)
            ->with('success', __('travel.packages.updated'));
    }

    // ── Cities for country (AJAX) ─────────────────────────────────────────────

    public function citiesForCountry(string $travelCountryId): JsonResponse
    {
        return response()->json(
            TravelCity::where('travel_country_id', $travelCountryId)
                ->where('is_active', true)
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar'])
        );
    }

    // ── Submit for review ─────────────────────────────────────────────────────

    public function submitForReview(TravelPackage $package): RedirectResponse
    {
        $this->authorise($package);

        if ($package->status !== TravelPackageStatus::Draft) {
            return back()->withErrors(['status' => __('travel.packages.submit_review_only_draft')]);
        }

        $errors = $package->reviewReadinessErrors();
        if (! empty($errors)) {
            return back()->withErrors(['submission' => $errors]);
        }

        $package->update(['status' => TravelPackageStatus::PendingReview]);

        return back()->with('success', __('travel.packages.submitted_for_review'));
    }

    // ── Withdraw from review ──────────────────────────────────────────────────

    public function withdraw(TravelPackage $package): RedirectResponse
    {
        $this->authorise($package);

        if ($package->status !== TravelPackageStatus::PendingReview) {
            return back()->withErrors(['status' => __('travel.packages.withdraw_only_pending')]);
        }

        $package->update(['status' => TravelPackageStatus::Draft]);

        return back()->with('success', __('travel.packages.withdrawn'));
    }

    // ── Delete media ──────────────────────────────────────────────────────────

    public function destroyMedia(TravelPackage $package, TravelPackageMedia $media): \Illuminate\Http\JsonResponse
    {
        $this->authorise($package);
        abort_if($media->travel_package_id !== $package->id, 404);

        Storage::disk('public')->delete($media->file_path);
        $media->delete();

        return response()->json(['message' => __('travel.packages.media_removed')]);
    }

    // ── Download contract ─────────────────────────────────────────────────────

    public function downloadContract(TravelPackage $package): \Symfony\Component\HttpFoundation\StreamedResponse
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
        $file = $request->file('contract_file');
        $path = $file->store("travel-packages/{$package->id}/contracts", 'local');

        $package->update([
            'contract_file_path'          => $path,
            'contract_file_original_name' => $file->getClientOriginalName(),
            'contract_uploaded_at'        => now(),
        ]);
    }

    private function handleMediaUploads(Request $request, TravelPackage $package): void
    {
        if (!$request->hasFile('media')) {
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
