@extends('layouts.carrier')

@section('title', __('carrier.agents.title'))

@section('content')

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.agents.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('carrier.agents.subtitle') }}</p>
    </div>
    @if(auth('shipping_supervisor')->user()->hasPermission('manage_agents'))
    <a href="{{ route('carrier.agents.create') }}"
       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-4 py-2 rounded-lg transition">
        + {{ __('carrier.agents.add_agent') }}
    </a>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($agents->isEmpty())
    <div class="py-16 text-center text-gray-400 text-sm">{{ __('carrier.agents.no_agents') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold">{{ __('carrier.agents.name') }}</th>
                    <th class="px-6 py-3 text-left font-semibold">{{ __('carrier.common.phone') }}</th>
                    <th class="px-6 py-3 text-left font-semibold">{{ __('carrier.agents.vehicle_type') }}</th>
                    <th class="px-6 py-3 text-left font-semibold">{{ __('carrier.agents.zone') }}</th>
                    <th class="px-6 py-3 text-left font-semibold">{{ __('carrier.assignments.status') }}</th>
                    <th class="px-6 py-3 text-left font-semibold">{{ __('carrier.agents.avg_rating') }}</th>
                    <th class="px-4 py-3 text-left font-semibold">{{ __('carrier.common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($agents as $agent)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <div class="font-medium text-gray-900">{{ $agent->name }}</div>
                        <div class="text-xs text-gray-400">{{ $agent->email }}</div>
                    </td>
                    <td class="px-6 py-3 text-gray-600">{{ $agent->phone }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $agent->vehicle_type->label() }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $agent->zone?->name ?? '—' }}</td>
                    <td class="px-6 py-3">
                        @php
                            $sc = ['active'=>'emerald','suspended'=>'red','inactive'=>'gray','on_shift'=>'blue'][$agent->status->value] ?? 'gray';
                            $sl = [
                                'active'    => __('carrier.agents.active'),
                                'suspended' => __('carrier.agents.suspended'),
                                'inactive'  => __('carrier.agents.inactive'),
                                'on_shift'  => __('carrier.agents.on_shift'),
                            ][$agent->status->value] ?? $agent->status->value;
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ $sl }}</span>
                    </td>
                    <td class="px-6 py-3 text-gray-600">{{ number_format($agent->rating_avg, 1) }} ⭐</td>
                    <td class="px-4 py-3 min-w-[200px]">
                        <div class="flex flex-wrap gap-1.5 items-center">

                            {{-- View --}}
                            <a href="{{ route('carrier.agents.show', $agent->id) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold
                                      bg-gray-100 text-gray-700 hover:bg-gray-200 transition whitespace-nowrap">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                {{ __('carrier.common.view') }}
                            </a>

                            @if(auth('shipping_supervisor')->user()->hasPermission('manage_agents'))

                            {{-- Edit --}}
                            <button type="button"
                                    class="btn-edit-agent inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold
                                           bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition whitespace-nowrap"
                                    data-id="{{ $agent->id }}"
                                    data-name="{{ $agent->name }}"
                                    data-phone="{{ $agent->phone }}"
                                    data-vehicle-type="{{ $agent->vehicle_type->value }}"
                                    data-national-id="{{ $agent->national_id ?? '' }}"
                                    data-vehicle-plate="{{ $agent->vehicle_plate ?? '' }}"
                                    data-emergency-name="{{ $agent->emergency_contact_name ?? '' }}"
                                    data-emergency-phone="{{ $agent->emergency_contact_phone ?? '' }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                {{ __('carrier.agents.edit') }}
                            </button>

                            {{-- Reset Password --}}
                            <button type="button"
                                    class="btn-reset-password inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold
                                           bg-amber-50 text-amber-700 hover:bg-amber-100 transition whitespace-nowrap"
                                    data-id="{{ $agent->id }}"
                                    data-name="{{ $agent->name }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                {{ __('carrier.agents.reset_password_btn') }}
                            </button>

                            {{-- Suspend / Activate --}}
                            @if($agent->status === \App\Enums\DeliveryAgentStatus::Suspended)
                            <form method="POST" action="{{ route('carrier.agents.activate', $agent->id) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold
                                               bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition whitespace-nowrap">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ __('carrier.agents.activate') }}
                                </button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('carrier.agents.suspend', $agent->id) }}" class="inline"
                                  onsubmit="return confirm('{{ __('carrier.agents.suspend_confirm', ['name' => $agent->name]) }}')">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold
                                               bg-red-50 text-red-600 hover:bg-red-100 transition whitespace-nowrap">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                    {{ __('carrier.agents.suspend') }}
                                </button>
                            </form>
                            @endif

                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $agents->links() }}
    </div>
    @endif
</div>

