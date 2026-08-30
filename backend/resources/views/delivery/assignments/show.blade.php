@extends('layouts.delivery')

@section('title', __('delivery.assignments.order_number_prefix') . ($assignment->subOrder?->sub_order_number ?? substr($assignment->id, 0, 8)))

@section('header-left')
    <a href="{{ route('delivery.assignments.index') }}" class="flex items-center gap-1 text-slate-300 text-sm -ml-1 p-1">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        {{ __('delivery.assignments.back') }}
    </a>
@endsection

@section('content')

    @php
        /** @var \App\Models\DeliveryAssignment $assignment */
        $isActive = in_array($assignment->status, [
            \App\Enums\DeliveryAssignmentStatus::Assigned,
            \App\Enums\DeliveryAssignmentStatus::Accepted,
            \App\Enums\DeliveryAssignmentStatus::PickedUp,
        ], true);
        $chipClass = 'chip-' . $assignment->status->value;
        $order = $assignment->subOrder?->order;
        $items = $assignment->subOrder?->items ?? collect();
        $customer = $order?->customer;
        $isCod = $order?->payment_method === 'cod';
        $expectedCodCents = $isCod ? (int) ($order->total ?? 0) : 0;
    @endphp

    {{-- ── Status + Order Number ───────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold">#{{ $assignment->subOrder?->sub_order_number ?? substr($assignment->id, 0, 8) }}
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('delivery.assignments.assigned') }} {{ $assignment->assigned_at?->diffForHumans() }}</p>
        </div>
        <span
            class="chip {{ $chipClass }} text-sm px-3 py-1">{{ $assignment->status->label() }}</span>
    </div>

    {{-- ── Map: pickup → delivery ──────────────────────────────────────────────── --}}
    @if($assignment->subOrder?->order)
        <div class="d-card mb-4 p-0 overflow-hidden rounded-2xl" style="height: 180px;" id="route-map">
            <div class="flex items-center justify-center h-full text-slate-500 text-sm" id="map-placeholder">
                📍 {{ __('delivery.assignments.loading_map') }}
            </div>
        </div>
    @endif

    {{-- ── Addresses ────────────────────────────────────────────────────────────── --}}
    <div class="d-card mb-3">
        <div class="space-y-4">
            {{-- Pickup --}}
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-yellow-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="text-yellow-400 text-xs font-bold">P</span>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">{{ __('delivery.assignments.pickup') }}</p>
                    <p class="text-sm text-slate-200 mt-0.5">
                        {{ $assignment->shipment?->subOrder?->vendor?->store_name ?? __('delivery.assignments.vendor_warehouse') }}
                    </p>
                </div>
            </div>

            <div class="ml-4 border-l border-dashed border-slate-600 pl-7 -mt-1 -mb-1 h-5"></div>

            {{-- Delivery --}}
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="text-green-400 text-xs font-bold">D</span>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">{{ __('delivery.assignments.deliver_to') }}</p>
                    @if($order?->shipping_address_snapshot)
                                <p class="text-sm text-slate-200 mt-0.5">
                                    @php
                                        $snap    = is_array($order->shipping_address_snapshot)
                                            ? $order->shipping_address_snapshot
                                            : [];
                                        $rawCity = $snap['city'] ?? null;
                                        $snapCity = is_array($rawCity)
                                            ? ($rawCity[app()->getLocale()] ?? $rawCity['en'] ?? $rawCity['ar'] ?? null)
                                            : $rawCity;
                                    @endphp
                                    {{ implode(', ', array_filter([
                                        $snap['street_address'] ?? $snap['street'] ?? null,
                                        $snapCity,
                                        $snap['country'] ?? null,
                                    ])) }}
                                </p>
                    @else
                        <p class="text-sm text-slate-500 mt-0.5">{{ __('delivery.assignments.address_unavailable') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Customer Contact ─────────────────────────────────────────────────────── --}}
    @if($customer?->phone && $isActive)
        <div class="d-card mb-3 flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-400">{{ __('delivery.assignments.customer') }}</p>
                <p class="text-sm font-semibold">{{ $customer->name }}</p>
            </div>
            <a href="tel:{{ $customer->phone }}"
                class="flex items-center gap-2 bg-green-600 text-white rounded-xl px-4 py-2.5 text-sm font-semibold">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                {{ __('delivery.assignments.call') }}
            </a>
        </div>
    @endif

    {{-- ── Items ────────────────────────────────────────────────────────────────── --}}
    <div class="d-card mb-4">
        <details>
            <summary class="flex items-center justify-between cursor-pointer list-none">
                <span class="text-sm font-semibold">
                    {{ __('delivery.assignments.items_count') }}
                    <span class="text-slate-400 font-normal">({{ $items->count() }})</span>
                </span>
                <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </summary>
            <div class="mt-3 space-y-2 border-t border-slate-700 pt-3">
                @forelse($items as $item)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-300">{{ $item->productVariant?->product?->name_en ?? __('delivery.assignments.product') }}</span>
                        <span class="text-slate-400">× {{ $item->quantity }}</span>
                    </div>
                @empty
                    <p class="text-slate-500 text-sm">{{ __('delivery.assignments.no_items_found') }}</p>
                @endforelse
            </div>
        </details>
    </div>

    {{-- ── Action Buttons ───────────────────────────────────────────────────────── --}}
    @if($isActive)
        <div x-data="assignmentActions()" class="space-y-3 pb-6">

            {{-- ASSIGNED: Accept button --}}
            @if($assignment->status === \App\Enums\DeliveryAssignmentStatus::Assigned)
                <button type="button" @click="accept()" :disabled="loading" class="btn-action btn-yellow">
                    <span x-text="loading ? '{{ __('delivery.assignments.processing') }}' : '✓ {{ __('delivery.assignments.accept') }}'"></span>
                </button>
            @endif

            {{-- ACCEPTED: Mark Picked Up --}}
            @if($assignment->status === \App\Enums\DeliveryAssignmentStatus::Accepted)
                <button type="button" @click="pickUp()" :disabled="loading" class="btn-action btn-blue">
                    <span x-text="loading ? '{{ __('delivery.assignments.getting_location') }}' : '📦 {{ __('delivery.dashboard.mark_picked_up') }}'"></span>
                </button>
            @endif

            {{-- PICKED_UP: COD Card + OTP + Deliver --}}
            @if($assignment->status === \App\Enums\DeliveryAssignmentStatus::PickedUp)

                {{-- COD Collection Card --}}
                @if($isCod)
                    <div class="d-card mb-3 border border-yellow-500/40 bg-yellow-500/5">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-yellow-400 text-lg">💵</span>
                            <p class="text-sm font-bold text-yellow-300">{{ __('delivery.cod.title') }} (COD)</p>
                        </div>
                        <div class="bg-slate-800 rounded-xl p-4 mb-3 text-center">
                            <p class="text-xs text-slate-400 mb-1">{{ __('delivery.cod.expected_amount') }}</p>
                            <p class="text-3xl font-extrabold text-yellow-300">
                                {{ number_format($expectedCodCents, 2) }}
                                <span class="text-sm font-normal text-slate-400">{{ $order->currency }}</span>
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-400 uppercase tracking-wide block mb-1">
                                {{ __('delivery.cod.collected_amount') }}
                            </label>
                            <input type="number" id="cod-amount-input" x-model.number="codAmountCollected"
                                min="0" step="1"
                                placeholder="{{ number_format($expectedCodCents, 2) }}"
                                class="w-full bg-slate-700 text-slate-100 rounded-xl p-3 text-center text-xl font-bold border-2 border-slate-600 focus:border-yellow-400 focus:outline-none transition-colors"
                                inputmode="decimal">
                            <p class="text-xs text-slate-500 mt-1 text-center">
                                {{ __('delivery.cod.enter_collected_amount_hint') }}: {{ number_format($expectedCodCents, 2) }}
                            </p>
                        </div>

                        {{-- Discrepancy note — shown when amount differs by more than 5% --}}
                        <div x-show="showDiscrepancyNote" x-cloak class="mt-3 border-t border-yellow-500/30 pt-3">
                            <label class="text-xs font-semibold text-yellow-400 block mb-1">
                                {{ __('delivery.cod.discrepancy_reason') }}
                            </label>
                            <textarea x-model="discrepancyNote" rows="2"
                                class="w-full bg-slate-700 text-slate-100 rounded-xl p-3 text-sm border-2 border-yellow-500/50 focus:border-yellow-400 focus:outline-none resize-none"
                                placeholder="{{ __('delivery.cod.discrepancy_placeholder') }}"></textarea>
                        </div>
                    </div>
                @endif

                <div class="d-card mb-3">
                    <p class="text-sm font-semibold mb-4 text-center">{{ __('delivery.assignments.otp_verification') }}</p>
                    <div class="flex justify-center gap-2 mb-4" id="otp-inputs">
                        @for($i = 0; $i < 6; $i++)
                            <input type="tel" inputmode="numeric" pattern="[0-9]" maxlength="1"
                                class="otp-digit w-11 h-14 rounded-xl bg-slate-700 text-center text-2xl font-bold text-white border-2 border-slate-600 focus:border-yellow-400 focus:outline-none transition-colors"
                                autocomplete="off">
                        @endfor
                        <input type="hidden" id="otp-combined" x-model="otp">
                    </div>

                    {{-- Proof photo --}}
                    <div class="mt-4 border-t border-slate-700 pt-4">
                        <label class="text-xs font-semibold text-slate-400 uppercase tracking-wide block mb-2">{{ __('delivery.assignments.proof_photo_optional') }}</label>
                        <label
                            class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-slate-600 rounded-xl cursor-pointer hover:border-yellow-400 transition-colors"
                            x-bind:class="proofPreview ? 'border-green-500' : ''">
                            <template x-if="!proofPreview">
                                <div class="text-center">
                                    <svg class="w-7 h-7 text-slate-500 mx-auto" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 15l-5-5L5 21" />
                                    </svg>
                                    <p class="text-xs text-slate-500 mt-1">{{ __('delivery.assignments.tap_to_take_upload_photo') }}</p>
                                </div>
                            </template>
                            <template x-if="proofPreview">
                                <img :src="proofPreview" class="h-full w-full object-cover rounded-xl">
                            </template>
                            <input type="file" accept="image/*" capture="environment" class="hidden"
                                @change="handleProofPhoto($event)">
                        </label>
                    </div>
                </div>

                <button type="button" id="deliver-form" @click="deliver()"
                    :disabled="loading || !otp || otp.length < 6 || (isCod && !codAmountCollected)"
                    class="btn-action btn-yellow"
                    :class="{ 'opacity-50': !otp || otp.length < 6 || (isCod && !codAmountCollected) }">
                    <span x-text="loading ? '{{ __('delivery.assignments.confirming') }}' : (isCod ? '✓ {{ __('delivery.assignments.delivery_confirmed_cod') }}' : '✓ {{ __('delivery.assignments.confirm_delivery') }}')"></span>
                </button>
            @endif

            {{-- Fail button (available for accepted and picked_up) --}}
            @if(in_array($assignment->status, [\App\Enums\DeliveryAssignmentStatus::Accepted, \App\Enums\DeliveryAssignmentStatus::PickedUp], true))
                <button type="button" @click="showFail = true" class="btn-action btn-outline text-sm" style="min-height: 46px;">
                    {{ __('delivery.assignments.mark_as_failed') }}
                </button>
            @endif

            {{-- Fail reason modal --}}
            <div x-show="showFail" x-cloak class="fixed inset-0 bg-black/70 z-50 flex items-end justify-center"
                style="max-width: 480px; left: 50%; transform: translateX(-50%);">
                <div class="bg-slate-800 w-full rounded-t-3xl p-6 pb-safe" @click.stop>
                    <h3 class="text-base font-bold mb-4">{{ __('delivery.assignments.why_delivery_failed') }}</h3>
                    <select x-model="failReason"
                        class="w-full bg-slate-700 text-slate-200 rounded-xl p-3 mb-3 text-sm border border-slate-600 focus:outline-none focus:border-yellow-400">
                        <option value="">{{ __('delivery.assignments.select_a_reason') }}</option>
                        <option value="customer_not_home">{{ __('delivery.assignments.customer_not_home') }}</option>
                        <option value="wrong_address">{{ __('delivery.assignments.wrong_address') }}</option>
                        <option value="customer_refused">{{ __('delivery.assignments.customer_refused') }}</option>
                        <option value="damaged_package">{{ __('delivery.assignments.damaged_package') }}</option>
                        <option value="other">{{ __('delivery.assignments.other') }}</option>
                    </select>
                    <textarea x-model="failNotes" rows="2"
                        class="w-full bg-slate-700 text-slate-200 rounded-xl p-3 mb-4 text-sm border border-slate-600 focus:outline-none focus:border-yellow-400 resize-none"
                        placeholder="{{ __('delivery.assignments.additional_notes_optional') }}"></textarea>
                    <div class="flex gap-3">
                        <button type="button" @click="showFail = false" class="btn-action btn-outline flex-1"
                            style="min-height:46px; font-size:14px;">{{ __('delivery.assignments.cancel') }}</button>
                        <button type="button" @click="fail()" :disabled="!failReason || loading"
                            class="btn-action btn-red flex-1" style="min-height:46px; font-size:14px;"
                            :class="{ 'opacity-50': !failReason }">
                            <span x-text="loading ? '{{ __('delivery.assignments.saving') }}' : '{{ __('delivery.assignments.confirm') }}'"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Success/Error toast --}}
            <div x-show="toastMsg" x-cloak x-transition
                class="fixed top-4 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-2xl text-sm font-semibold shadow-xl"
                :class="toastError ? 'bg-red-600 text-white' : 'bg-green-600 text-white'" style="max-width: 340px;">
                <span x-text="toastMsg"></span>
            </div>

        </div>
    @else
        {{-- Completed / Read-only state --}}
        <div class="d-card text-center py-8 mb-6">
            @if($assignment->status === \App\Enums\DeliveryAssignmentStatus::Delivered)
                <div class="text-4xl mb-2">✅</div>
                <p class="font-semibold text-green-400">{{ __('delivery.assignments.delivered') }}</p>
                @if($assignment->delivered_at)
                    <p class="text-xs text-slate-400 mt-1">{{ $assignment->delivered_at->format('d M Y H:i') }}</p>
                @endif
            @elseif($assignment->status === \App\Enums\DeliveryAssignmentStatus::Failed)
                <div class="text-4xl mb-2">❌</div>
                <p class="font-semibold text-red-400">{{ __('delivery.assignments.failed') }}</p>
                @if($assignment->failure_reason)
                    <p class="text-xs text-slate-400 mt-1">{{ ucfirst(str_replace('_', ' ', $assignment->failure_reason)) }}</p>
                @endif
            @endif
        </div>
    @endif

