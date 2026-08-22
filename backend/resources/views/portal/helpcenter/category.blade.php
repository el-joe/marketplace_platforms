@extends('layouts.helpcenter')

@php
    $isAr = app()->getLocale() === 'ar';
    $totalArticles = $category->articles->count() + $category->children->sum(fn ($c) => $c->articles->count());
@endphp

@section('title', $category->localizedName() . ' | ' . ($isAr ? 'مركز مساعدة البائع' : 'noon Seller Help Center'))
@section('description', $category->localizedDescription())

@section('header')
    @include('portal.partials.helpcenter-header', ['variant' => 'lite', 'country' => $country])
@endsection

@section('content')
    <nav class="pb-4 text-base" aria-label="{{ $isAr ? 'مسار التنقل' : 'Breadcrumb' }}">
        <ol class="m-0 flex list-none flex-wrap items-baseline gap-2 p-0">
            <li class="flex items-center gap-2">
                <a href="{{ route('portal.helpcenter.index', $country) }}" class="text-black no-underline hover:text-orange-600">{{ portal_content('helpcenter', 'category', 'all_categories', 'All Categories', 'كل الفئات') }}</a>
                <svg width="6" height="10" viewBox="0 0 6 10" class="block h-2 w-2 fill-[#737373] {{ $isAr ? 'rotate-180' : '' }}" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.648862 0.898862C0.316916 1.23081 0.316916 1.769 0.648862 2.10094L3.54782 4.9999L0.648862 7.89886C0.316916 8.23081 0.316917 8.769 0.648862 9.10094C0.980808 9.43289 1.519 9.43289 1.85094 9.10094L5.35094 5.60094C5.68289 5.269 5.68289 4.73081 5.35094 4.39886L1.85094 0.898862C1.519 0.566916 0.980807 0.566916 0.648862 0.898862Z"></path></svg>
            </li>
            <li aria-current="page" class="text-[#737373]">{{ $category->localizedName() }}</li>
        </ol>
    </nav>

    <div class="flex flex-col gap-10 pt-4">
        <div>
            <div class="mb-5">
                <div class="flex h-14 w-14 items-center justify-center rounded-[10px] bg-orange-50">
                    @if($category->icon)
                        <img class="h-9 w-9" src="{{ $category->icon }}" alt="" loading="lazy">
                    @else
                        <svg class="h-8 w-8 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    @endif
                </div>
            </div>
            <div class="flex flex-col">
                <h1 class="mb-1 text-2xl font-bold leading-10 text-black">{{ $category->localizedName() }}</h1>
                @if($category->localizedDescription())
                    <div class="text-base font-normal leading-normal text-gray-600"><p>{{ $category->localizedDescription() }}</p></div>
                @endif
            </div>
            <div class="mt-5">
                <span class="flex text-sm text-[#737373]">{{ $totalArticles }} {{ $isAr ? portal_content('helpcenter', 'category', 'article_word', 'article', 'مقالة') : Str::plural('article', $totalArticles) }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-5">
            @if($category->articles->isNotEmpty())
                <section class="flex flex-col rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-2 sm:p-3">
                    @foreach($category->articles as $article)
                        <a class="group/article flex flex-row justify-between gap-2 rounded-[10px] px-3 py-2 no-underline transition ease-linear hover:bg-orange-50 sm:py-3"
                           href="{{ route('portal.helpcenter.article.show', ['country' => $country, 'article' => $article->slug]) }}">
                            <span class="m-0 text-base text-black group-hover/article:text-orange-600">{{ $article->localizedTitle() }}</span>
                            <svg class="block h-4 w-4 shrink-0 text-black {{ $isAr ? 'rotate-90' : '-rotate-90' }}" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                        </a>
                    @endforeach
                </section>
            @endif

            @foreach($category->children as $child)
                @continue($child->articles->isEmpty())
                <section class="flex flex-col rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-2 sm:p-3">
                    <div class="p-3 pb-6 text-black">
                        <h2 class="m-0 text-xl font-bold no-underline">{{ $child->localizedName() }}</h2>
                    </div>
                    <hr class="mx-3 mb-2 mt-0 border-0 border-t border-solid border-[#e6e6e6]">
                    @foreach($child->articles as $article)
                        <a class="group/article flex flex-row justify-between gap-2 rounded-[10px] px-3 py-2 no-underline transition ease-linear hover:bg-orange-50 sm:py-3"
                           href="{{ route('portal.helpcenter.article.show', ['country' => $country, 'article' => $article->slug]) }}">
                            <span class="m-0 text-base text-black group-hover/article:text-orange-600">{{ $article->localizedTitle() }}</span>
                            <svg class="block h-4 w-4 shrink-0 text-black {{ $isAr ? 'rotate-90' : '-rotate-90' }}" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                        </a>
                    @endforeach
                </section>
            @endforeach

            @if($totalArticles === 0)
                <section class="flex flex-col items-center rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-10 text-center text-[#737373]">
                    {{ portal_content('helpcenter', 'category', 'no_articles', 'Articles in this category are coming soon.', 'ستتوفر المقالات في هذه الفئة قريباً.') }}
                </section>
            @endif
        </div>
    </div>
@endsection
