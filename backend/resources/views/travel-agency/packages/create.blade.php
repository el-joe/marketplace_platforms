@extends('layouts.travel-agency')

@section('title', __('travel.packages.add_new_package'))

@section('content')
<div class="max-w-3xl space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('travel-agency.packages.index') }}" class="text-gray-400 hover:text-gray-600">{{ __('travel.packages.back') }}</a>
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.packages.add_new_package') }}</h1>
    </div>

    <form method="POST" action="{{ route('travel-agency.packages.store') }}"
          enctype="multipart/form-data" class="space-y-6">
        @csrf

        @include('travel-agency.packages._form')

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-3 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-400">
                {{ __('travel.packages.save_draft') }}
            </button>
            <a href="{{ route('travel-agency.packages.index') }}" class="px-6 py-3 border border-gray-300 rounded-xl text-gray-600 hover:bg-gray-50">
                {{ __('travel.packages.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
