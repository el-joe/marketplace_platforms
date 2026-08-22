<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShippingCompanyStatus;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use App\Models\ShippingFallbackRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShippingCompanyController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total'     => ShippingCompany::count(),
            'active'    => ShippingCompany::where('status', ShippingCompanyStatus::Active)->count(),
            'pending'   => ShippingCompany::where('status', ShippingCompanyStatus::Pending)->count(),
            'suspended' => ShippingCompany::where('status', ShippingCompanyStatus::Suspended)->count(),
        ];

        $companies = ShippingCompany::with('country')
            ->withCount(['supervisors', 'agents'])
            ->latest()
            ->paginate(20);

        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'currency_code']);

        return view('admin.shipping-companies.index', compact('companies', 'stats', 'countries'));
    }

    public function create(): View
    {
        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'currency_code']);

        return view('admin.shipping-companies.create', compact('countries'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                                       => ['required', 'string', 'max:255'],
            'legal_name'                                 => ['nullable', 'string', 'max:255'],
            'country_id'                                 => ['nullable', 'exists:countries,id'],
            'contact_email'                              => ['required', 'email', 'max:255', 'unique:shipping_companies,contact_email'],
            'contact_phone'                               => ['nullable', 'string', 'max:30'],
            'served_countries'                            => ['nullable', 'array'],
            'served_countries.*'                          => ['exists:countries,id'],
            'status'                                       => ['required', 'in:pending,active,suspended'],
            'can_supervisors_receive_all_notifications'    => ['boolean'],
            'logo'                                         => ['nullable', 'image', 'max:2048'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('shipping-companies/logos', 'public');
        }

        $company = ShippingCompany::create([
            'name'                                       => $data['name'],
            'legal_name'                                 => $data['legal_name'] ?? null,
            'country_id'                                 => $data['country_id'] ?? null,
            'contact_email'                              => $data['contact_email'],
            'contact_phone'                               => $data['contact_phone'] ?? null,
            'served_countries'                            => $data['served_countries'] ?? null,
            'status'                                       => $data['status'],
            'can_supervisors_receive_all_notifications'    => $data['can_supervisors_receive_all_notifications'] ?? true,
            'logo_path'                                   => $logoPath,
            'approved_by_admin_id'                        => $data['status'] === 'active' ? auth('admin')->id() : null,
            'approved_at'                                  => $data['status'] === 'active' ? now() : null,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => __('admin.shipping_section.company_created'),
            'redirect' => route('admin.shipping-companies.show', $company->id),
        ], 201);
    }

    public function update(Request $request, ShippingCompany $shippingCompany): JsonResponse
    {
        $data = $request->validate([
            'name'                                       => ['required', 'string', 'max:255'],
            'legal_name'                                 => ['nullable', 'string', 'max:255'],
            'country_id'                                 => ['nullable', 'exists:countries,id'],
            'contact_email'                              => ['required', 'email', 'max:255',
                                                              'unique:shipping_companies,contact_email,' . $shippingCompany->id],
            'contact_phone'                               => ['nullable', 'string', 'max:30'],
            'served_countries'                            => ['nullable', 'array'],
            'served_countries.*'                          => ['exists:countries,id'],
            'can_supervisors_receive_all_notifications'    => ['boolean'],
            'logo'                                         => ['nullable', 'image', 'max:2048'],
            'remove_logo'                                 => ['boolean'],
        ]);

        $logoPath = $shippingCompany->logo_path;

        if ($request->boolean('remove_logo') && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $request->file('logo')->store('shipping-companies/logos', 'public');
        }

        $shippingCompany->update([
            'name'                                       => $data['name'],
            'legal_name'                                 => $data['legal_name'] ?? null,
            'country_id'                                 => $data['country_id'] ?? null,
            'contact_email'                              => $data['contact_email'],
            'contact_phone'                               => $data['contact_phone'] ?? null,
            'served_countries'                            => $data['served_countries'] ?? null,
            'can_supervisors_receive_all_notifications'    => $data['can_supervisors_receive_all_notifications'] ?? true,
            'logo_path'                                   => $logoPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.shipping_section.company_updated'),
        ]);
    }

    public function destroy(ShippingCompany $shippingCompany): JsonResponse
    {
        if ($shippingCompany->agents()->whereNull('deleted_at')->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('admin.shipping_section.company_has_agents'),
            ], 422);
        }

        if ($shippingCompany->supervisors()->whereNull('deleted_at')->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('admin.shipping_section.company_has_supervisors'),
            ], 422);
        }

        if ($shippingCompany->logo_path) {
            Storage::disk('public')->delete($shippingCompany->logo_path);
        }

        $shippingCompany->delete();

        return response()->json([
            'success'  => true,
            'message'  => __('admin.shipping_section.company_deleted'),
            'redirect' => route('admin.shipping-companies.index'),
        ]);
    }

    public function show(ShippingCompany $shippingCompany): View
    {
        $shippingCompany->load(['country', 'supervisors.country', 'agents' => fn ($q) => $q->limit(20)]);

        $servedIds = collect($shippingCompany->served_countries ?? [])
            ->push($shippingCompany->country_id)
            ->filter()
            ->unique()
            ->values();

        $countries = $servedIds->isNotEmpty()
            ? Country::whereIn('id', $servedIds)->orderBy('name_en')->get(['id', 'name_en', 'currency_code'])
            : Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'currency_code']);

        return view('admin.shipping-companies.show', compact('shippingCompany', 'countries'));
    }

    public function approve(ShippingCompany $shippingCompany): RedirectResponse
    {
        $shippingCompany->update([
            'status'              => ShippingCompanyStatus::Active,
            'approved_by_admin_id' => auth('admin')->id(),
            'approved_at'         => now(),
        ]);

        return back()->with('success', 'تم تفعيل شركة الشحن بنجاح.');
    }

    public function suspend(ShippingCompany $shippingCompany): RedirectResponse
    {
        $shippingCompany->update(['status' => ShippingCompanyStatus::Suspended]);

        return back()->with('success', 'تم إيقاف شركة الشحن.');
    }

    public function toggleSupervisorNotifications(ShippingCompanySupervisor $supervisor): JsonResponse
    {
        $supervisor->update([
            'receives_all_notifications' => ! $supervisor->receives_all_notifications,
        ]);

        return response()->json([
            'success' => true,
            'enabled' => $supervisor->receives_all_notifications,
            'message' => $supervisor->receives_all_notifications
                ? 'تم تفعيل الإشعارات للمشرف.'
                : 'تم إيقاف الإشعارات للمشرف.',
        ]);
    }

    // ── Store Supervisor ───────────────────────────────────────────────────

    public function storeSupervisor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipping_company_id' => ['required', 'exists:shipping_companies,id'],
            'country_id'          => [
                'nullable',
                'exists:countries,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if (! $value) {
                        return;
                    }

                    $company = ShippingCompany::find($request->input('shipping_company_id'));
                    if (! $company) {
                        return;
                    }

                    $allowed = collect($company->served_countries ?? [])
                        ->push($company->country_id)
                        ->filter()
                        ->unique();

                    if ($allowed->isEmpty()) {
                        return;
                    }

                    if (! $allowed->contains($value)) {
                        $fail('The selected country is not in this company\'s served countries.');
                    }
                },
            ],
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255', 'unique:shipping_company_supervisors,email'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'permissions'         => ['required', 'array', 'min:1'],
            'permissions.*'       => ['string', 'in:manage_agents,view_orders,assign_orders,view_reports'],
            'is_active'           => ['boolean'],
        ]);

        $tempPassword = Str::password(12, letters: true, numbers: true, symbols: false);

        $supervisor = ShippingCompanySupervisor::create([
            'shipping_company_id' => $data['shipping_company_id'],
            'country_id'          => $data['country_id'] ?? null,
            'name'                => $data['name'],
            'email'               => $data['email'],
            'phone'               => $data['phone'] ?? null,
            'password'            => $tempPassword,   // model casts hash automatically
            'permissions'         => $data['permissions'],
            'is_active'           => $data['is_active'] ?? true,
            'receives_all_notifications' => false,
        ]);

        // In production a SupervisorInviteMail would be dispatched here.
        // Returning the temp password so the admin can relay it manually.

        return response()->json([
            'success'       => true,
            'message'       => 'Supervisor created successfully.',
            'temp_password' => $tempPassword,
            'data'          => [
                'id'          => $supervisor->id,
                'name'        => $supervisor->name,
                'email'       => $supervisor->email,
                'country'     => $supervisor->country?->name_en,
                'permissions' => $supervisor->permissions,
            ],
        ], 201);
    }

    // ── Update Supervisor ──────────────────────────────────────────────────

    public function updateSupervisor(Request $request, ShippingCompanySupervisor $supervisor): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255',
                              'unique:shipping_company_supervisors,email,' . $supervisor->id],
            'phone'       => ['nullable', 'string', 'max:30'],
            'country_id'  => [
                'nullable',
                'exists:countries,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($supervisor) {
                    if (!$value) return;
                    $company = $supervisor->company;
                    $allowed = collect($company->served_countries ?? [])
                        ->push($company->country_id)
                        ->filter()
                        ->unique();
                    if ($allowed->isNotEmpty() && !$allowed->contains($value)) {
                        $fail('The selected country is not in this company\'s served countries.');
                    }
                },
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'in:manage_agents,view_orders,assign_orders,view_reports'],
            'is_active'   => ['boolean'],
            'password'    => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updateData = [
            'name'        => $data['name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'] ?? null,
            'country_id'  => $data['country_id'] ?? null,
            'permissions' => $data['permissions'],
            'is_active'   => $data['is_active'] ?? $supervisor->is_active,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $supervisor->update($updateData);

        return response()->json([
            'success' => true,
            'message' => __('admin.shipping_section.supervisor_updated'),
            'data'    => [
                'id'          => $supervisor->id,
                'name'        => $supervisor->name,
                'email'       => $supervisor->email,
                'country'     => $supervisor->fresh('country')->country?->name_en,
                'permissions' => $supervisor->permissions,
                'is_active'   => $supervisor->is_active,
            ],
        ]);
    }

    public function resetSupervisorPassword(ShippingCompanySupervisor $supervisor): JsonResponse
    {
        $tempPassword = Str::password(12, letters: true, numbers: true, symbols: false);

        $supervisor->update(['password' => $tempPassword]);

        return response()->json([
            'success'       => true,
            'message'       => __('admin.shipping_section.supervisor_password_reset'),
            'temp_password' => $tempPassword,
        ]);
    }

    // ── Destroy Supervisor ─────────────────────────────────────────────────

    public function destroySupervisor(ShippingCompanySupervisor $supervisor): JsonResponse
    {
        $supervisor->delete();   // SoftDeletes trait handles this

        return response()->json(['success' => true, 'message' => 'Supervisor removed.']);
    }

    // ── Fallback Rules ─────────────────────────────────────────────────────

    public function fallbackRules(): View
    {
        $rules = ShippingFallbackRule::with(['unservedCity', 'fallbackShippingCompany'])
            ->orderBy('priority')
            ->paginate(30);

        $cities    = City::orderBy('name_en')->get(['id', 'name_en', 'name_ar']);
        $companies = ShippingCompany::active()->orderBy('name')->get(['id', 'name']);

        return view('admin.shipping-companies.fallback-rules', compact('rules', 'cities', 'companies'));
    }

    public function storeFallbackRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'unserved_city_id'              => ['required', 'exists:cities,id'],
            'fallback_shipping_company_id'  => ['required', 'exists:shipping_companies,id'],
            'priority'                      => ['required', 'integer', 'min:1'],
        ]);

        ShippingFallbackRule::create($data);

        return back()->with('success', 'تم إضافة قاعدة الاحتياط.');
    }

    public function destroyFallbackRule(ShippingFallbackRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'تم حذف قاعدة الاحتياط.');
    }
}
