{{-- Usage:
    @component('admin.docs._layout', ['title' => 'Page Title', 'icon' => '📦', 'breadcrumb' => 'Features'])
        ... page content ...
    @endcomponent
--}}

@extends('layouts.admin')

@section('title', $title)

@section('content')
<div class="max-w-5xl mx-auto px-6 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-4">
        <a href="{{ route('admin.docs.index') }}" class="hover:text-primary-600">{{ __('admin.nav.documentation') }}</a>
        @if(isset($breadcrumb))
            <span class="mx-2">&rsaquo;</span>
            <span class="text-gray-700">{{ $breadcrumb }}</span>
        @endif
        <span class="mx-2">&rsaquo;</span>
        <span class="text-gray-900 font-medium">{{ $title }}</span>
    </nav>

    {{-- Page header --}}
    <div class="flex items-center gap-3 mb-8 pb-4 border-b border-gray-200">
        <span class="text-3xl">{{ $icon ?? '📄' }}</span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
            @if(isset($subtitle))
                <p class="text-gray-500 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    {{-- Content slot --}}
    {{ $slot }}

    {{-- Navigation footer --}}
    <div class="mt-12 pt-6 border-t border-gray-200 flex justify-between text-sm text-gray-400">
        <span>{{ __('admin.nav.documentation') }}</span>
        <a href="{{ route('admin.docs.index') }}" class="text-primary-500 hover:underline">&larr; {{ __('docs/layout.back_to_overview') }}</a>
    </div>
</div>
@endsection
