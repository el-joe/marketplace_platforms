@extends('layouts.admin')

@section('title', __('admin.nav.dashboard'))

@section('content')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.dashboard.welcome_back') }}</h1>
        <p class="text-sm text-gray-500 mt-2">{{ auth()->guard('admin')->user()?->name }}</p>
    </div>
@endsection
