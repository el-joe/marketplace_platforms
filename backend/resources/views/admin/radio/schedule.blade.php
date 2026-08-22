@extends('layouts.admin')

@section('title', __('admin.radio.schedule_for', ['channel' => $channel->name_en]))

@push('head')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="p-6 space-y-4" x-data="radioSchedule()" x-init="init()">

    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.radio.channels.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('admin.radio.back_to_channels') }}</a>
            <h1 class="text-xl font-bold text-gray-900 mt-1">
                📅 {{ __('admin.radio.schedule_for', ['channel' => $channel->name_en]) }}
                <span class="text-sm font-normal text-gray-500 ml-2">{{ __('admin.radio.' . $channel->type->value) }}</span>
            </h1>
        </div>
        <button @click="openCreate()"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            + {{ __('admin.radio.add_slot') }}
        </button>
    </div>

    {{-- Calendar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div id="radio-calendar"></div>
    </div>

    {{-- Slot Modal --}}
    <div x-show="modal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         @click.self="closeModal()">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">

            <h2 class="text-lg font-bold text-gray-900" x-text="editing ? '{{ __('admin.radio.edit_slot') }}' : '{{ __('admin.radio.new_slot') }}'"></h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.radio.slot_title') }}</label>
                <input type="text" x-model="form.title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.radio.starts_at') }}</label>
                    <input type="datetime-local" x-model="form.starts_at" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.radio.ends_at') }}</label>
                    <input type="datetime-local" x-model="form.ends_at" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.radio.recurrence') }}</label>
                <select x-model="form.recurrence" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="once">{{ __('admin.radio.recurrence_once') }}</option>
                    <option value="daily">{{ __('admin.radio.recurrence_daily') }}</option>
                    <option value="weekly">{{ __('admin.radio.recurrence_weekly') }}</option>
                </select>
            </div>

            <div x-show="form.recurrence === 'weekly'">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.radio.repeat_on_days') }}</label>
                <div class="flex flex-wrap gap-2">
                    @foreach(['mon','tue','wed','thu','fri','sat','sun'] as $d)
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" value="{{ $d }}"
                               @change="toggleDay('{{ $d }}')"
                               :checked="form.recurrence_days.includes('{{ $d }}')"
                               class="rounded border-gray-300 text-primary-600">
                        <span class="text-sm text-gray-700">{{ __('admin.radio.day_' . $d) }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" x-model="form.is_active" id="slot_active" class="rounded border-gray-300 text-primary-600">
                <label for="slot_active" class="text-sm text-gray-700">{{ __('common.active') }}</label>
            </div>

            <div class="flex gap-3 pt-2">
                <button @click="saveSlot()"
                        class="flex-1 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700"
                        x-text="saving ? '{{ __('admin.radio.saving') }}' : (editing ? '{{ __('admin.radio.update_slot') }}' : '{{ __('admin.radio.create_slot') }}')"
                        :disabled="saving"></button>
                <template x-if="editing">
                    <button @click="deleteSlot()"
                            class="py-2 px-4 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50">
                        {{ __('common.delete') }}
                    </button>
                </template>
                <button @click="closeModal()"
                        class="py-2 px-4 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">
                    {{ __('common.cancel') }}
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    slot_error: "{{ __('admin.radio.slot_error', ['message' => '__MSG__']) }}",
    delete_slot_confirm: "{{ __('admin.radio.delete_slot_confirm') }}",
});

function radioSchedule() {
    return {
        modal:   false,
        editing: null, // slot id when editing
        saving:  false,
        calendar: null,
        form: {
            title: '',
            starts_at: '',
            ends_at: '',
            recurrence: 'once',
            recurrence_days: [],
            is_active: true,
        },

        init() {
            const calEl = document.getElementById('radio-calendar');
            this.calendar = new FullCalendar.Calendar(calEl, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  'dayGridMonth,timeGridWeek,timeGridDay',
                },
                events: '{{ route('admin.radio.schedule.events', $channel) }}',
                selectable: true,
                select: (info) => {
                    this.openCreate(info.startStr, info.endStr);
                    this.calendar.unselect();
                },
                eventClick: (info) => {
                    this.openEdit(info.event);
                },
            });
            this.calendar.render();
        },

        openCreate(start = '', end = '') {
            this.editing = null;
            this.form = {
                title: '',
                starts_at: start ? start.slice(0,16) : '',
                ends_at:   end   ? end.slice(0,16)   : '',
                recurrence: 'once',
                recurrence_days: [],
                is_active: true,
            };
            this.modal = true;
        },

        openEdit(event) {
            this.editing = event.id;
            const props  = event.extendedProps;
            this.form = {
                title:           event.title,
                starts_at:       event.start ? event.start.toISOString().slice(0,16) : '',
                ends_at:         event.end   ? event.end.toISOString().slice(0,16)   : '',
                recurrence:      props.recurrence      ?? 'once',
                recurrence_days: props.recurrence_days ?? [],
                is_active:       props.is_active       ?? true,
            };
            this.modal = true;
        },

        closeModal() { this.modal = false; },

        toggleDay(day) {
            const idx = this.form.recurrence_days.indexOf(day);
            if (idx > -1) this.form.recurrence_days.splice(idx, 1);
            else          this.form.recurrence_days.push(day);
        },

        async saveSlot() {
            this.saving = true;
            const url    = this.editing
                ? `/admin/radio/channels/{{ $channel->id }}/slots/${this.editing}`
                : '{{ route('admin.radio.slots.store', $channel) }}';
            const method = this.editing ? 'PUT' : 'POST';

            try {
                const resp = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                if (!resp.ok) throw await resp.json();
                this.calendar.refetchEvents();
                this.closeModal();
            } catch(e) {
                alert(window.TRANSLATIONS.slot_error.replace('__MSG__', e.message ?? JSON.stringify(e)));
            } finally {
                this.saving = false;
            }
        },

        async deleteSlot() {
            if (!confirm(window.TRANSLATIONS.delete_slot_confirm)) return;
            await fetch(`/admin/radio/channels/{{ $channel->id }}/slots/${this.editing}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
            });
            this.calendar.refetchEvents();
            this.closeModal();
        },
    };
}
</script>
@endpush
