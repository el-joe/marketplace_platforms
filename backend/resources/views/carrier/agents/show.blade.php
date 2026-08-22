@extends('layouts.carrier')

@section('title', $agent->name)

@section('content')

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <a href="{{ route('carrier.agents.index') }}"
           class="text-indigo-600 hover:underline text-sm">← {{ __('carrier.agents.back') }}</a>
        <h1 class="text-2xl font-black text-gray-900 mt-2">{{ $agent->name }}</h1>
        <p class="text-sm text-gray-500">{{ $agent->email }} · {{ $agent->phone }}</p>
    </div>
    <div class="flex items-center gap-3">
        @php $sc = ['active'=>'emerald','on_shift'=>'blue','inactive'=>'gray','suspended'=>'red'][$agent->status->value] ?? 'gray'; @endphp
        <span class="px-3 py-1 rounded-full text-xs font-bold bg-{{ $sc }}-100 text-{{ $sc }}-700 capitalize">
            {{ $agent->status->value }}
        </span>
        @if(auth('shipping_supervisor')->user()->hasPermission('manage_agents'))
        <button type="button" id="btn-edit-agent"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-lg text-sm transition">
            {{ __('carrier.agents.edit') }}
        </button>
        <button type="button" id="btn-reset-password"
                data-id="{{ $agent->id }}"
                data-name="{{ $agent->name }}"
                class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-lg text-sm transition">
            {{ __('carrier.agents.reset_password_btn') }}
        </button>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Agent info --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3 text-sm">
        <h2 class="font-bold text-gray-900 mb-4">{{ __('carrier.agents.details') }}</h2>
        <div class="flex justify-between"><span class="text-gray-500">{{ __('carrier.agents.vehicle_type') }}</span><span class="font-medium capitalize">{{ $agent->vehicle_type->value }}</span></div>
        <div class="flex justify-between"><span class="text-gray-500">{{ __('carrier.agents.current_zone') }}</span><span class="font-medium">{{ $agent->zone?->name ?? '—' }}</span></div>
        <div class="flex justify-between"><span class="text-gray-500">{{ __('carrier.agents.rating') }}</span><span class="font-medium">{{ number_format($agent->rating_avg, 1) }}</span></div>
        <div class="flex justify-between"><span class="text-gray-500">{{ __('carrier.agents.total_deliveries') }}</span><span class="font-medium">{{ $agent->total_deliveries }}</span></div>
    </div>

    {{-- Zone assignment --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-bold text-gray-900 mb-4">{{ __('carrier.agents.zone_assignment') }}</h2>

        <form id="zone-assign-form" class="flex gap-3 items-end flex-wrap">
            @csrf
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">{{ __('carrier.agents.zone') }}</label>
                <select name="zone_id" id="zone-select"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">— {{ __('carrier.agents.no_zone') }} —</option>
                    @foreach($zones as $zone)
                        @php $full = $zone->max_active_agents && $zone->agents_count >= $zone->max_active_agents && $zone->id !== $agent->zone_id; @endphp
                        <option value="{{ $zone->id }}"
                                {{ $agent->zone_id === $zone->id ? 'selected' : '' }}
                                {{ $full ? 'disabled' : '' }}>
                            {{ $zone->name }}
                            @if($zone->max_active_agents)
                                ({{ $zone->agents_count }}/{{ $zone->max_active_agents }}){{ $full ? ' — Full' : '' }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" id="zone-submit-btn"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-lg text-sm transition">
                {{ __('carrier.agents.update_zone') }}
            </button>
        </form>

        <div id="zone-result" class="mt-3 text-sm hidden"></div>
    </div>
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
@php $agentJson = [
    'id'             => $agent->id,
    'name'           => $agent->name,
    'phone'          => $agent->phone,
    'vehicleType'    => $agent->vehicle_type->value,
    'nationalId'     => $agent->national_id ?? '',
    'vehiclePlate'   => $agent->vehicle_plate ?? '',
    'emergencyName'  => $agent->emergency_contact_name ?? '',
    'emergencyPhone' => $agent->emergency_contact_phone ?? '',
]; @endphp
<script>
document.getElementById('zone-assign-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('zone-submit-btn');
    btn.disabled = true; btn.textContent = '...';

    const res  = await fetch('{{ route('carrier.agents.assign-zone', $agent->id) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            _method: 'PATCH',
            zone_id: document.getElementById('zone-select').value || null,
        }),
    });

    const data = await res.json();
    const resultEl = document.getElementById('zone-result');
    resultEl.classList.remove('hidden');

    if (data.success) {
        resultEl.className = 'mt-3 text-sm text-emerald-600 font-medium';
        resultEl.textContent = data.message;
        setTimeout(() => location.reload(), 1000);
    } else {
        resultEl.className = 'mt-3 text-sm text-red-600 font-medium';
        resultEl.textContent = data.message;
        btn.disabled = false;
        btn.textContent = '{{ __('carrier.agents.update_zone') }}';
    }
});

