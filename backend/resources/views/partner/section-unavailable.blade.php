@extends('layouts.partner')

@section('title', __('partner.section_unavailable.page_title'))
@section('page-title', __('partner.section_unavailable.page_title'))

@section('content')
    <div class="max-w-xl mx-auto mt-10 bg-white border border-gray-200 rounded-2xl p-8 text-center">
        <div class="flex justify-center mb-5">
            <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center">
                <x-heroicon name="lock-closed" class="w-8 h-8 text-amber-600" />
            </div>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">{{ __('partner.section_unavailable.heading') }}</h1>
        <p class="text-sm text-gray-500 leading-relaxed mb-6">{{ __('partner.section_unavailable.message') }}</p>
        <a href="{{ route('partner.dashboard') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-gray-800">
            {{ __('partner.section_unavailable.back_to_dashboard') }}
        </a>
    </div>
@endsection
