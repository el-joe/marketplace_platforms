@extends('layouts.admin')

@section('title', __('admin.app_contexts.title'))

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.app_contexts.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.app_contexts.subtitle') }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($contexts as $context)
            <a href="{{ route('admin.app-contexts.show', $context) }}"
               class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex flex-col gap-3 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center overflow-hidden"
                         style="background-color: {{ $context->color_hex ?: '#E5E7EB' }}">
                        @if ($context->icon_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($context->icon_path) }}"
                                 class="w-8 h-8 object-contain">
                        @else
                            <span class="text-white font-bold text-lg">{{ mb_substr($context->name_en, 0, 1) }}</span>
                        @endif
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $context->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $context->is_active ? __('admin.app_contexts.active') : __('admin.app_contexts.inactive') }}
                    </span>
                </div>
                <div>
                    <div class="font-semibold text-gray-900">{{ $context->name_en }}</div>
                    <div class="text-sm text-gray-500 font-arabic" dir="rtl">{{ $context->name_ar }}</div>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-400 pt-2 border-t border-gray-100">
                    <span>{{ $context->key }}</span>
                    <span>{{ __('admin.app_contexts.sort_order') }}: {{ $context->sort_order }}</span>
                </div>
            </a>
        @endforeach
    </div>

@endsection
