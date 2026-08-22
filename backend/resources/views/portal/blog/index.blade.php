@extends('layouts.portal')

@section('title', __('portal.blog.title'))

@section('content')

{{-- ── Header ─────────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden bg-gradient-to-b from-gray-900 to-gray-950 border-b border-gray-800 py-16">
    <div class="absolute inset-0 opacity-20 pointer-events-none"
         style="background-image: radial-gradient(circle at 20% 20%, #F59E0B 0%, transparent 35%);"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <span class="inline-block text-xs font-bold tracking-widest uppercase text-[#feee00] mb-3">
            {{ portal_content('blog', 'header', 'eyebrow', 'Noon Sellers Blog', 'مدونة نون للبائعين') }}
        </span>
        <h1 class="text-4xl sm:text-5xl font-black text-white leading-tight">
            {{ __('portal.blog.title') }}
        </h1>
        <p class="text-gray-400 mt-3 text-lg max-w-2xl">
            {{ portal_content('blog', 'header', 'subtitle', 'News, guides and tips from the Noon platform', 'أخبار وتوجيهات ونصائح من منصة نون') }}
        </p>

        <form method="GET" action="{{ route('portal.blog.index') }}" class="mt-8 flex gap-3 max-w-xl">
            @if(request('category'))
                <input type="hidden" name="category" value="{{ request('category') }}">
            @endif
            <div class="relative flex-1">
                <svg class="w-4 h-4 text-gray-500 absolute top-1/2 -translate-y-1/2 start-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('portal.blog.search_blog') }}"
                       class="w-full rounded-xl border border-gray-700 bg-gray-900 ps-10 pe-4 py-2.5 text-sm text-white
                              placeholder-gray-500 focus:border-[#feee00] focus:ring-1 focus:ring-[#feee00] transition-colors">
            </div>
            <button type="submit"
                    class="text-sm font-bold bg-[#feee00] hover:bg-[#e5d600] text-gray-950 px-6 py-2.5 rounded-xl
                           transition-colors shadow-lg shadow-[#feee00]/20">
                {{ __('portal.blog.search') }}
            </button>
        </form>
    </div>
</section>

{{-- ── Featured Post ───────────────────────────────────────────────── --}}
@if($featuredPost)
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">
        <a href="{{ route('portal.blog.show', $featuredPost->slug) }}"
           class="block rounded-2xl overflow-hidden bg-gray-900 border border-gray-800
                  hover:border-gray-700 hover:shadow-2xl hover:shadow-black/40 transition-all group">
            <div class="grid grid-cols-1 lg:grid-cols-2">
                @if($featuredPost->featured_image_path)
                    <div class="aspect-video lg:aspect-auto overflow-hidden bg-gray-800">
                        <img src="{{ Storage::url($featuredPost->featured_image_path) }}"
                             alt="{{ $featuredPost->{'featured_image_alt_' . $locale} }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    </div>
                @endif
                <div class="p-8 lg:p-10 flex flex-col justify-center">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold
                                 rounded-full px-3 py-1 w-fit mb-4"
                          style="background-color: {{ $featuredPost->category->color_hex ?? '#F59E0B' }}1F;
                                 color: {{ $featuredPost->category->color_hex ?? '#FBBF24' }}">
                        {{ $featuredPost->category->{'name_' . $locale} }}
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-white group-hover:text-[#feee00]
                               transition-colors leading-snug">
                        {{ $featuredPost->{'title_' . $locale} }}
                    </h2>
                    <p class="text-gray-400 mt-3 leading-relaxed line-clamp-3">
                        {{ $featuredPost->{'excerpt_' . $locale} }}
                    </p>
                    <div class="flex items-center gap-3 mt-6 text-xs text-gray-500">
                        <div class="w-7 h-7 rounded-full bg-[#feee00]/10 flex items-center justify-center text-[#feee00] text-xs font-bold">
                            {{ mb_substr($featuredPost->author->name, 0, 1) }}
                        </div>
                        <span class="text-gray-300 font-medium">{{ $featuredPost->author->name }}</span>
                        <span>·</span>
                        <span>{{ $featuredPost->published_at->format('d M Y') }}</span>
                        <span>·</span>
                        <span>{{ $featuredPost->reading_time_minutes }}
                            {{ __('portal.blog.min_read') }}</span>
                    </div>
                </div>
            </div>
        </a>
    </div>
@endif

{{-- ── Category Filter Pills ───────────────────────────────────────── --}}
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 flex flex-wrap gap-2">
    <a href="{{ route('portal.blog.index', array_filter(['search' => request('search'), 'tag' => request('tag')])) }}"
       class="rounded-full px-4 py-1.5 text-sm font-medium border transition-colors
              {{ !request('category')
                  ? 'bg-[#feee00] text-gray-950 border-[#feee00]'
                  : 'bg-gray-900 text-gray-400 border-gray-800 hover:border-gray-600 hover:text-white' }}">
        {{ __('portal.blog.all') }}
    </a>

    @foreach($categories as $cat)
        <a href="{{ route('portal.blog.index', array_filter(['category' => $cat->slug, 'search' => request('search')])) }}"
           class="rounded-full px-4 py-1.5 text-sm font-medium border transition-colors
                  {{ request('category') === $cat->slug
                      ? 'text-gray-950 border-transparent'
                      : 'bg-gray-900 text-gray-400 border-gray-800 hover:border-gray-600 hover:text-white' }}"
           @if(request('category') === $cat->slug)
               style="background-color: {{ $cat->color_hex ?? '#FBBF24' }}; border-color: {{ $cat->color_hex ?? '#FBBF24' }}"
           @endif>
            {{ $cat->{'name_' . $locale} }}
            <span class="ms-1 text-xs opacity-70">({{ $cat->posts_count }})</span>
        </a>
    @endforeach
</div>

{{-- ── Posts Grid ──────────────────────────────────────────────────── --}}
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 mb-20">
    @if($posts->isEmpty())
        <div class="py-20 text-center text-gray-500">
            <p class="text-lg">{{ __('portal.blog.no_posts') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($posts as $post)
                @include('portal.blog._post-card', ['post' => $post, 'locale' => $locale])
            @endforeach
        </div>
        <div class="mt-10 [&_.pagination]:text-gray-400">{{ $posts->links() }}</div>
    @endif
</div>

@include('portal.partials.cta-footer')

@endsection
