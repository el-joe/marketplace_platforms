@extends('layouts.admin')

@section('title', __('admin.attributes_section.edit_attribute_prefix') . e($attribute->name_en))

@push('styles')
    @vite(['resources/js/admin/attributes.js'])
@endpush

@section('content')
    <form id="attribute-form" method="POST" action="{{ route('admin.attributes.update', $attribute->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.attributes._form', ['mode' => 'edit', 'attribute' => $attribute])
    </form>

    <script>
        window.ROUTES_ATTR_EDIT = {
            storeValue: '{{ route('admin.attributes.values.store', $attribute->id) }}',
            updateValue: '{{ url('attributes/' . $attribute->id . '/values') }}/:value_id',
            destroyValue: '{{ url('attributes/' . $attribute->id . '/values') }}/:value_id',
            reorderValues: '{{ route('admin.attributes.values.reorder', $attribute->id) }}',
            regenerateVariantSlugs: '{{ url('attributes/' . $attribute->id . '/values') }}/:value_id/regenerate-variant-slugs',
        };
    </script>
@endsection