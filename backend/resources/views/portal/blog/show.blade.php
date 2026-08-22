@extends('layouts.portal')

@section('title', $post->{'seo_title_' . $locale} ?? $post->{'title_' . $locale})
@section('meta_description', $post->{'seo_description_' . $locale} ?? $post->{'excerpt_' . $locale})

@push('head')
@if($post->og_image_path || $post->featured_image_path)
    <meta property="og:image" content="{{ Storage::url($post->og_image_path ?? $post->featured_image_path) }}">
@endif
<meta property="og:title" content="{{ $post->{'seo_title_' . $locale} ?? $post->{'title_' . $locale} }}">
<meta property="og:description" content="{{ $post->{'seo_description_' . $locale} ?? $post->{'excerpt_' . $locale} }}">
<meta property="og:type" content="article">
@if($post->published_at)
    <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
@endif
@endpush

@section('content')

<div class="bg-gray-950 min-h-screen py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- ── Breadcrumb ───────────────────────────────────────────── --}}
        <nav class="flex items-center gap-2 text-xs text-gray-500 mb-6 flex-wrap" aria-label="{{ __('portal.blog.breadcrumb') }}">
            <a href="{{ route('portal.home') }}" class="hover:text-gray-300 transition-colors">
                {{ __('portal.nav.home') }}
            </a>
            <span>/</span>
            <a href="{{ route('portal.blog.index') }}" class="hover:text-gray-300 transition-colors">
                {{ __('portal.blog.title') }}
            </a>
            @if($post->category)
                <span>/</span>
                <a href="{{ route('portal.blog.index', ['category' => $post->category->slug]) }}"
                   class="hover:text-gray-300 transition-colors">
                    {{ $post->category->{'name_' . $locale} }}
                </a>
            @endif
            <span>/</span>
            <span class="text-gray-400 line-clamp-1">{{ $post->{'title_' . $locale} }}</span>
        </nav>

        <article class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden">

            {{-- ── Article Header ───────────────────────────────────── --}}
            <div class="p-8 pb-6">
                @if($post->category)
                    <a href="{{ route('portal.blog.index', ['category' => $post->category->slug]) }}"
                       class="inline-flex items-center text-xs font-semibold rounded-full px-3 py-1 mb-5"
                       style="background-color: {{ $post->category->color_hex ?? '#F59E0B' }}1F;
                              color: {{ $post->category->color_hex ?? '#FBBF24' }}">
                        {{ $post->category->{'name_' . $locale} }}
                    </a>
                @endif

                <h1 class="text-3xl sm:text-4xl font-bold text-white leading-snug"
                    dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                    {{ $post->{'title_' . $locale} }}
                </h1>

                <p class="text-gray-400 mt-3 text-lg leading-relaxed"
                   dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                    {{ $post->{'excerpt_' . $locale} }}
                </p>

                <div class="flex items-center gap-4 mt-6 text-sm text-gray-500 flex-wrap">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-[#feee00]/10 flex items-center justify-center
                                    text-[#feee00] text-xs font-bold">
                            {{ mb_substr($post->author->name, 0, 1) }}
                        </div>
                        <span class="text-gray-300 font-medium">{{ $post->author->name }}</span>
                    </div>
                    <span>·</span>
                    <span>{{ $post->published_at->format('d M Y') }}</span>
                    <span>·</span>
                    <span>{{ $post->reading_time_minutes }} {{ __('portal.blog.min_read') }}</span>
                    @if($post->views_count)
                        <span>·</span>
                        <span>{{ number_format($post->views_count) }} {{ portal_content('blog', 'show', 'views_label', 'views', 'مشاهدة') }}</span>
                    @endif
                </div>
            </div>

            {{-- ── Featured Image ───────────────────────────────────── --}}
            @if($post->featured_image_path)
                <div class="px-8 pb-6">
                    <img src="{{ Storage::url($post->featured_image_path) }}"
                         alt="{{ $post->{'featured_image_alt_' . $locale} }}"
                         class="w-full rounded-xl object-cover max-h-[480px]">
                </div>
            @endif

            {{-- ── Article Body ─────────────────────────────────────── --}}
            <div class="px-8 pb-8">
                <div class="blog-prose {{ $locale === 'ar' ? 'text-right' : '' }}"
                     dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                    {!! $post->{'body_' . $locale} !!}
                </div>
            </div>

            {{-- ── Attachments ──────────────────────────────────────── --}}
            @if($post->attachments->isNotEmpty())
                <div class="px-8 pb-8 pt-0">
                    <div class="blog-attachments pt-8 border-t border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-300 mb-3">{{ portal_content('blog', 'show', 'attachments_label', 'Attachments', 'المرفقات') }}</h3>
                        <div class="flex flex-col gap-2">
                            @foreach($post->attachments as $attachment)
                                <a href="{{ Storage::url($attachment->path) }}" download
                                   class="attachment-link flex items-center gap-2 rounded-lg px-3 py-2 bg-gray-800
                                          text-gray-300 hover:bg-[#feee00]/10 hover:text-[#feee00] transition-colors text-sm">
                                    <span class="icon">📎</span>
                                    {{ basename($attachment->path) }}
                                    <span class="size text-xs text-gray-500">({{ number_format($attachment->size / 1024, 0) }} KB)</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Tags ─────────────────────────────────────────────── --}}
            @if($post->tags && count($post->tags) > 0)
                <div class="px-8 pb-8 pt-0">
                    <div class="flex flex-wrap gap-2 pt-8 border-t border-gray-800">
                        @foreach($post->tags as $tag)
                            <a href="{{ route('portal.blog.index', ['tag' => $tag]) }}"
                               class="rounded-full px-3 py-1 text-xs font-medium bg-gray-800
                                      text-gray-300 hover:bg-[#feee00]/10 hover:text-[#feee00] transition-colors">
                                #{{ $tag }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── Author Card ──────────────────────────────────────── --}}
            <div class="px-8 pb-8">
                <div class="p-6 bg-gray-950 rounded-2xl flex items-start gap-4 border border-gray-800">
                    <div class="w-12 h-12 rounded-full bg-[#feee00]/10 flex items-center
                                justify-center text-[#feee00] font-bold text-lg flex-shrink-0">
                        {{ mb_substr($post->author->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium mb-0.5 tracking-wide">
                            {{ __('portal.blog.written_by') }}
                        </p>
                        <p class="font-semibold text-white">{{ $post->author->name }}</p>
                    </div>
                </div>
            </div>

        </article>

        {{-- ── Related Posts ────────────────────────────────────────── --}}
        @if($relatedPosts->isNotEmpty())
            <div class="mt-14 mb-20">
                <h2 class="text-xl font-bold text-white mb-6">
                    {{ __('portal.blog.related_posts') }}
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    @foreach($relatedPosts as $related)
                        @include('portal.blog._post-card', ['post' => $related, 'locale' => $locale])
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>

@include('portal.partials.cta-footer')

@endsection

@push('scripts')
<script>
    fetch('{{ route('portal.blog.posts.increment-views', $post->id) }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    }).catch(() => {});
</script>
@endpush

@push('head')
<style>
.blog-prose { color: #D1D5DB; line-height: 1.8; font-size: 1rem; }
.blog-prose h2 { font-size: 1.5rem; font-weight: 700; color: #FFFFFF; margin: 2rem 0 1rem; }
.blog-prose h3 { font-size: 1.25rem; font-weight: 600; color: #FFFFFF; margin: 1.5rem 0 0.75rem; }
.blog-prose p { margin: 0 0 1.25rem; }
.blog-prose ul, .blog-prose ol { padding-inline-start: 1.5rem; margin: 0 0 1.25rem; }
.blog-prose li { margin-bottom: 0.4rem; }
.blog-prose blockquote { border-inline-start: 4px solid #F59E0B; padding-inline-start: 1rem;
    color: #9CA3AF; font-style: italic; margin: 1.5rem 0; }
.blog-prose a { color: #FBBF24; text-decoration: underline; }
.blog-prose a:hover { color: #FCD34D; }
.blog-prose img { border-radius: 0.75rem; max-width: 100%; height: auto; margin: 1.5rem 0; }
.blog-prose strong { font-weight: 600; color: #FFFFFF; }
.blog-prose code { background: #1F2937; color: #F9FAFB; padding: 0.15rem 0.4rem; border-radius: 0.25rem;
    font-size: 0.875em; }
.blog-prose pre { background: #030712; color: #F9FAFB; padding: 1.25rem; border-radius: 0.75rem;
    overflow-x: auto; margin: 1.5rem 0; border: 1px solid #1F2937; }
.blog-prose pre code { background: none; padding: 0; color: inherit; }
.blog-prose hr { border: none; border-top: 1px solid #1F2937; margin: 2rem 0; }
</style>
@endpush