@if(auth('shipping_supervisor')->user()->hasPermission('manage_agents'))
<div id="edit-agent-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">{{ __('carrier.agents.edit_agent') }}</h3>
            <button type="button" id="edit-agent-close"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <form id="edit-agent-form" class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">
            @csrf
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" id="edit-agent-id" value="">

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        {{ __('carrier.common.full_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="edit-name" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        {{ __('carrier.common.phone') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="phone" id="edit-phone" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        {{ __('carrier.agents.vehicle_type') }} <span class="text-red-500">*</span>
                    </label>
                    <select name="vehicle_type" id="edit-vehicle-type" required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                        @foreach(['motorcycle','car','van','bicycle'] as $vt)
                            <option value="{{ $vt }}">{{ __('carrier.vehicle_types.' . $vt) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        {{ __('carrier.agents.create.national_id') }}
                    </label>
                    <input type="text" name="national_id" id="edit-national-id"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        {{ __('carrier.agents.vehicle_plate') }}
                    </label>
                    <input type="text" name="vehicle_plate" id="edit-vehicle-plate"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                    {{ __('carrier.agents.emergency_contact') }}
                </h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                            {{ __('carrier.agents.emergency_name') }}
                        </label>
                        <input type="text" name="emergency_contact_name" id="edit-emergency-name"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                            {{ __('carrier.agents.emergency_phone') }}
                        </label>
                        <input type="text" name="emergency_contact_phone" id="edit-emergency-phone"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                    {{ __('carrier.agents.change_password') }}
                </h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                            {{ __('carrier.agents.new_password') }}
                        </label>
                        <input type="password" name="password" id="edit-password" minlength="8"
                               placeholder="{{ __('carrier.agents.password_blank_hint') }}"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                            {{ __('carrier.agents.confirm_password') }}
                        </label>
                        <input type="password" name="password_confirmation" id="edit-password-confirm" minlength="8"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">{{ __('carrier.agents.password_blank_hint') }}</p>
            </div>

            <div id="edit-agent-errors" class="hidden bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700"></div>
        </form>

        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
            <button type="button" id="edit-agent-cancel"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-5 py-2.5 rounded-lg text-sm transition">
                {{ __('carrier.common.cancel') }}
            </button>
            <button type="button" id="edit-agent-submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-lg text-sm transition">
                {{ __('carrier.common.save_changes') }}
            </button>
        </div>
    </div>
</div>
@endif

{{-- ── Reset Agent Password Modal ──────────────────────────────────────── --}}
@if(auth('shipping_supervisor')->user()->hasPermission('manage_agents'))
<div id="reset-password-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-lg font-bold text-gray-900">{{ __('carrier.agents.reset_password_title') }}</h3>
                <p id="reset-agent-name" class="text-sm text-gray-500 mt-0.5"></p>
            </div>
            <button type="button" id="reset-password-close"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <form id="reset-password-form" class="px-6 py-5 space-y-4">
            @csrf
            <input type="hidden" id="reset-agent-id" value="">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    {{ __('carrier.agents.new_password') }} <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" id="reset-password-field"
                       required minlength="8"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    {{ __('carrier.common.confirm_password') }} <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password_confirmation" id="reset-password-confirm"
                       required minlength="8"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div id="reset-password-error"
                 class="hidden bg-red-50 border border-red-200 rounded-lg px-4 py-2.5 text-sm text-red-700">
            </div>

            <div id="reset-password-success"
                 class="hidden bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2.5 text-sm text-emerald-700">
            </div>
        </form>

        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
            <button type="button" id="reset-password-cancel"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-5 py-2.5 rounded-lg text-sm transition">
                {{ __('carrier.common.cancel') }}
            </button>
            <button type="button" id="reset-password-submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-lg text-sm transition">
                {{ __('carrier.agents.reset_password_btn') }}
            </button>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
const editModal   = document.getElementById('edit-agent-modal');
const editForm    = document.getElementById('edit-agent-form');
const submitBtn   = document.getElementById('edit-agent-submit');
const errorsBox   = document.getElementById('edit-agent-errors');
const BASE_URL    = @json(url('carrier/agents'));
const CSRF        = () => document.querySelector('meta[name="csrf-token"]').content;

function openEditModal(data) {
    editForm.reset();
    errorsBox.classList.add('hidden');

    document.getElementById('edit-agent-id').value          = data.id;
    document.getElementById('edit-name').value              = data.name;
    document.getElementById('edit-phone').value             = data.phone;
    document.getElementById('edit-vehicle-type').value      = data.vehicleType;
    document.getElementById('edit-national-id').value       = data.nationalId;
    document.getElementById('edit-vehicle-plate').value     = data.vehiclePlate;
    document.getElementById('edit-emergency-name').value    = data.emergencyName;
    document.getElementById('edit-emergency-phone').value   = data.emergencyPhone;

    editModal.classList.remove('hidden');
}

document.querySelectorAll('.btn-edit-agent').forEach(btn => {
    btn.addEventListener('click', () => openEditModal({
        id:             btn.dataset.id,
        name:           btn.dataset.name,
        phone:          btn.dataset.phone,
        vehicleType:    btn.dataset.vehicleType,
        nationalId:     btn.dataset.nationalId,
        vehiclePlate:   btn.dataset.vehiclePlate,
        emergencyName:  btn.dataset.emergencyName,
        emergencyPhone: btn.dataset.emergencyPhone,
    }));
});

['edit-agent-close', 'edit-agent-cancel'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => editModal.classList.add('hidden'));
});

function passwordsMatch() {
    const pwd     = document.getElementById('edit-password').value;
    const confirm = document.getElementById('edit-password-confirm').value;
    if (pwd && pwd !== confirm) {
        showErrors(@json(__('carrier.agents.password_mismatch')));
        return false;
    }
    return true;
}

function showErrors(msg) {
    errorsBox.textContent = msg;
    errorsBox.classList.remove('hidden');
}

submitBtn.addEventListener('click', async () => {
    if (!passwordsMatch()) return;

    const agentId = document.getElementById('edit-agent-id').value;
    submitBtn.disabled = true;
    submitBtn.textContent = '...';

    const formData = new FormData(editForm);

    try {
        const res  = await fetch(`${BASE_URL}/${agentId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json' },
            body: formData,
        });

        const data = await res.json();

        if (data.success) {
            editModal.classList.add('hidden');
            window.Toast?.success(data.message);
            setTimeout(() => location.reload(), 900);
        } else {
            const errors = data.errors
                ? Object.values(data.errors).flat().join('\n')
                : (data.message ?? @json(__('carrier.errors.generic')));
            showErrors(errors);
            submitBtn.disabled = false;
            submitBtn.textContent = @json(__('carrier.common.save_changes'));
        }
    } catch {
        showErrors(@json(__('carrier.errors.generic')));
        submitBtn.disabled = false;
        submitBtn.textContent = @json(__('carrier.common.save_changes'));
    }
});

// ── Reset Password Modal ──────────────────────────────────────────────────
(function () {
    const modal     = document.getElementById('reset-password-modal');
    const form      = document.getElementById('reset-password-form');
    const resetSubmitBtn = document.getElementById('reset-password-submit');
    const errorBox  = document.getElementById('reset-password-error');
    const successBox = document.getElementById('reset-password-success');
    const RESET_BASE_URL  = @json(url('carrier/agents'));

    function openModal(id, name) {
        form.reset();
        errorBox.classList.add('hidden');
        successBox.classList.add('hidden');
        resetSubmitBtn.disabled = false;
        resetSubmitBtn.textContent = @json(__('carrier.agents.reset_password_btn'));

        document.getElementById('reset-agent-id').value  = id;
        document.getElementById('reset-agent-name').textContent = name;

        modal.classList.remove('hidden');
        document.getElementById('reset-password-field').focus();
    }

    document.querySelectorAll('.btn-reset-password').forEach(btn => {
        btn.addEventListener('click', () => openModal(btn.dataset.id, btn.dataset.name));
    });

    ['reset-password-close', 'reset-password-cancel'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', () => modal.classList.add('hidden'));
    });

    resetSubmitBtn?.addEventListener('click', async () => {
        const agentId = document.getElementById('reset-agent-id').value;
        const pwd     = document.getElementById('reset-password-field').value;
        const confirm = document.getElementById('reset-password-confirm').value;

        errorBox.classList.add('hidden');
        successBox.classList.add('hidden');

        if (!pwd || pwd.length < 8) {
            errorBox.textContent = @json(__('carrier.agents.password_min_length'));
            errorBox.classList.remove('hidden');
            return;
        }
        if (pwd !== confirm) {
            errorBox.textContent = @json(__('carrier.agents.password_mismatch'));
            errorBox.classList.remove('hidden');
            return;
        }

        resetSubmitBtn.disabled = true;
        resetSubmitBtn.textContent = '...';

        const formData = new FormData(form);

        try {
            const res  = await fetch(`${RESET_BASE_URL}/${agentId}/reset-password`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json' },
                body: formData,
            });

            const data = await res.json();

            if (data.success) {
                successBox.textContent = data.message;
                successBox.classList.remove('hidden');
                form.reset();
                resetSubmitBtn.disabled = true;
                setTimeout(() => modal.classList.add('hidden'), 1500);
            } else {
                const errors = data.errors
                    ? Object.values(data.errors).flat().join('\n')
                    : (data.message ?? @json(__('carrier.errors.generic')));
                errorBox.textContent = errors;
                errorBox.classList.remove('hidden');
                resetSubmitBtn.disabled = false;
                resetSubmitBtn.textContent = @json(__('carrier.agents.reset_password_btn'));
            }
        } catch {
            errorBox.textContent = @json(__('carrier.errors.generic'));
            errorBox.classList.remove('hidden');
            resetSubmitBtn.disabled = false;
            resetSubmitBtn.textContent = @json(__('carrier.agents.reset_password_btn'));
        }
    });
})();
</script>
@endpush
