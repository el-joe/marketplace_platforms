@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';
    $pageTitle = $page->localizedTitle();
    // Same-language SEO title, else the (localized) page title.
    $seoTitle = $page->{'seo_title_'.($isAr ? 'ar' : 'en')} ?: $pageTitle;
    $seoDescription = $page->localizedSeoDescription();
    // Stored HTML is sanitized on save (Admin\StaticPageController); re-cleaned here as defense in depth.
    $content = \App\Support\HtmlSanitizer::clean($page->localizedContent());
@endphp

@section('title', $seoTitle)
@if ($seoDescription)
    @section('meta_description', $seoDescription)
@endif

@push('head')
<meta property="og:title" content="{{ $seoTitle }}">
@if ($seoDescription)
    <meta property="og:description" content="{{ $seoDescription }}">
@endif
<meta property="og:type" content="website">
<meta property="og:url" content="{{ route('portal.pages.show', $page->slug) }}">
<link rel="canonical" href="{{ route('portal.pages.show', $page->slug) }}">
<style>
.static-page-prose { color: #D1D5DB; line-height: 1.8; font-size: 1rem; overflow-wrap: anywhere; }
.static-page-prose h1, .static-page-prose h2 { font-size: 1.5rem; font-weight: 700; color: #FFFFFF; margin: 2rem 0 1rem; }
.static-page-prose h3, .static-page-prose h4 { font-size: 1.25rem; font-weight: 600; color: #FFFFFF; margin: 1.5rem 0 0.75rem; }
.static-page-prose p { margin: 0 0 1.25rem; }
.static-page-prose ul, .static-page-prose ol { padding-inline-start: 1.5rem; margin: 0 0 1.25rem; }
.static-page-prose ul { list-style: disc; }
.static-page-prose ol { list-style: decimal; }
.static-page-prose li { margin-bottom: 0.4rem; }
.static-page-prose blockquote { border-inline-start: 4px solid #0F807E; padding-inline-start: 1rem; color: #9CA3AF; margin: 1.5rem 0; }
.static-page-prose a { color: #2DD4BF; text-decoration: underline; }
.static-page-prose img { border-radius: 0.75rem; max-width: 100%; height: auto; margin: 1.5rem 0; }
.static-page-prose strong, .static-page-prose b { font-weight: 600; color: #FFFFFF; }
.static-page-prose table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; font-size: 0.875rem; }
.static-page-prose th, .static-page-prose td { border: 1px solid #1F2937; padding: 0.5rem 0.75rem; text-align: start; }
.static-page-prose pre { background: #030712; padding: 1rem; border-radius: 0.75rem; overflow-x: auto; margin: 1.5rem 0; }
.static-page-prose hr { border: none; border-top: 1px solid #1F2937; margin: 2rem 0; }
</style>
@endpush

@section('content')
<div class="bg-gray-950 min-h-screen" style="padding-top: 104px;">
    <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-16" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
        <nav class="flex items-center gap-2 text-xs text-gray-500 mb-6 flex-wrap" aria-label="{{ portal_content('blog', 'show', 'breadcrumb_aria', 'Breadcrumb', 'مسار التنقل') }}">
            <a href="{{ route('portal.home') }}" class="hover:text-gray-300 transition-colors">
                {{ portal_content('blog', 'show', 'home_label', 'Home', 'الرئيسية') }}
            </a>
            <span>/</span>
            <span class="text-gray-400 line-clamp-1">{{ $pageTitle }}</span>
        </nav>

        <h1 class="text-3xl sm:text-4xl font-bold text-white leading-tight mb-3">{{ $pageTitle }}</h1>
        @if ($page->updated_at)
            <p class="text-xs text-gray-500 mb-8">{{ __('portal.static_pages.last_updated', ['date' => $page->updated_at->translatedFormat('j F Y')]) }}</p>
        @endif

        <div class="static-page-prose" data-static-page="{{ $page->slug }}">
            {!! $content !!}
        </div>
    </article>
</div>

@include('portal.partials.portal-footer')
@endsection
