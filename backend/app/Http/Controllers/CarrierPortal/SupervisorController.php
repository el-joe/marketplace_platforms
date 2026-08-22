<?php

namespace App\Http\Controllers\CarrierPortal;

use App\Http\Controllers\Controller;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    private const ALL_PERMISSIONS = ['manage_agents', 'view_orders', 'assign_orders', 'view_reports'];

    public function index(): View
    {
        $this->requireOwner();

        $supervisor = auth('shipping_supervisor')->user();
        $supervisors = ShippingCompanySupervisor::where('shipping_company_id', $supervisor->shipping_company_id)
            ->latest()
            ->paginate(20);

        return view('carrier.supervisors.index', compact('supervisors'));
    }

    public function create(): View
    {
        $this->requireOwner();

        return view('carrier.supervisors.create', ['allPermissions' => self::ALL_PERMISSIONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'unique:shipping_company_supervisors,email'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['in:' . implode(',', self::ALL_PERMISSIONS)],
        ]);

        $supervisor = auth('shipping_supervisor')->user();

        ShippingCompanySupervisor::create([
            ...$data,
            'shipping_company_id' => $supervisor->shipping_company_id,
        ]);

        return redirect()->route('carrier.supervisors.index')
            ->with('success', __('carrier.supervisors.added_success'));
    }

    public function edit(ShippingCompanySupervisor $supervisor): View
    {
        $this->requireOwner();
        $this->ensureSameCompany($supervisor);

        return view('carrier.supervisors.edit', [
            'editSupervisor' => $supervisor,
            'allPermissions' => self::ALL_PERMISSIONS,
        ]);
    }

    public function update(Request $request, ShippingCompanySupervisor $supervisor): RedirectResponse
    {
        $this->requireOwner();
        $this->ensureSameCompany($supervisor);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['in:' . implode(',', self::ALL_PERMISSIONS)],
            'is_active'   => ['boolean'],
        ]);

        $supervisor->update($data);

        return redirect()->route('carrier.supervisors.index')
            ->with('success', __('carrier.supervisors.updated_success'));
    }

    public function destroy(ShippingCompanySupervisor $supervisor): RedirectResponse
    {
        $this->requireOwner();
        $this->ensureSameCompany($supervisor);

        abort_if(
            $supervisor->id === auth('shipping_supervisor')->id(),
            403,
            __('carrier.errors.cannot_delete_own_account')
        );

        $supervisor->delete();

        return redirect()->route('carrier.supervisors.index')
            ->with('success', __('carrier.supervisors.deleted_success'));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    // Owner = supervisor with all permissions (manage_agents is the gate we use)
    private function requireOwner(): void
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission('manage_agents'), 403, __('carrier.errors.owner_only'));
    }

    private function ensureSameCompany(ShippingCompanySupervisor $target): void
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless(
            $target->shipping_company_id === $supervisor->shipping_company_id,
            403
        );
    }
}