@endsection

@push('scripts')
    <script>
        function assignmentActions() {
            return {
                loading: false,
                otp: '',
                proofFile: null,
                proofPreview: null,
                showFail: false,
                failReason: '',
                failNotes: '',
                toastMsg: '',
                toastError: false,
                _toastTimer: null,
                isCod: @json($isCod),
                expectedCodCents: @json($expectedCodCents),
                codAmountCollected: @json($isCod ? number_format($expectedCodCents, 2) : 'null'),
                discrepancyNote: '',
                get showDiscrepancyNote() {
                    if (!this.isCod || !this.codAmountCollected) return false;
                    const collectedCents = Math.round(parseFloat(this.codAmountCollected) * 100);
                    const diff = Math.abs(collectedCents - this.expectedCodCents);
                    const pct = this.expectedCodCents > 0 ? diff / this.expectedCodCents : 0;
                    return diff > 5 && pct > 0.05;
                },

                toast(msg, error = false) {
                    this.toastMsg = msg;
                    this.toastError = error;
                    clearTimeout(this._toastTimer);
                    this._toastTimer = setTimeout(() => { this.toastMsg = ''; }, 3000);
                },

                getLocation() {
                    return new Promise((resolve) => {
                        if (!navigator.geolocation) return resolve({});
                        navigator.geolocation.getCurrentPosition(
                            p => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude }),
                            () => resolve({})
                        );
                    });
                },

                async accept() {
                    this.loading = true;
                    try {
                        const res = await fetch(@json(route('delivery.assignments.accept', $assignment->id)), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.toast(@json(__('delivery.assignments.assignment_accepted')));
                            setTimeout(() => location.reload(), 800);
                        } else {
                            this.toast(data.message || @json(__('delivery.assignments.generic_failed')), true);
                        }
                    } catch (e) { this.toast(@json(__('delivery.assignments.network_error')), true); }
                    finally { this.loading = false; }
                },

                async pickUp() {
                    this.loading = true;
                    const loc = await this.getLocation();
                    try {
                        const res = await fetch(@json(route('delivery.assignments.picked-up', $assignment->id)), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify(loc),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.toast(@json(__('delivery.assignments.marked_picked_up')));
                            setTimeout(() => location.reload(), 800);
                        } else {
                            this.toast(data.message || @json(__('delivery.assignments.generic_failed')), true);
                        }
                    } catch (e) { this.toast(@json(__('delivery.assignments.network_error')), true); }
                    finally { this.loading = false; }
                },

                handleProofPhoto(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    this.proofFile = file;
                    this.proofPreview = URL.createObjectURL(file);
                },

                async deliver() {
                    if (!this.otp || this.otp.length < 6) return;
                    if (this.isCod && !this.codAmountCollected) {
                        this.toast(@json(__('delivery.assignments.enter_collected_amount')), true);
                        return;
                    }
                    if (this.showDiscrepancyNote && !this.discrepancyNote.trim()) {
                        this.toast(@json(__('delivery.assignments.enter_discrepancy_reason')), true);
                        return;
                    }
                    this.loading = true;
                    const loc = await this.getLocation();
                    const form = new FormData();
                    form.append('otp_code', this.otp);
                    form.append('_token', @json(csrf_token()));
                    if (loc.latitude) form.append('latitude', loc.latitude);
                    if (loc.longitude) form.append('longitude', loc.longitude);
                    if (this.proofFile) form.append('proof_image', this.proofFile);
                    if (this.isCod && this.codAmountCollected) {
                        // Convert display value (e.g. 125.50) to cents integer
                        const cents = Math.round(parseFloat(this.codAmountCollected) * 100);
                        form.append('cod_amount_collected', cents);
                    }
                    if (this.discrepancyNote.trim()) {
                        form.append('discrepancy_note', this.discrepancyNote.trim());
                    }

                    try {
                        const res = await fetch(@json(route('delivery.assignments.deliver', $assignment->id)), {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: form,
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.toast(this.isCod ? @json(__('delivery.assignments.delivery_confirmed_cod')) : @json(__('delivery.assignments.delivery_confirmed')));
                            setTimeout(() => window.location.href = @json(route('delivery.assignments.index')), 1200);
                        } else {
                            if (data.requires_discrepancy_note) {
                                this.toast(data.message, true);
                                // Force show the note field
                            } else {
                                this.toast(data.message || @json(__('delivery.assignments.invalid_otp')), true);
                            }
                            if (data.remaining === 0) {
                                setTimeout(() => location.reload(), 1500);
                            }
                        }
                    } catch (e) { this.toast(@json(__('delivery.assignments.network_error')), true); }
                    finally { this.loading = false; }
                },

                async fail() {
                    if (!this.failReason) return;
                    this.loading = true;
                    const loc = await this.getLocation();
                    try {
                        const res = await fetch(@json(route('delivery.assignments.fail', $assignment->id)), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                failure_reason: this.failReason,
                                failure_notes: this.failNotes,
                                ...loc
                            }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showFail = false;
                            this.toast(@json(__('delivery.assignments.marked_failed')));
                            setTimeout(() => window.location.href = @json(route('delivery.assignments.index')), 1200);
                        } else {
                            this.toast(data.message || @json(__('delivery.assignments.generic_failed')), true);
                        }
                    } catch (e) { this.toast(@json(__('delivery.assignments.network_error')), true); }
                    finally { this.loading = false; }
                },
            };
        }

        // Wire OTP inputs (from delivery/app.js)
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = document.querySelectorAll('.otp-digit');
            if (!inputs.length) return;
            inputs.forEach((input, i) => {
                input.addEventListener('input', e => {
                    const v = e.target.value.replace(/\D/g, '');
                    e.target.value = v.slice(0, 1);
                    if (v && i < inputs.length - 1) inputs[i + 1].focus();
                    const combined = [...inputs].map(el => el.value).join('');
                    document.getElementById('otp-combined').value = combined;
                    // Tell Alpine
                    document.getElementById('otp-combined').dispatchEvent(new Event('input'));
                });
                input.addEventListener('keydown', e => {
                    if (e.key === 'Backspace' && !input.value && i > 0) inputs[i - 1].focus();
                });
                input.addEventListener('paste', e => {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    inputs.forEach((el, idx) => { el.value = pasted[idx] || ''; });
                    if (pasted.length >= inputs.length) inputs[inputs.length - 1].focus();
                    const combined = pasted.slice(0, inputs.length);
                    document.getElementById('otp-combined').value = combined;
                    document.getElementById('otp-combined').dispatchEvent(new Event('input'));
                });
            });
        });
    </script>
@endpush