// Edit modal — pre-populated from server data
const editModal  = document.getElementById('edit-agent-modal');
const editForm   = document.getElementById('edit-agent-form');
const submitBtn  = document.getElementById('edit-agent-submit');
const errorsBox  = document.getElementById('edit-agent-errors');
const BASE_URL   = @json(url('carrier/agents'));
const CSRF       = () => document.querySelector('meta[name="csrf-token"]').content;

const AGENT = @json($agentJson);

function openEditModal() {
    editForm?.reset();
    errorsBox?.classList.add('hidden');

    document.getElementById('edit-agent-id').value          = AGENT.id;
    document.getElementById('edit-name').value              = AGENT.name;
    document.getElementById('edit-phone').value             = AGENT.phone;
    document.getElementById('edit-vehicle-type').value      = AGENT.vehicleType;
    document.getElementById('edit-national-id').value       = AGENT.nationalId;
    document.getElementById('edit-vehicle-plate').value     = AGENT.vehiclePlate;
    document.getElementById('edit-emergency-name').value    = AGENT.emergencyName;
    document.getElementById('edit-emergency-phone').value   = AGENT.emergencyPhone;

    editModal?.classList.remove('hidden');
}

document.getElementById('btn-edit-agent')?.addEventListener('click', openEditModal);

['edit-agent-close', 'edit-agent-cancel'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => editModal?.classList.add('hidden'));
});

function passwordsMatch() {
    const pwd     = document.getElementById('edit-password').value;
    const confirm = document.getElementById('edit-password-confirm').value;
    if (pwd && pwd !== confirm) {
        errorsBox.textContent = @json(__('carrier.agents.password_mismatch'));
        errorsBox.classList.remove('hidden');
        return false;
    }
    return true;
}

submitBtn?.addEventListener('click', async () => {
    if (!passwordsMatch()) return;
    submitBtn.disabled = true;
    submitBtn.textContent = '...';

    try {
        const res  = await fetch(`${BASE_URL}/${AGENT.id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json' },
            body: new FormData(editForm),
        });
        const data = await res.json();

        if (data.success) {
            window.Toast?.success(data.message);
            setTimeout(() => location.reload(), 900);
        } else {
            const errors = data.errors
                ? Object.values(data.errors).flat().join('\n')
                : (data.message ?? @json(__('carrier.errors.generic')));
            errorsBox.textContent = errors;
            errorsBox.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = @json(__('carrier.common.save_changes'));
        }
    } catch {
        errorsBox.textContent = @json(__('carrier.errors.generic'));
        errorsBox.classList.remove('hidden');
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

    document.getElementById('btn-reset-password')?.addEventListener('click', function () {
        openModal(this.dataset.id, this.dataset.name);
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
            const res  = await fetch(`${BASE_URL}/${agentId}/reset-password`, {
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
