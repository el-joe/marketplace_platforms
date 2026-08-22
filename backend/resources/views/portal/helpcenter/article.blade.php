@extends('layouts.helpcenter')

@php
    $isAr = app()->getLocale() === 'ar';
    $category = $article->category;
    $parentCategory = $category?->parent;
    $toc = $article->table_of_contents;
    $arrow = $isAr ? 'rotate-90' : '-rotate-90';
    $crumbArrow = $isAr ? 'rotate-180' : '';
@endphp

@section('title', $article->localizedTitle() . ' | ' . ($isAr ? 'مركز مساعدة البائع' : 'noon Seller Help Center'))
@section('description', $article->localizedExcerpt() ?? '')

@section('header')
    @include('portal.partials.helpcenter-header', ['variant' => 'lite', 'country' => $country])
@endsection

@section('content')
    <div class="justify-between flex">
        <div class="relative z-3 w-full lg:max-w-[660px]">
            <nav class="pb-4 text-base" aria-label="{{ $isAr ? 'مسار التنقل' : 'Breadcrumb' }}">
                <ol class="m-0 flex list-none flex-wrap items-baseline gap-2 p-0">
                    <li class="flex items-center gap-2">
                        <a href="{{ route('portal.helpcenter.index', $country) }}" class="text-black no-underline hover:text-orange-600">{{ portal_content('helpcenter', 'article', 'all_categories', 'All Categories', 'كل الفئات') }}</a>
                        <svg width="6" height="10" viewBox="0 0 6 10" class="block h-2 w-2 fill-[#737373] {{ $crumbArrow }}" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.648862 0.898862C0.316916 1.23081 0.316916 1.769 0.648862 2.10094L3.54782 4.9999L0.648862 7.89886C0.316916 8.23081 0.316917 8.769 0.648862 9.10094C0.980808 9.43289 1.519 9.43289 1.85094 9.10094L5.35094 5.60094C5.68289 5.269 5.68289 4.73081 5.35094 4.39886L1.85094 0.898862C1.519 0.566916 0.980807 0.566916 0.648862 0.898862Z"></path></svg>
                    </li>
                    @if($parentCategory)
                        <li class="flex items-center gap-2">
                            <a href="{{ route('portal.helpcenter.category.show', ['country' => $country, 'category' => $parentCategory->slug]) }}" class="text-black no-underline hover:text-orange-600">{{ $parentCategory->localizedName() }}</a>
                            <svg width="6" height="10" viewBox="0 0 6 10" class="block h-2 w-2 fill-[#737373] {{ $crumbArrow }}" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.648862 0.898862C0.316916 1.23081 0.316916 1.769 0.648862 2.10094L3.54782 4.9999L0.648862 7.89886C0.316916 8.23081 0.316917 8.769 0.648862 9.10094C0.980808 9.43289 1.519 9.43289 1.85094 9.10094L5.35094 5.60094C5.68289 5.269 5.68289 4.73081 5.35094 4.39886L1.85094 0.898862C1.519 0.566916 0.980807 0.566916 0.648862 0.898862Z"></path></svg>
                        </li>
                    @endif
                    @if($category)
                        <li class="flex items-center gap-2">
                            <a href="{{ route('portal.helpcenter.category.show', ['country' => $country, 'category' => $category->slug]) }}" class="text-black no-underline hover:text-orange-600">{{ $category->localizedName() }}</a>
                            <svg width="6" height="10" viewBox="0 0 6 10" class="block h-2 w-2 fill-[#737373] {{ $crumbArrow }}" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.648862 0.898862C0.316916 1.23081 0.316916 1.769 0.648862 2.10094L3.54782 4.9999L0.648862 7.89886C0.316916 8.23081 0.316917 8.769 0.648862 9.10094C0.980808 9.43289 1.519 9.43289 1.85094 9.10094L5.35094 5.60094C5.68289 5.269 5.68289 4.73081 5.35094 4.39886L1.85094 0.898862C1.519 0.566916 0.980807 0.566916 0.648862 0.898862Z"></path></svg>
                        </li>
                    @endif
                    <li aria-current="page" class="text-[#737373]">{{ $article->localizedTitle() }}</li>
                </ol>
            </nav>

            <div class="mb-8 max-lg:mb-6">
                <div class="flex flex-col gap-3">
                    <h1 class="mb-1 text-2xl font-bold leading-10 text-black">{{ $article->localizedTitle() }}</h1>
                    <div class="-mt-0.5 text-sm text-[#737373]">
                        <time datetime="{{ ($article->published_at ?? $article->updated_at)->toIso8601String() }}" title="{{ $isAr ? 'تم التحديث' : 'Updated' }}">{{ $article->updatedLabel() }}</time>
                        &middot; {{ number_format($article->views_count) }} {{ portal_content('helpcenter', 'article', 'views_label', 'views', 'مشاهدة') }}
                    </div>
                </div>
            </div>

            @if(!empty($toc))
                <div class="mb-7 text-base lg:hidden" x-data="{ tocOpen: false }">
                    <div class="max-h-[calc(100vh-96px)] overflow-y-auto rounded-2xl border border-solid border-[#e6e6e6] text-black">
                        <div @click="tocOpen = !tocOpen" class="flex cursor-pointer flex-row justify-between border-b border-solid border-[#e6e6e6] px-4 py-2">
                            <div>{{ portal_content('helpcenter', 'article', 'table_of_contents', 'Table of contents', 'محتويات الصفحة') }}</div>
                            <svg class="mt-1 transition-transform" :class="tocOpen ? 'rotate-180' : ''" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.93353 5.93451C4.24595 5.62209 4.75248 5.62209 5.0649 5.93451L7.99922 8.86882L10.9335 5.93451C11.246 5.62209 11.7525 5.62209 12.0649 5.93451C12.3773 6.24693 12.3773 6.75346 12.0649 7.06588L8.5649 10.5659C8.25249 10.8783 7.74595 10.8783 7.43353 10.5659L3.93353 7.06588C3.62111 6.75346 3.62111 6.24693 3.93353 5.93451Z" fill="currentColor"></path></svg>
                        </div>
                        <div x-show="tocOpen" x-cloak class="my-2">
                            @foreach($toc as $item)
                                <section class="flex border-s-2 border-solid border-transparent px-7 py-1.5 {{ $item['level'] === 3 ? 'ps-10' : '' }}">
                                    <a href="#{{ $item['id'] }}" class="w-full text-sm no-underline hover:text-orange-600">{{ $item['label'] }}</a>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="hc-article-body">
                {!! $article->localizedBody() !!}

                @if($relatedArticles->isNotEmpty())
                    <section class="my-6">
                        <hr class="my-6 sm:my-8">
                        <div class="mb-3 text-xl font-bold">{{ portal_content('helpcenter', 'article', 'related_articles', 'Related Articles', 'مقالات ذات صلة') }}</div>
                        <section class="flex flex-col rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-2 sm:p-3">
                            @foreach($relatedArticles as $related)
                                <a class="group/article flex flex-row justify-between gap-2 rounded-[10px] px-3 py-2 no-underline transition ease-linear hover:bg-orange-50 sm:py-3"
                                   href="{{ route('portal.helpcenter.article.show', ['country' => $country, 'article' => $related->slug]) }}">
                                    <span class="m-0 text-base text-black group-hover/article:text-orange-600">{{ $related->localizedTitle() }}</span>
                                    <svg class="block h-4 w-4 shrink-0 text-black {{ $arrow }}" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                </a>
                            @endforeach
                        </section>
                    </section>
                @endif
            </div>

            <fieldset class="m-0 mt-6 rounded-[10px] border-0 sm:mt-8" x-data="{ picked: null }">
                <legend class="float-start w-full">{{ portal_content('helpcenter', 'article', 'feedback_prompt', 'Did this answer your question?', 'هل أجاب هذا المقال على سؤالك؟') }}</legend>
                <div class="mt-4 flex gap-2">
                    <button type="button" @click="picked = 'disappointed'" :class="picked === 'disappointed' ? 'bg-orange-100' : ''" class="rounded-full border border-solid border-[#e6e6e6] px-3 py-2"><span aria-hidden="true">😞</span></button>
                    <button type="button" @click="picked = 'neutral'" :class="picked === 'neutral' ? 'bg-orange-100' : ''" class="rounded-full border border-solid border-[#e6e6e6] px-3 py-2"><span aria-hidden="true">😐</span></button>
                    <button type="button" @click="picked = 'smiley'" :class="picked === 'smiley' ? 'bg-orange-100' : ''" class="rounded-full border border-solid border-[#e6e6e6] px-3 py-2"><span aria-hidden="true">😊</span></button>
                </div>
            </fieldset>
        </div>

        @if(!empty($toc))
            <div class="w-61 sticky top-8 {{ $isAr ? 'mr-7' : 'ml-7' }} max-w-61 self-start max-lg:hidden mt-16">
                <div class="max-h-[calc(100vh-96px)] overflow-y-auto rounded-2xl text-black">
                    <div class="my-2 font-semibold">{{ $isAr ? 'محتويات الصفحة' : 'Table of contents' }}</div>
                    <div class="my-2">
                        @foreach($toc as $item)
                            <section class="flex border-s-2 border-solid border-[#f2f2f2] px-7 py-1.5 hover:border-orange-400 {{ $item['level'] === 3 ? 'ps-10' : '' }}">
                                <a href="#{{ $item['id'] }}" class="w-full text-sm text-[#737373] no-underline hover:text-orange-600">{{ $item['label'] }}</a>
                            </section>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
