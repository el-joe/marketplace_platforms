@extends('layouts.admin')

@section('title', __('admin.ad_slots.create_slot_title'))

@section('content')

    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="{{ route('admin.ad-slots.index') }}" class="hover:text-primary-600">{{ __('admin.ad_slots.title') }}</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">{{ __('admin.ad_slots.create') }}</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.ad_slots.create_slot_title') }}</h1>
    </div>

    <form method="POST" action="{{ route('admin.ad-slots.store') }}">
        @csrf
        @include('admin.ad-slots._form', ['adSlot' => null])
    </form>

@endsection
