@php
    /** @var \App\Models\DeliveryAssignment $assignment */
    $chipClass = 'chip-' . $assignment->status->value;
    $actionLabel = match ($assignment->status) {
        \App\Enums\DeliveryAssignmentStatus::Assigned => __('delivery.assignments.accept'),
        \App\Enums\DeliveryAssignmentStatus::Accepted => __('delivery.dashboard.mark_picked_up'),
        \App\Enums\DeliveryAssignmentStatus::PickedUp => __('delivery.assignments.confirm_delivery'),
        default => __('delivery.dashboard.view'),
    };
@endphp
<a href="{{ route('delivery.assignments.show', $assignment->id) }}"
    class="block d-card hover:bg-slate-700/50 transition-colors">
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm truncate">
                #{{ $assignment->subOrder?->sub_order_number ?? substr($assignment->id, 0, 8) }}
            </p>
            <p class="text-xs text-slate-400 mt-0.5">
                {{ $assignment->subOrder?->items?->count() ?? '?' }} {{ __('delivery.assignments.items') }}
                @if($assignment->assigned_at)
                    · {{ $assignment->assigned_at->format('H:i') }}
                @endif
            </p>
        </div>
        <span class="chip {{ $chipClass }} ml-2 flex-shrink-0">
            {{ $assignment->status->label() }}
        </span>
    </div>
</a>
