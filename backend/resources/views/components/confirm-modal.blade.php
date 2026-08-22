@props([
    'id'            => 'confirm-modal',
    'title'         => null,
    'message'       => null,
    'confirmLabel'  => null,
    'confirmClass' => 'btn-danger',
    'actionUrl'     => '#',
    'actionMethod' => 'DELETE', // DELETE | POST | PATCH | PUT
])

@php
    $title = $title ?? __('common.are_you_sure');
    $message = $message ?? __('common.action_cannot_be_undone');
    $confirmLabel = $confirmLabel ?? __('common.confirm');
    $methodUpper = strtoupper($actionMethod);
    $formMethod  = in_array($methodUpper, ['GET', 'POST']) ? $methodUpper : 'POST';
    $spoof = in_array($methodUpper, ['DELETE', 'PATCH', 'PUT']) ? $methodUpper : null;
@endphp

<x-modal :id="$id" :title="$title" size="sm">
    <p class="text-sm text-gray-600">{{ $message }}</p>

    <x-slot:footer>
        <button type="button" data-modal-close class="btn-secondary">{{ __('common.cancel') }}</button>
        <form method="{{ $formMethod }}" action="{{ $actionUrl }}" class="inline">
            @csrf
            @if($spoof)
                <input type="hidden" name="_method" value="{{ $spoof }}">
            @endif
            <button type="submit" class="{{ $confirmClass }}">{{ $confirmLabel }}</button>
        </form>
    </x-slot:footer>
</x-modal>
